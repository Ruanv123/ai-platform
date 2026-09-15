<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Knowledge\MarkdownChunker;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Throwable;

class IngestDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Create a new ingestion job.
     */
    public function __construct(public int $documentId, public string $ingestionToken)
    {
        $this->onQueue('ingestion');
    }

    /**
     * Execute the job.
     */
    public function handle(MarkdownChunker $chunker): void
    {
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

        $text = $this->extractText($document);
        $chunks = $chunker->chunk($text);
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

        DB::transaction(function () use ($chunks, $document, $embeddings): void {
            $lockedDocument = Document::withoutGlobalScopes()
                ->lockForUpdate()
                ->whereKey($document->getKey())
                ->first();

            if ($lockedDocument === null || $lockedDocument->ingestion_token !== $this->ingestionToken) {
                return;
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

            $lockedDocument->update([
                'status' => DocumentStatus::Ready,
                'error_message' => null,
                'processed_at' => now(),
            ]);
        });
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

    private function extractText(Document $document): string
    {
        if ($document->type !== DocumentType::Text) {
            throw new RuntimeException('Este tipo de documento ainda não está disponível.');
        }

        $text = trim($document->content);

        if ($text === '') {
            throw new RuntimeException('O documento não contém texto para processar.');
        }

        return $text;
    }

    private function friendlyErrorMessage(?Throwable $exception): string
    {
        $message = $exception?->getMessage();

        if ($message !== null && preg_match('/^(PDF sem texto extraível|O documento não contém texto|Este tipo de documento)/u', $message)) {
            return $message;
        }

        return 'Não foi possível processar o documento. Tente novamente.';
    }
}
