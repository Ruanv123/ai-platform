<?php

namespace App\Http\Controllers\Knowledge;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\MoveDocumentRequest;
use App\Http\Requests\Knowledge\SaveDocumentRequest;
use App\Jobs\IngestDocument;
use App\Knowledge\DocumentFileStorage;
use App\Knowledge\DocumentStorageQuota;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    private const DOCUMENTS_PER_PAGE = 20;

    public function __construct(
        private readonly DocumentStorageQuota $quota,
        private readonly DocumentFileStorage $fileStorage,
    ) {}

    /**
     * Display documents from a collection.
     */
    public function index(Request $request, Collection $collection): Response
    {
        Gate::authorize('view', $collection);
        $user = $request->user();
        $team = $user->company();

        abort_if($team === null, 404);

        $filters = $this->documentFilters($request);
        $documents = $collection->documents()
            ->select([
                'id',
                'team_id',
                'collection_id',
                'type',
                'status',
                'title',
                'error_message',
                'size_bytes',
                'file_path',
                'original_filename',
                'mime_type',
                'url',
                'updated_at',
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $query->whereRaw(
                    'LOWER(documents.title) LIKE ?',
                    ['%'.mb_strtolower($filters['search']).'%'],
                );
            })
            ->when($filters['type'] !== '', fn ($query) => $query->where('type', $filters['type']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderBy($filters['sortColumn'], $filters['direction'])
            ->orderByDesc('id')
            ->paginate(self::DOCUMENTS_PER_PAGE)
            ->withQueryString();

        $documents->through(fn (Document $document): array => $this->documentSummaryData($document));

        return Inertia::render('knowledge/documents', [
            'collection' => [
                'id' => $collection->id,
                'name' => $collection->name,
                'description' => $collection->description,
            ],
            'documents' => $documents,
            'filters' => [
                'search' => $filters['search'],
                'type' => $filters['type'],
                'status' => $filters['status'],
                'sort' => $filters['sort'],
                'direction' => $filters['direction'],
            ],
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
            'polling' => $documents->getCollection()->contains(fn (array $document): bool => in_array(
                $document['status'],
                [DocumentStatus::Pending->value, DocumentStatus::Processing->value],
                true,
            )),
            'permissions' => $user->toTeamPermissions($team),
        ]);
    }

    /**
     * Display a complete document for the edit dialog.
     */
    public function show(Document $document): JsonResponse
    {
        Gate::authorize('view', $document);

        return response()->json($this->documentData($document));
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

        $validated = $request->validated();
        $type = DocumentType::from($request->string('type')->toString());
        $file = $validated['file'] ?? null;
        $newFilePath = $file instanceof UploadedFile
            ? $this->fileStorage->store($file, $team->getKey())
            : null;

        try {
            $document = DB::transaction(function () use ($request, $collection, $team, $type, $file, $newFilePath, $validated): Document {
                $lockedTeam = $team->newQuery()->lockForUpdate()->whereKey($team->getKey())->firstOrFail();
                $content = $this->documentContent($type, $validated);
                $faqItems = $this->faqItems($type, $validated);
                $sizeBytes = match ($type) {
                    DocumentType::File => $file instanceof UploadedFile ? (int) ($file->getSize() ?: 0) : 0,
                    DocumentType::Url => 0,
                    default => strlen($content),
                };
                $this->quota->assertCanStore(
                    $lockedTeam,
                    $sizeBytes,
                    attribute: $type === DocumentType::File ? 'file' : 'content',
                );
                $token = (string) Str::uuid();

                $document = Document::create([
                    'team_id' => $lockedTeam->getKey(),
                    'collection_id' => $collection->getKey(),
                    'type' => $type,
                    'status' => DocumentStatus::Pending,
                    'title' => $request->string('title')->toString(),
                    'content' => $content,
                    'faq_items' => $faqItems,
                    'file_path' => $newFilePath,
                    'original_filename' => $file instanceof UploadedFile
                        ? $this->fileStorage->originalFilename($file)
                        : null,
                    'mime_type' => $file instanceof UploadedFile ? $file->getMimeType() : null,
                    'url' => $type === DocumentType::Url ? $validated['url'] : null,
                    'size_bytes' => $sizeBytes,
                    'ingestion_token' => $token,
                ]);

                $this->dispatchIngestionAfterCommit($document, $token);

                return $document;
            });
        } catch (\Throwable $exception) {
            $this->deleteUnpersistedFile($newFilePath);

            throw $exception;
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento criado com sucesso.',
        ]);

        return $this->redirectToDocuments($request, $document->collection_id);
    }

    /**
     * Update a document and reprocess its content.
     */
    public function update(SaveDocumentRequest $request, Document $document): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);

        $validated = $request->validated();
        $file = $validated['file'] ?? null;
        $newFilePath = $file instanceof UploadedFile
            ? $this->fileStorage->store($file, $team->getKey())
            : null;
        $oldFilePath = $document->file_path;
        $token = (string) Str::uuid();

        try {
            DB::transaction(function () use ($request, $document, $team, $token, $file, $newFilePath, $validated): void {
                $lockedTeam = $team->newQuery()->lockForUpdate()->whereKey($team->getKey())->firstOrFail();
                $content = in_array($document->type, [DocumentType::File, DocumentType::Url], true)
                    ? $document->content
                    : $this->documentContent($document->type, $validated);
                $faqItems = $this->faqItems($document->type, $validated);
                $sizeBytes = match ($document->type) {
                    DocumentType::File => $file instanceof UploadedFile
                        ? (int) ($file->getSize() ?: 0)
                        : $document->size_bytes,
                    DocumentType::Url => $document->size_bytes,
                    default => strlen($content),
                };
                $this->quota->assertCanStore(
                    $lockedTeam,
                    $sizeBytes,
                    $document,
                    $document->type === DocumentType::File ? 'file' : 'content',
                );

                $updates = [
                    'title' => $request->string('title')->toString(),
                    'content' => $content,
                    'faq_items' => $faqItems,
                    'size_bytes' => $sizeBytes,
                    'status' => DocumentStatus::Pending,
                    'error_message' => null,
                    'processed_at' => null,
                    'ingestion_token' => $token,
                ];

                if ($document->type === DocumentType::Url) {
                    $updates['url'] = $validated['url'];
                }

                if ($document->type === DocumentType::File && $file instanceof UploadedFile) {
                    $updates['file_path'] = $newFilePath;
                    $updates['original_filename'] = $this->fileStorage->originalFilename($file);
                    $updates['mime_type'] = $file->getMimeType();
                }

                $document->update($updates);

                $this->dispatchIngestionAfterCommit($document, $token);
            });
        } catch (\Throwable $exception) {
            $this->deleteUnpersistedFile($newFilePath);

            throw $exception;
        }

        if ($newFilePath !== null && $oldFilePath !== null) {
            $this->fileStorage->delete($oldFilePath);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento atualizado e enviado para processamento.',
        ]);

        return $this->redirectToDocuments($request, $document->collection_id);
    }

    /**
     * Move a document and its indexed chunks to another collection.
     */
    public function move(MoveDocumentRequest $request, Document $document): RedirectResponse
    {
        $collectionId = (string) $request->validated('collection_id');
        $collection = Collection::query()->findOrFail($collectionId);

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

        return $this->redirectToDocuments($request, $collection);
    }

    /**
     * Delete a document and its chunks.
     */
    public function destroy(Request $request, Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);
        $collectionId = $document->collection_id;
        $this->fileStorage->delete($document->file_path);

        DB::transaction(function () use ($document): void {
            $collection = $document->collection()->lockForUpdate()->first();

            if (
                $document->site_read_page_id !== null
                && $document->url !== null
                && $collection?->is_site_collection
            ) {
                $collection->ignoredSiteUrls()->firstOrCreate(
                    ['url' => $document->url],
                    ['team_id' => $document->team_id],
                );
            }

            $document->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Documento excluído com sucesso.',
        ]);

        return $this->redirectToDocuments($request, $collectionId);
    }

    /**
     * Put a failed document back into the ingestion queue.
     */
    public function reprocess(Request $request, Document $document): RedirectResponse
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

        return $this->redirectToDocuments($request, $document->collection_id);
    }

    /**
     * Download the original file while keeping document isolation in place.
     */
    public function download(Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return $this->fileStorage->download($document);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentData(Document $document): array
    {
        return [
            ...$this->documentSummaryData($document),
            'content' => $document->content,
            'faqItems' => $document->faq_items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSummaryData(Document $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'originalFilename' => $document->original_filename,
            'mimeType' => $document->mime_type,
            'url' => $document->url,
            'downloadUrl' => $document->file_path === null ? null : route('documents.download', $document),
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

    /**
     * @return array{
     *     search: string,
     *     type: string,
     *     status: string,
     *     sort: string,
     *     sortColumn: string,
     *     direction: 'asc'|'desc'
     * }
     */
    private function documentFilters(Request $request): array
    {
        $type = DocumentType::tryFrom($request->string('type')->toString())->value ?? '';
        $status = DocumentStatus::tryFrom($request->string('status')->toString())->value ?? '';
        $sortOptions = [
            'title' => 'title',
            'size' => 'size_bytes',
            'updated_at' => 'updated_at',
        ];
        $sort = $request->string('sort')->toString();
        $sort = array_key_exists($sort, $sortOptions) ? $sort : 'updated_at';
        $direction = $request->string('direction')->toString();
        $direction = match ($direction) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $sort === 'updated_at' ? 'desc' : 'asc',
        };

        return [
            'search' => trim($request->string('search')->toString()),
            'type' => $type,
            'status' => $status,
            'sort' => $sort,
            'sortColumn' => $sortOptions[$sort],
            'direction' => $direction,
        ];
    }

    private function redirectToDocuments(Request $request, Collection|string $collection): RedirectResponse
    {
        return to_route('documents.index', [
            'collection' => $collection,
            ...Arr::only($request->query(), ['search', 'type', 'status', 'sort', 'direction', 'page']),
        ]);
    }

    private function dispatchIngestionAfterCommit(Document $document, string $token): void
    {
        DB::afterCommit(fn (): mixed => IngestDocument::dispatch($document->getKey(), $token)->onQueue('ingestion'));
    }

    private function deleteUnpersistedFile(?string $path): void
    {
        if ($path === null || Document::withoutGlobalScopes()->where('file_path', $path)->exists()) {
            return;
        }

        $this->fileStorage->delete($path);
    }

    /**
     * Build the text representation used for storage accounting and search context.
     *
     * @param  array<string, mixed>  $data
     */
    private function documentContent(DocumentType $type, array $data): string
    {
        if ($type === DocumentType::File) {
            return '';
        }

        if ($type === DocumentType::Url) {
            return '';
        }

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
