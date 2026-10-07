<?php

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    config(['services.firecrawl.key' => null]);
});

it('downloads a URL, extracts its main content and indexes Markdown', function () {
    config(['queue.default' => 'sync']);
    Embeddings::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://docs.example.test/article' => Http::response(
            file_get_contents(base_path('tests/Fixtures/knowledge/article.html')),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'Manual de trocas',
        'url' => 'https://docs.example.test/article',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->type)->toBe(DocumentType::Url)
        ->and($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content)->toContain('# Política de trocas | Loja Exemplo')
        ->and($document->content)->toContain('- Solicite a troca em até 7 dias.')
        ->and($document->content)->not->toContain('Navegação principal')
        ->and($document->content)->not->toContain('Aviso de cookies')
        ->and($document->content)->not->toContain('Rodapé institucional')
        ->and($document->size_bytes)->toBe(712);

    $this->assertDatabaseCount('chunks', 1);
    $this->assertDatabaseMissing('chunks', ['content' => 'Navegação principal']);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://docs.example.test/article');
    Http::assertSentCount(1);
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Solicite a troca em até 7 dias.'));
});

it('uses Firecrawl Markdown to ingest a URL document when configured', function () {
    config(['queue.default' => 'sync']);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Embeddings::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::response([
            'success' => true,
            'data' => ['markdown' => "# Política de trocas\n\nSolicite a troca em até 7 dias."],
        ]),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'Manual de trocas',
        'url' => 'https://docs.example.test/article',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->type)->toBe(DocumentType::Url)
        ->and($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content)->toBe("# Política de trocas\n\nSolicite a troca em até 7 dias.")
        ->and($document->size_bytes)->toBe(55);
    $this->assertDatabaseHas('chunks', ['document_id' => $document->id, 'content' => $document->content]);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://firecrawl.example.test/v2/scrape'
        && $request->hasHeader('Authorization', 'Bearer test-firecrawl-key')
        && $request['url'] === 'https://docs.example.test/article'
        && $request['formats'] === ['markdown']);
    Http::assertSentCount(1);
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Solicite a troca em até 7 dias.'));
});

it('falls back to Readability when Firecrawl returns an empty Markdown response', function () {
    config(['queue.default' => 'sync']);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Embeddings::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::response([
            'success' => true,
            'data' => ['markdown' => '  '],
        ]),
        'https://docs.example.test/article' => Http::response(
            file_get_contents(base_path('tests/Fixtures/knowledge/article.html')),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'Manual de trocas',
        'url' => 'https://docs.example.test/article',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content)->toContain('# Política de trocas | Loja Exemplo');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://firecrawl.example.test/v2/scrape');
    Http::assertSentCount(2);
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Solicite a troca em até 7 dias.'));
});

it('falls back to Readability when Firecrawl cannot connect', function () {
    config(['queue.default' => 'sync']);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Embeddings::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::failedConnection(),
        'https://docs.example.test/article' => Http::response(
            file_get_contents(base_path('tests/Fixtures/knowledge/article.html')),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'Manual de trocas',
        'url' => 'https://docs.example.test/article',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content)->toContain('# Política de trocas | Loja Exemplo');
    Http::assertSentCount(2);
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Solicite a troca em até 7 dias.'));
});

it('preserves URL document errors when Firecrawl and Readability both fail', function () {
    config(['queue.default' => 'sync']);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::response(['success' => false], 503),
        'https://missing.example.test/article' => Http::response('Not found', 404),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'Página ausente',
        'url' => 'https://missing.example.test/article',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('A página retornou um erro HTTP (404).');
    $this->assertDatabaseCount('chunks', 0);
    Http::assertSentCount(2);
});

it('marks a URL as failed when the page cannot be reached', function () {
    config(['queue.default' => 'sync']);
    Http::preventStrayRequests();
    Http::fake([
        'https://offline.example.test/article' => Http::failedConnection(),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'Página indisponível',
        'url' => 'https://offline.example.test/article',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('Não foi possível acessar a página informada.');
    $this->assertDatabaseCount('chunks', 0);
});

it('marks a URL as failed when the page returns an HTTP error', function () {
    config(['queue.default' => 'sync']);
    Http::preventStrayRequests();
    Http::fake([
        'https://missing.example.test/article' => Http::response('Not found', 404),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'Página ausente',
        'url' => 'https://missing.example.test/article',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('A página retornou um erro HTTP (404).');
});

it('marks a URL as failed when the page has no content', function () {
    config(['queue.default' => 'sync']);
    Http::preventStrayRequests();
    Http::fake([
        'https://empty.example.test/article' => Http::response('', 200),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'Página vazia',
        'url' => 'https://empty.example.test/article',
    ]);

    $response->assertRedirect(route('documents.index', $collection));
    $document = Document::query()->firstOrFail();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe('A página não possui conteúdo para processar.');
});

it('rejects URL documents without an HTTP or HTTPS URL', function () {
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'URL inválida',
        'url' => 'javascript:alert(1)',
    ]);

    $response->assertSessionHasErrors([
        'url' => 'Informe uma URL válida, começando por http:// ou https://.',
    ]);
    $this->assertDatabaseCount('documents', 0);
});

it('requires a URL for URL documents', function () {
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'URL ausente',
    ]);

    $response->assertSessionHasErrors([
        'url' => 'Informe a URL da página.',
    ]);
    $this->assertDatabaseCount('documents', 0);
});

it('rejects a URL longer than 2048 characters', function () {
    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(route('documents.store', $collection), [
        'type' => 'url',
        'title' => 'URL longa',
        'url' => 'https://example.test/'.str_repeat('a', 2040),
    ]);

    $response->assertSessionHasErrors([
        'url' => 'A URL deve ter no máximo 2048 caracteres.',
    ]);
    $this->assertDatabaseCount('documents', 0);
});

it('re-synchronizes a URL document and updates its indexed content and quota usage', function () {
    config(['queue.default' => 'sync']);
    Embeddings::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://docs.example.test/article' => Http::response(
            file_get_contents(base_path('tests/Fixtures/knowledge/article-updated.html')),
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        ),
    ]);

    $user = User::factory()->withTeam()->create();
    $collection = Collection::factory()->for($user->company())->create();
    $document = Document::factory()->for($collection)->create([
        'type' => DocumentType::Url,
        'status' => DocumentStatus::Ready,
        'content' => '# Política antiga\n\nConteúdo antigo.',
        'size_bytes' => 36,
        'url' => 'https://docs.example.test/article',
    ]);
    Chunk::factory()->for($document)->create([
        'content' => $document->content,
        'position' => 0,
        'embedding' => array_merge([1.0], array_fill(0, 1535, 0.0)),
    ]);

    $response = $this->actingAs($user)->post(route('documents.reprocess', $document));

    $response->assertRedirect(route('documents.index', $collection));
    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->content)->toContain('# Política atualizada | Loja Exemplo')
        ->and($document->content)->toContain('Prazo estendido para 10 dias.')
        ->and($document->size_bytes)->toBe(508);
    $this->assertDatabaseMissing('chunks', ['content' => '# Política antiga\n\nConteúdo antigo.']);
    $this->assertDatabaseHas('chunks', ['content' => $document->content]);
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Prazo estendido para 10 dias.'));
});
