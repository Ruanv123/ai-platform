<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Jobs\IngestDocument;
use App\Knowledge\MarkdownChunker;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

it('creates a text document, ingests it and reports its storage usage', function () {
    config(['queue.default' => 'sync']);
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $content = "# Horário\n\nAtendemos de segunda a sexta.";

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'title' => 'Horário de atendimento',
        'content' => $content,
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Documento criado com sucesso.',
    ]);
    $document = Document::query()->firstOrFail();

    expect($document->type)->toBe(DocumentType::Text)
        ->and($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->size_bytes)->toBe(strlen($content))
        ->and($document->error_message)->toBeNull();

    $this->assertDatabaseCount('chunks', 1);
    Embeddings::assertGenerated(fn ($prompt) => $prompt->contains('Atendemos de segunda a sexta.'));
});

it('reprocesses edited text and replaces the previous chunks', function () {
    config(['queue.default' => 'sync']);
    Embeddings::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'title' => 'Política antiga',
        'content' => 'A política antiga não deve aparecer.',
        'status' => DocumentStatus::Ready,
        'size_bytes' => strlen('A política antiga não deve aparecer.'),
    ]);
    Chunk::create([
        'document_id' => $document->id,
        'team_id' => $user->company()->id,
        'collection_id' => $collection->id,
        'content' => 'A política antiga não deve aparecer.',
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);

    $response = $this->actingAs($user)->patch(route('documents.update', $document), [
        'title' => 'Política atualizada',
        'content' => 'A política atualizada deve aparecer.',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->title)->toBe('Política atualizada')
        ->and($document->size_bytes)->toBe(strlen('A política atualizada deve aparecer.'));

    $this->assertDatabaseMissing('chunks', ['content' => 'A política antiga não deve aparecer.']);
    $this->assertDatabaseHas('chunks', ['content' => 'A política atualizada deve aparecer.']);
});

it('shows documents, storage usage and polling while ingestion is pending', function () {
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->pending()->create([
        'content' => 'Aguardando processamento.',
        'size_bytes' => strlen('Aguardando processamento.'),
    ]);

    $response = $this->actingAs($user)->get(route('documents.index', $collection));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('knowledge/documents')
        ->where('collection.id', $collection->id)
        ->where('documents.0.id', $document->id)
        ->where('documents.0.status', 'pending')
        ->where('storage.usedBytes', strlen('Aguardando processamento.'))
        ->where('polling', true),
    );
});

it('blocks a document that would exceed the company storage quota', function () {
    config(['knowledge.storage_quota_bytes' => 10]);
    Queue::fake();

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'title' => 'Grande demais',
        'content' => 'Este conteúdo excede a cota.',
    ]);

    $response->assertSessionHasErrors([
        'content' => 'O conteúdo ultrapassa a cota de armazenamento de 500 MB da Empresa.',
    ]);
    $this->assertDatabaseCount('documents', 0);
    Queue::assertNothingPushed();
});

it('removes document chunks and releases its storage usage when deleted', function () {
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'content' => 'Conteúdo removível.',
        'size_bytes' => strlen('Conteúdo removível.'),
        'status' => DocumentStatus::Ready,
    ]);
    Chunk::create([
        'document_id' => $document->id,
        'team_id' => $user->company()->id,
        'collection_id' => $collection->id,
        'content' => 'Conteúdo removível.',
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);

    $response = $this->actingAs($user)->delete(route('documents.destroy', $document));

    $response->assertRedirect(route('documents.index', $collection));
    $this->assertModelMissing($document);
    $this->assertDatabaseCount('chunks', 0);
});

it('moves a document and its existing chunks to another collection', function () {
    $user = User::factory()->withTeam()->create();
    $source = Collection::factory()->for($user->company())->create(['name' => 'Origem']);
    $target = Collection::factory()->for($user->company())->create(['name' => 'Destino']);
    $document = Document::factory()->for($source)->create([
        'status' => DocumentStatus::Ready,
    ]);
    $chunk = Chunk::create([
        'document_id' => $document->id,
        'team_id' => $user->company()->id,
        'collection_id' => $source->id,
        'content' => $document->content,
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);

    $response = $this->actingAs($user)->patch(route('documents.move', $document), [
        'collection_id' => $target->id,
    ]);

    $response->assertRedirect(route('documents.index', $target));
    $this->assertDatabaseHas('documents', [
        'id' => $document->id,
        'collection_id' => $target->id,
    ]);
    $this->assertDatabaseHas('chunks', [
        'id' => $chunk->id,
        'collection_id' => $target->id,
    ]);
});

it('marks an ingestion as failed and preserves the previous chunks', function () {
    Embeddings::fake(function (): never {
        throw new RuntimeException('Provider unavailable');
    });

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'status' => DocumentStatus::Pending,
        'ingestion_token' => '00000000-0000-0000-0000-000000000001',
    ]);
    $chunk = Chunk::create([
        'document_id' => $document->id,
        'team_id' => $user->company()->id,
        'collection_id' => $collection->id,
        'content' => 'Versão anterior preservada.',
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);
    $job = new IngestDocument($document->id, '00000000-0000-0000-0000-000000000001');

    try {
        $job->handle(app(MarkdownChunker::class));
    } catch (RuntimeException $exception) {
        $job->failed($exception);
    }

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('Não foi possível processar o documento. Tente novamente.');
    $this->assertModelExists($chunk);
});

it('returns not found for another company document', function () {
    $user = User::factory()->withTeam()->create();
    $document = Document::factory()->create();

    $response = $this->actingAs($user)->patch(route('documents.update', $document), [
        'title' => 'Tentativa',
        'content' => 'Não pode alterar.',
    ]);

    $response->assertNotFound();
});

it('forbids members from changing documents', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $member->teams()->attach($owner->company(), ['role' => 'member']);
    $collection = Collection::factory()->for($owner->company())->create();
    $document = Document::factory()->for($collection)->create();

    $response = $this->actingAs($member)->post(route('documents.store', $collection), [
        'title' => 'Sem permissão',
        'content' => 'Não pode criar.',
    ]);

    $response->assertForbidden();
});
