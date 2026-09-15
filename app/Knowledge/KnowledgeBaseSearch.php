<?php

namespace App\Knowledge;

use App\Enums\DocumentStatus;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Team;
use Illuminate\Support\Collection as SupportCollection;
use Laravel\Ai\Embeddings;

class KnowledgeBaseSearch
{
    /**
     * Search ready knowledge chunks available to a set of collections.
     *
     * @param  iterable<int, Collection|int>  $allowedCollections
     * @return SupportCollection<int, array{
     *     content: string,
     *     document: array{id: int, title: string},
     *     collection: array{id: int, name: string},
     *     similarity: float,
     * }>
     */
    public function search(Team $team, iterable $allowedCollections, string $question): SupportCollection
    {
        $collectionIds = collect($allowedCollections)
            ->map(fn (Collection|int $collection): int => $collection instanceof Collection
                ? $collection->getKey()
                : $collection)
            ->filter()
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();

        if ($collectionIds->isEmpty()) {
            return collect();
        }

        $embedding = Embeddings::for([$question])
            ->dimensions((int) config('knowledge.embedding_dimensions', 1536))
            ->generate(
                config('knowledge.embedding_provider', 'openai'),
                config('knowledge.embedding_model', 'text-embedding-3-small'),
            )
            ->first();
        $minimumSimilarity = (float) config('knowledge.search.min_similarity', 0.35);
        $topK = (int) config('knowledge.search.top_k', 5);

        return Chunk::withoutGlobalScopes()
            ->join('documents', 'documents.id', '=', 'chunks.document_id')
            ->join('collections', 'collections.id', '=', 'chunks.collection_id')
            ->select('chunks.*', 'documents.title as document_title', 'collections.name as collection_name')
            ->where('chunks.team_id', $team->getKey())
            ->where('documents.team_id', $team->getKey())
            ->where('collections.team_id', $team->getKey())
            ->whereIn('chunks.collection_id', $collectionIds)
            ->where('documents.status', DocumentStatus::Ready->value)
            ->selectVectorDistance('embedding', $embedding, as: 'distance')
            ->whereVectorDistanceLessThan('embedding', $embedding, maxDistance: 1 - $minimumSimilarity)
            ->orderByVectorDistance('embedding', $embedding)
            ->limit($topK)
            ->toBase()
            ->get()
            ->map(fn (object $chunk): array => [
                'content' => (string) $chunk->content,
                'document' => [
                    'id' => (int) $chunk->document_id,
                    'title' => (string) $chunk->document_title,
                ],
                'collection' => [
                    'id' => (int) $chunk->collection_id,
                    'name' => (string) $chunk->collection_name,
                ],
                'similarity' => (float) max(0.0, 1 - (float) $chunk->distance),
            ])
            ->values();
    }
}
