<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Jobs\IngestDocument;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

it('creates a FAQ document with one indexed chunk per pair', function () {
    config(['queue.default' => 'sync']);
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $items = [
        ['question' => 'Qual é o prazo?', 'answer' => 'O prazo é de 7 dias.'],
        ['question' => 'Como rastrear?', 'answer' => 'Use o código enviado por e-mail.'],
    ];

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'faq',
        'title' => 'Dúvidas frequentes',
        'items' => $items,
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->type)->toBe(DocumentType::Faq)
        ->and($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->faq_items)->toBe($items)
        ->and($document->size_bytes)->toBeGreaterThan(0);

    $this->assertDatabaseCount('chunks', 2);
    $this->assertDatabaseHas('chunks', [
        'document_id' => $document->id,
        'position' => 0,
        'content' => "Pergunta: Qual é o prazo?\nResposta: O prazo é de 7 dias.",
    ]);
    $this->assertDatabaseHas('chunks', [
        'document_id' => $document->id,
        'position' => 1,
        'content' => "Pergunta: Como rastrear?\nResposta: Use o código enviado por e-mail.",
    ]);
});

it('reprocesses edited FAQ pairs and replaces the previous chunks', function () {
    config(['queue.default' => 'sync']);
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'type' => DocumentType::Faq,
        'faq_items' => [['question' => 'Antiga?', 'answer' => 'Resposta antiga.']],
        'content' => 'Pergunta: Antiga?\nResposta: Resposta antiga.',
        'status' => DocumentStatus::Ready,
        'size_bytes' => 40,
    ]);
    Chunk::factory()->for($document)->create([
        'content' => 'Pergunta: Antiga?\nResposta: Resposta antiga.',
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);

    $response = $this->actingAs($user)->patch(route('documents.update', $document), [
        'type' => 'faq',
        'title' => 'FAQ atualizada',
        'items' => [['question' => 'Nova?', 'answer' => 'Resposta nova.']],
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->faq_items)->toBe([['question' => 'Nova?', 'answer' => 'Resposta nova.']]);

    $this->assertDatabaseMissing('chunks', ['content' => "Pergunta: Antiga?\nResposta: Resposta antiga."]);
    $this->assertDatabaseHas('chunks', ['content' => "Pergunta: Nova?\nResposta: Resposta nova."]);
});

it('marks an edited FAQ as pending and dispatches its ingestion job', function () {
    Queue::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'type' => DocumentType::Faq,
        'faq_items' => [['question' => 'Antiga?', 'answer' => 'Resposta antiga.']],
        'content' => 'Pergunta: Antiga?\nResposta: Resposta antiga.',
        'status' => DocumentStatus::Ready,
    ]);

    $response = $this->actingAs($user)->patch(route('documents.update', $document), [
        'type' => 'faq',
        'title' => $document->title,
        'items' => [['question' => 'Nova?', 'answer' => 'Resposta nova.']],
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->processed_at)->toBeNull();
    Queue::assertPushed(IngestDocument::class);
});

it('rejects an FAQ without at least one complete pair', function () {
    Queue::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'faq',
        'title' => 'FAQ inválida',
        'items' => [['question' => '', 'answer' => '']],
    ]);

    $response->assertSessionHasErrors([
        'items.0.question' => 'Informe a pergunta.',
        'items.0.answer' => 'Informe a resposta.',
    ]);
    $this->assertDatabaseCount('documents', 0);
    Queue::assertNothingPushed();
});

it('blocks an FAQ that would exceed the company storage quota', function () {
    config(['knowledge.storage_quota_bytes' => 10]);
    Queue::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'faq',
        'title' => 'FAQ grande demais',
        'items' => [['question' => 'Uma pergunta longa', 'answer' => 'Uma resposta longa']],
    ]);

    $response->assertSessionHasErrors('content');
    $this->assertDatabaseCount('documents', 0);
    Queue::assertNothingPushed();
});

it('forbids members from creating FAQ documents', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $member->teams()->attach($owner->company(), ['role' => 'member']);
    $collection = Collection::factory()->for($owner->company())->create();

    $response = $this->actingAs($member)->post(route('documents.store', $collection), [
        'type' => 'faq',
        'title' => 'Sem permissão',
        'items' => [['question' => 'Pergunta', 'answer' => 'Resposta']],
    ]);

    $response->assertForbidden();
});
