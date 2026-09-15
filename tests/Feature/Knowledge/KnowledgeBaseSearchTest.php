<?php

use App\Enums\DocumentStatus;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\Team;
use Laravel\Ai\Embeddings;

it('returns up to five ready chunks above the configured similarity threshold in order', function () {
    Embeddings::fake([
        [array_merge([1.0], array_fill(0, 1535, 0.0))],
    ]);

    $team = Team::factory()->create();
    $collection = Collection::factory()->for($team)->create();
    $document = Document::factory()->for($collection)->create(['status' => DocumentStatus::Ready]);

    foreach ([1.0, 0.9, 0.8, 0.7, 0.6, 0.5, 0.2] as $position => $similarity) {
        $second = sqrt(1 - ($similarity ** 2));
        Chunk::factory()->for($document)->create([
            'content' => "Trecho {$position}",
            'position' => $position,
            'embedding' => array_merge([$similarity, $second], array_fill(0, 1534, 0.0)),
        ]);
    }

    $results = app(KnowledgeBaseSearch::class)->search($team, [$collection], 'pergunta');

    expect($results)->toHaveCount(5)
        ->and($results->pluck('content')->all())->toBe([
            'Trecho 0',
            'Trecho 1',
            'Trecho 2',
            'Trecho 3',
            'Trecho 4',
        ])
        ->and($results->first()['document']['title'])->toBe($document->title)
        ->and($results->first()['collection']['name'])->toBe($collection->name)
        ->and($results->first()['similarity'])->toBeGreaterThanOrEqual(0.99);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('pergunta'));
});

it('only searches ready documents from the requested company and collections', function () {
    Embeddings::fake([
        [array_merge([1.0], array_fill(0, 1535, 0.0))],
    ]);

    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $collection = Collection::factory()->for($team)->create();
    $otherCollection = Collection::factory()->for($otherTeam)->create();
    $readyDocument = Document::factory()->for($collection)->create(['status' => DocumentStatus::Ready]);
    $pendingDocument = Document::factory()->for($collection)->create(['status' => DocumentStatus::Pending]);
    $otherDocument = Document::factory()->for($otherCollection)->create(['status' => DocumentStatus::Ready]);

    foreach ([$readyDocument, $pendingDocument, $otherDocument] as $document) {
        Chunk::factory()->for($document)->create([
            'content' => $document->title,
            'position' => 0,
            'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
        ]);
    }

    $results = app(KnowledgeBaseSearch::class)->search($team, [$collection], 'pergunta');

    expect($results->pluck('content')->all())->toBe([$readyDocument->title]);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('pergunta'));
});

it('includes a chunk whose similarity is exactly the configured threshold', function () {
    Embeddings::fake([
        [array_merge([1.0], array_fill(0, 1535, 0.0))],
    ]);

    $team = Team::factory()->create();
    $collection = Collection::factory()->for($team)->create();
    $document = Document::factory()->for($collection)->create(['status' => DocumentStatus::Ready]);
    $similarity = config('knowledge.search.min_similarity');
    $second = sqrt(1 - ($similarity ** 2));

    Chunk::factory()->for($document)->create([
        'content' => 'Trecho no limite da busca',
        'embedding' => array_merge([$similarity, $second], array_fill(0, 1534, 0.0)),
    ]);

    $results = app(KnowledgeBaseSearch::class)->search($team, [$collection], 'pergunta');

    expect($results->pluck('content')->all())->toBe(['Trecho no limite da busca']);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('pergunta'));
});

it('does not query embeddings or the database when no collections are allowed', function () {
    Embeddings::fake()->preventStrayEmbeddings();

    $results = app(KnowledgeBaseSearch::class)->search(Team::factory()->make(), [], 'pergunta');

    expect($results)->toBeEmpty();
    Embeddings::assertNothingGenerated();
});
