<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\MoveDocumentRequest;
use App\Http\Requests\Knowledge\SaveDocumentRequest;
use App\Jobs\IngestDocument;
use App\Knowledge\DocumentStorageQuota;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentStorageQuota $quota) {}

    /**
     * Display documents from a collection.
     */
    public function index(Request $request, Collection $collection): Response
    {
        Gate::authorize('view', $collection);
        $user = $request->user();
        $team = $user->company();

        abort_if($team === null, 404);

        $documents = $collection->documents()
            ->latest('updated_at')
            ->get()
            ->map(fn (Document $document): array => $this->documentData($document))
            ->values();

        return Inertia::render('knowledge/documents', [
            'collection' => [
                'id' => $collection->id,
                'name' => $collection->name,
                'description' => $collection->description,
            ],
            'documents' => $documents,
            'collections' => $team->collections()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Collection $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                ])
                ->values(),
            'storage' => $this->storageData($team),
            'polling' => $documents->contains(fn (array $document): bool => in_array(
                $document['status'],
                [DocumentStatus::Pending->value, DocumentStatus::Processing->value],
                true,
            )),
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }

    /**
     * Store a document and dispatch its ingestion.
     */
    public function store(SaveDocumentRequest $request, Collection $collection): RedirectResponse
    {
        $user = $request->user();
        $team = $user->company();

        abort_if($team === null, 404);
        Gate::authorize('view', $collection);

        $document = DB::transaction(function () use ($request, $collection, $team): Document {
            $lockedTeam = $team->newQuery()->lockForUpdate()->whereKey($team->getKey())->firstOrFail();
            $type = DocumentType::from($request->string('type')->toString());
            $content = $this->documentContent($type, $request->validated());
            $faqItems = $this->faqItems($type, $request->validated());
            $this->quota->assertCanStore($lockedTeam, strlen($content));
            $token = (string) Str::uuid();

            $document = Document::create([
                'team_id' => $lockedTeam->getKey(),
                'collection_id' => $collection->getKey(),
                'type' => $type,
                'status' => DocumentStatus::Pending,
                'title' => $request->string('title')->toString(),
                'content' => $content,
                'faq_items' => $faqItems,
                'size_bytes' => strlen($content),
                'ingestion_token' => $token,
            ]);

            $this->dispatchIngestionAfterCommit($document, $token);

            return $document;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento criado com sucesso.',
        ]);

        return to_route('documents.index', $document->collection_id);
    }

    /**
     * Update a document and reprocess its content.
     */
    public function update(SaveDocumentRequest $request, Document $document): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);

        $token = (string) Str::uuid();
        DB::transaction(function () use ($request, $document, $team, $token): void {
            $lockedTeam = $team->newQuery()->lockForUpdate()->whereKey($team->getKey())->firstOrFail();
            $content = $this->documentContent($document->type, $request->validated());
            $faqItems = $this->faqItems($document->type, $request->validated());
            $this->quota->assertCanStore($lockedTeam, strlen($content), $document);

            $document->update([
                'title' => $request->string('title')->toString(),
                'content' => $content,
                'faq_items' => $faqItems,
                'size_bytes' => strlen($content),
                'status' => DocumentStatus::Pending,
                'error_message' => null,
                'processed_at' => null,
                'ingestion_token' => $token,
            ]);

            $this->dispatchIngestionAfterCommit($document, $token);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento atualizado e enviado para processamento.',
        ]);

        return to_route('documents.index', $document->collection_id);
    }

    /**
     * Move a document and its indexed chunks to another collection.
     */
    public function move(MoveDocumentRequest $request, Document $document): RedirectResponse
    {
        $collection = Collection::query()->findOrFail((int) $request->integer('collection_id'));

        DB::transaction(function () use ($document, $collection): void {
            $document->update(['collection_id' => $collection->getKey()]);
            Chunk::withoutGlobalScopes()
                ->where('document_id', $document->getKey())
                ->update(['collection_id' => $collection->getKey()]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento movido com sucesso.',
        ]);

        return to_route('documents.index', $collection);
    }

    /**
     * Delete a document and its chunks.
     */
    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);
        $collectionId = $document->collection_id;
        $document->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento excluído com sucesso.',
        ]);

        return to_route('documents.index', $collectionId);
    }

    /**
     * Put a failed document back into the ingestion queue.
     */
    public function reprocess(Document $document): RedirectResponse
    {
        Gate::authorize('update', $document);
        $token = (string) Str::uuid();
        $document->update([
            'status' => DocumentStatus::Pending,
            'error_message' => null,
            'processed_at' => null,
            'ingestion_token' => $token,
        ]);
        IngestDocument::dispatch($document->getKey(), $token)->onQueue('ingestion');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento enviado para reprocessamento.',
        ]);

        return to_route('documents.index', $document->collection_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'content' => $document->content,
            'faqItems' => $document->faq_items,
            'type' => $document->type->value,
            'status' => $document->status->value,
            'statusLabel' => $document->status->label(),
            'errorMessage' => $document->error_message,
            'sizeBytes' => $document->size_bytes,
            'updatedAt' => $document->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{usedBytes: int, limitBytes: int, usedPercentage: float}
     */
    private function storageData(Team $team): array
    {
        $usedBytes = $this->quota->usage($team);
        $limitBytes = $this->quota->limit();

        return [
            'usedBytes' => $usedBytes,
            'limitBytes' => $limitBytes,
            'usedPercentage' => round(min(100, ($usedBytes / max(1, $limitBytes)) * 100), 1),
        ];
    }

    private function dispatchIngestionAfterCommit(Document $document, string $token): void
    {
        DB::afterCommit(fn (): mixed => IngestDocument::dispatch($document->getKey(), $token)->onQueue('ingestion'));
    }

    /**
     * Build the text representation used for storage accounting and search context.
     *
     * @param  array<string, mixed>  $data
     */
    private function documentContent(DocumentType $type, array $data): string
    {
        if ($type === DocumentType::Text) {
            return (string) ($data['content'] ?? '');
        }

        $faqItems = $this->faqItems($type, $data) ?? [];

        return implode("\n\n", array_map(
            static fn (array $item): string => "Pergunta: {$item['question']}\nResposta: {$item['answer']}",
            $faqItems,
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{question: string, answer: string}>|null
     */
    private function faqItems(DocumentType $type, array $data): ?array
    {
        if ($type !== DocumentType::Faq) {
            return null;
        }

        $items = $data['items'] ?? [];

        if (! is_array($items)) {
            return [];
        }

        $faqItems = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = $item['question'] ?? null;
            $answer = $item['answer'] ?? null;

            if (! is_string($question) || ! is_string($answer)) {
                continue;
            }

            $faqItems[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $faqItems;
    }
}
