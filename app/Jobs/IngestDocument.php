<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Knowledge\DocumentFileExtractor;
use App\Knowledge\DocumentStorageQuota;
use App\Knowledge\DocumentUrlExtractor;
use App\Knowledge\MarkdownChunker;
use App\Knowledge\UrlDocumentException;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Throwable;

class IngestDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    /**
     * Create a new ingestion job.
     */
    public function __construct(public int $documentId, public string $ingestionToken, public ?string $extractedUrlContent = null)
    {
        $this->onQueue('ingestion');
    }

    /**
     * Execute the job.
     */
    public function handle(
        MarkdownChunker $chunker,
        DocumentFileExtractor $fileExtractor,
        DocumentUrlExtractor $urlExtractor,
        DocumentStorageQuota $quota,
    ): void {
        $document = Document::withoutGlobalScopes()->whereKey($this->documentId)->first();

        if ($document === null || $document->ingestion_token !== $this->ingestionToken) {
            return;
        }

        if (! in_array($document->status, [DocumentStatus::Pending, DocumentStatus::Processing], true)) {
            return;
        }

        $document->update([
            'status' => DocumentStatus::Processing,
            'error_message' => null,
        ]);

        $extractedContent = null;

        try {
            if ($document->type === DocumentType::Faq) {
                $extractedContent = $document->content;
                $chunks = $this->extractFaqChunks($document);
            } else {
                $chunks = $chunker->chunk($extractedContent = $this->extractText($document, $fileExtractor, $urlExtractor));
            }
        } catch (UrlDocumentException|ValidationException $exception) {
            $this->markAsFailed($document, $this->friendlyErrorMessage($exception));

            return;
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'PDF sem texto extraível') {
                $document->update([
                    'status' => DocumentStatus::Failed,
                    'error_message' => $exception->getMessage(),
                ]);

                return;
            }

            throw $exception;
        }
        try {
            $embeddings = Embeddings::for($chunks)
                ->dimensions((int) config('knowledge.embedding_dimensions', 1536))
                ->generate(
                    config('knowledge.embedding_provider', 'openai'),
                    config('knowledge.embedding_model', 'text-embedding-3-small'),
                )
                ->embeddings;

            if (count($chunks) !== count($embeddings)) {
                throw new RuntimeException('Não foi possível gerar os vetores do documento.');
            }

            DB::transaction(function () use ($chunks, $document, $embeddings, $extractedContent, $quota): void {
                $lockedDocument = Document::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->whereKey($document->getKey())
                    ->first();

                if ($lockedDocument === null || $lockedDocument->ingestion_token !== $this->ingestionToken) {
                    return;
                }

                if (in_array($lockedDocument->type, [DocumentType::Url, DocumentType::Faq], true) && $extractedContent !== null) {
                    $lockedTeam = Team::withoutGlobalScopes()
                        ->lockForUpdate()
                        ->whereKey($lockedDocument->team_id)
                        ->firstOrFail();
                    $quota->assertCanStore(
                        $lockedTeam,
                        strlen($extractedContent),
                        $lockedDocument,
                        $lockedDocument->type === DocumentType::Url ? 'url' : 'content',
                    );
                }

                Chunk::withoutGlobalScopes()
                    ->where('document_id', $lockedDocument->getKey())
                    ->delete();

                foreach ($chunks as $position => $content) {
                    Chunk::create([
                        'document_id' => $lockedDocument->getKey(),
                        'team_id' => $lockedDocument->team_id,
                        'collection_id' => $lockedDocument->collection_id,
                        'position' => $position,
                        'content' => $content,
                        'embedding' => $embeddings[$position],
                    ]);
                }

                $updates = [
                    'status' => DocumentStatus::Ready,
                    'error_message' => null,
                    'processed_at' => now(),
                ];

                if ($lockedDocument->type === DocumentType::File && $extractedContent !== null) {
                    $updates['content'] = $extractedContent;
                }

                if (in_array($lockedDocument->type, [DocumentType::Url, DocumentType::Faq], true) && $extractedContent !== null) {
                    $updates['content'] = $extractedContent;
                    $updates['size_bytes'] = strlen($extractedContent);
                }

                $lockedDocument->update($updates);
            });
        } catch (ValidationException $exception) {
            $this->markAsFailed($document, $this->friendlyErrorMessage($exception));

            return;
        }
    }

    /**
     * Mark the document as failed after all queue attempts are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $document = Document::withoutGlobalScopes()->whereKey($this->documentId)->first();

        if ($document === null || $document->ingestion_token !== $this->ingestionToken) {
            return;
        }

        $document->update([
            'status' => DocumentStatus::Failed,
            'error_message' => $this->friendlyErrorMessage($exception),
        ]);
    }

    private function extractText(
        Document $document,
        DocumentFileExtractor $fileExtractor,
        DocumentUrlExtractor $urlExtractor,
    ): string {
        if ($document->type === DocumentType::File) {
            return $fileExtractor->extract($document);
        }

        if ($document->type !== DocumentType::Text) {
            if ($document->type === DocumentType::Url && $document->url !== null) {
                return $this->extractedUrlContent ?? $urlExtractor->extract($document->url);
            }

            throw new RuntimeException('Este tipo de documento ainda não está disponível.');
        }

        $text = trim($document->content);

        if ($text === '') {
            throw new RuntimeException('O documento não contém texto para processar.');
        }

        return $text;
    }

    /**
     * Turn every structured FAQ pair into exactly one chunk.
     *
     * @return array<int, string>
     */
    private function extractFaqChunks(Document $document): array
    {
        $items = $document->faq_items;

        if (! is_array($items) || $items === []) {
            throw new RuntimeException('O FAQ não contém pares para processar.');
        }

        return array_map(static fn (array $item): string => "Pergunta: {$item['question']}\nResposta: {$item['answer']}", $items);
    }

    private function friendlyErrorMessage(?Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return (string) collect($exception->errors())->flatten()->first();
        }

        $message = $exception?->getMessage();

        if ($message !== null && preg_match('/^(A página|Não foi possível acessar|Não foi possível extrair|PDF sem texto extraível|O documento não contém texto|O FAQ não contém pares|Este tipo de documento)/u', $message)) {
            return $message;
        }

        return 'Não foi possível processar o documento. Tente novamente.';
    }

    private function markAsFailed(Document $document, string $message): void
    {
        $document->update([
            'status' => DocumentStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
