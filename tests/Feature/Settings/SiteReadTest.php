<?php

use App\Ai\Agents\ExtractSiteFaq;
use App\Ai\Agents\SelectSitePages;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\SiteReadPageStatus;
use App\Enums\SiteReadStatus;
use App\Enums\TeamRole;
use App\Jobs\ReadSite;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\Collection;
use App\Models\Document;
use App\Models\SiteRead;
use App\Models\SiteReadPage;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;

function fakeSiteReadFirecrawl(array $mappedUrls, array $markdownByUrl, array $unavailableUrls = []): void
{
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/map' => Http::response([
            'success' => true,
            'links' => array_map(static fn (string $url): array => ['url' => $url], $mappedUrls),
        ]),
        'https://firecrawl.example.test/v2/scrape' => function (Request $request) use ($markdownByUrl) {
            $markdown = $markdownByUrl[$request['url']] ?? null;

            return is_string($markdown)
                ? Http::response(['success' => true, 'data' => ['markdown' => $markdown]])
                : Http::response(['success' => false], 503);
        },
        'https://site.example.test/*' => function (Request $request) use ($unavailableUrls) {
            return in_array($request->url(), $unavailableUrls, true)
                ? Http::response('Indisponível', 404)
                : Http::response('Página sem conteúdo principal', 200);
        },
    ]);
}

function enableSiteReadFakes(array $selectedUrls, ?string $faqUrl = null, ?array $faqItems = null): void
{
    SelectSitePages::fake([['urls' => $selectedUrls, 'faq_url' => $faqUrl]])->preventStrayPrompts();
    ExtractSiteFaq::fake($faqItems === null ? [] : [['items' => $faqItems]])->preventStrayPrompts();
    Embeddings::fake();
    config(['queue.default' => 'sync']);
}

it('follows the first business website on Linktree and preserves both addresses', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $linktreeUrl = 'https://linktr.ee/loja-aurora';
    $businessWebsite = 'https://business.example.test/';
    $aboutUrl = 'https://business.example.test/sobre';
    $team->update(['website' => $linktreeUrl]);
    $markdownByUrl = [
        $linktreeUrl => <<<'MARKDOWN'
            [Instagram](https://instagram.com/loja-aurora)
            [Facebook](https://www.facebook.com/loja-aurora)
            [WhatsApp](https://wa.me/5511999999999)
            [TikTok](https://www.tiktok.com/@loja-aurora)
            [Site](https://business.example.test/)
            [Outro site](https://second.example.test/)
            MARKDOWN,
        $businessWebsite => '# Loja Aurora\n\nInformações sobre a empresa e seus serviços.',
        $aboutUrl => '# Sobre a Loja Aurora\n\nNossa equipe atende clientes em todo o Brasil.',
    ];

    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/map' => Http::response([
            'success' => true,
            'links' => [
                ['url' => $businessWebsite],
                ['url' => $aboutUrl],
            ],
        ]),
        'https://firecrawl.example.test/v2/scrape' => function (Request $request) use ($markdownByUrl) {
            $markdown = $markdownByUrl[$request['url']] ?? null;

            return is_string($markdown)
                ? Http::response(['success' => true, 'data' => ['markdown' => $markdown]])
                : Http::response(['success' => false], 503);
        },
    ]);
    enableSiteReadFakes([$aboutUrl]);

    $this->actingAs($user)
        ->post(route('company.site-read.store'))
        ->assertRedirect(route('company.edit'));

    $siteRead = SiteRead::withoutGlobalScopes()->firstOrFail();
    $pages = $siteRead->pages()->orderBy('url')->get();

    expect($siteRead->status)->toBe(SiteReadStatus::Completed)
        ->and($siteRead->website)->toBe($linktreeUrl)
        ->and($siteRead->resolved_website)->toBe($businessWebsite)
        ->and($pages->pluck('url')->all())->toBe([$businessWebsite, $aboutUrl])
        ->and($pages->every(fn (SiteReadPage $page): bool => $page->status === SiteReadPageStatus::Read))->toBeTrue();

    $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('siteRead.latest.website', $linktreeUrl)
        ->where('siteRead.latest.resolvedWebsite', $businessWebsite));

    SelectSitePages::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains($aboutUrl)
        && ! $prompt->contains('instagram.com')
        && ! $prompt->contains('facebook.com')
        && ! $prompt->contains('wa.me')
        && ! $prompt->contains('tiktok.com')
        && ! $prompt->contains('second.example.test'));
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://firecrawl.example.test/v2/map'
        && $request['url'] === $businessWebsite);
    Http::assertSentCount(4);
});

it('fails a Linktree read when it has no own website link', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $linktreeUrl = 'https://linktr.ee/loja-aurora';
    $team->update(['website' => $linktreeUrl]);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
        'queue.default' => 'sync',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::response([
            'success' => true,
            'data' => [
                'markdown' => <<<'MARKDOWN'
                    [Instagram](https://instagram.com/loja-aurora)
                    [Facebook](https://www.facebook.com/loja-aurora)
                    [WhatsApp](https://wa.me/5511999999999)
                    [TikTok](https://www.tiktok.com/@loja-aurora)
                    MARKDOWN,
            ],
        ]),
    ]);
    SelectSitePages::fake()->preventStrayPrompts();
    ExtractSiteFaq::fake()->preventStrayPrompts();
    Embeddings::fake();

    $this->actingAs($user)
        ->post(route('company.site-read.store'))
        ->assertRedirect(route('company.edit'));

    $siteRead = SiteRead::withoutGlobalScopes()->firstOrFail();

    expect($siteRead->status)->toBe(SiteReadStatus::Failed)
        ->and($siteRead->website)->toBe($linktreeUrl)
        ->and($siteRead->resolved_website)->toBeNull()
        ->and($siteRead->error_message)->toBe('O Linktree não contém um link para um site próprio. Informe o site direto ou preencha os dados manualmente.')
        ->and($siteRead->pages()->count())->toBe(0);

    $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('siteRead.latest.website', $linktreeUrl)
        ->where('siteRead.latest.resolvedWebsite', null)
        ->where('siteRead.latest.errorMessage', 'O Linktree não contém um link para um site próprio. Informe o site direto ou preencha os dados manualmente.'));

    SelectSitePages::assertNeverPrompted();
    ExtractSiteFaq::assertNeverPrompted();
    Embeddings::assertNothingGenerated();
    Http::assertSentCount(1);
});

it('creates one FAQ document with original language pairs and no duplicate URL document', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    $faqUrl = 'https://site.example.test/faq';
    $faqMarkdown = "# Frequently asked questions\n\nWhat is your return policy?\n\nYou can return items within 30 days.\n\nHow do I track an order?\n\nUse the tracking link in your email.";
    $faqItems = [
        ['question' => 'What is your return policy?', 'answer' => 'You can return items within 30 days.'],
        ['question' => 'How do I track an order?', 'answer' => 'Use the tracking link in your email.'],
    ];
    fakeSiteReadFirecrawl(
        ['https://site.example.test/', $faqUrl],
        [
            'https://site.example.test/' => '# Home\n\nWelcome to our company website.',
            $faqUrl => $faqMarkdown,
        ],
    );
    enableSiteReadFakes([$faqUrl], $faqUrl, $faqItems);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $siteRead = SiteRead::withoutGlobalScopes()->firstOrFail();
    $faqPage = SiteReadPage::withoutGlobalScopes()->where('site_read_id', $siteRead->getKey())->where('url', $faqUrl)->firstOrFail();
    $faqDocument = Document::withoutGlobalScopes()->where('site_read_page_id', $faqPage->getKey())->firstOrFail();

    expect($siteRead->status)->toBe(SiteReadStatus::Completed)
        ->and($faqDocument->type)->toBe(DocumentType::Faq)
        ->and($faqDocument->status)->toBe(DocumentStatus::Ready)
        ->and($faqDocument->faq_items)->toBe($faqItems)
        ->and($faqDocument->url)->toBe($faqUrl)
        ->and($faqPage->status)->toBe(SiteReadPageStatus::Read)
        ->and(Document::withoutGlobalScopes()->where('site_read_page_id', $faqPage->getKey())->count())->toBe(1)
        ->and(Document::withoutGlobalScopes()->where('url', $faqUrl)->where('type', DocumentType::Url)->exists())->toBeFalse();

    $this->assertDatabaseCount('documents', 2);
    $this->assertDatabaseCount('chunks', 3);
    $this->assertDatabaseHas('chunks', [
        'document_id' => $faqDocument->getKey(),
        'position' => 0,
        'content' => "Pergunta: What is your return policy?\nResposta: You can return items within 30 days.",
    ]);
    $this->assertDatabaseHas('chunks', [
        'document_id' => $faqDocument->getKey(),
        'position' => 1,
        'content' => "Pergunta: How do I track an order?\nResposta: Use the tracking link in your email.",
    ]);

    ExtractSiteFaq::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('What is your return policy?')
        && str_contains($prompt->agent->instructions(), 'dado não confiável'));
    SelectSitePages::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains($faqUrl));
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('You can return items within 30 days.'));
    Http::assertSentCount(3);
});

it('keeps an FAQ page as a URL document when extraction returns no pairs', function () {
    $user = User::factory()->withTeam()->create();
    $user->company()->update(['website' => 'https://site.example.test/']);
    $faqUrl = 'https://site.example.test/faq';
    $faqMarkdown = "# Frequently asked questions\n\nContact our team if you need help.";
    fakeSiteReadFirecrawl(
        ['https://site.example.test/', $faqUrl],
        [
            'https://site.example.test/' => '# Home\n\nWelcome to our company website.',
            $faqUrl => $faqMarkdown,
        ],
    );
    enableSiteReadFakes([$faqUrl], $faqUrl, []);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $faqPage = SiteReadPage::withoutGlobalScopes()->where('url', $faqUrl)->firstOrFail();
    $faqDocument = Document::withoutGlobalScopes()->where('site_read_page_id', $faqPage->getKey())->firstOrFail();

    expect($faqDocument->type)->toBe(DocumentType::Url)
        ->and($faqDocument->status)->toBe(DocumentStatus::Ready)
        ->and($faqDocument->content)->toBe($faqMarkdown)
        ->and($faqDocument->faq_items)->toBeNull()
        ->and($faqPage->status)->toBe(SiteReadPageStatus::Read);

    $this->assertDatabaseCount('documents', 2);
    $this->assertDatabaseHas('chunks', [
        'document_id' => $faqDocument->getKey(),
        'content' => $faqMarkdown,
    ]);
    ExtractSiteFaq::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Contact our team if you need help.'));
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Contact our team if you need help.'));
    Http::assertSentCount(3);
});

it('reads the forced home and selected pages into the site collection', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/start']);
    config(['knowledge.site_read.maximum_pages' => 2]);
    fakeSiteReadFirecrawl(
        [
            'https://site.example.test/',
            'https://site.example.test/sobre',
            'https://site.example.test/contato',
        ],
        [
            'https://site.example.test/' => '# Página inicial\n\nConteúdo institucional da página inicial da Empresa.',
            'https://site.example.test/sobre' => '# Sobre a Empresa\n\nConteúdo institucional sobre a Empresa e os serviços oferecidos.',
        ],
    );
    enableSiteReadFakes([
        'https://site.example.test/sobre',
        'https://site.example.test/contato',
    ]);

    $response = $this->actingAs($user)->post(route('company.site-read.store'));

    $response->assertRedirect(route('company.edit'));
    $siteRead = SiteRead::withoutGlobalScopes()->firstOrFail();
    $siteCollection = Collection::withoutGlobalScopes()
        ->where('team_id', $team->getKey())
        ->where('is_site_collection', true)
        ->firstOrFail();
    $documents = Document::withoutGlobalScopes()
        ->where('collection_id', $siteCollection->getKey())
        ->orderBy('url')
        ->get();

    expect($siteRead->status)->toBe(SiteReadStatus::Completed)
        ->and($siteRead->found_pages_count)->toBe(3)
        ->and($siteRead->pages()->where('status', SiteReadPageStatus::Read)->count())->toBe(2)
        ->and($siteRead->pages()->where('url', 'https://site.example.test/')->exists())->toBeTrue()
        ->and($documents)->toHaveCount(2)
        ->and($documents->every(fn (Document $document): bool => $document->type === DocumentType::Url
            && $document->status === DocumentStatus::Ready
            && $document->site_read_page_id !== null))->toBeTrue()
        ->and($siteCollection->is_site_collection)->toBeTrue();

    Http::assertSentCount(3);
    SelectSitePages::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('URLs disponíveis:')
        && $prompt->contains('https://site.example.test/sobre'));
    ExtractSiteFaq::assertNeverPrompted();
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Conteúdo institucional'));

    $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/company')
        ->where('siteRead.latest.status', 'completed')
        ->where('siteRead.latest.foundPagesCount', 3)
        ->where('siteRead.latest.readPagesCount', 2)
        ->where('siteRead.latest.failedPagesCount', 0)
        ->has('siteRead.latest.pages', 2));
});

it('offers a completed site Collection to existing Agents without linking it automatically', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    $existingAgent = Agent::factory()->for($team)->create(['name' => 'Lia']);
    fakeSiteReadFirecrawl(
        ['https://site.example.test/'],
        ['https://site.example.test/' => '# Página inicial\n\nConteúdo institucional disponível para o atendimento.'],
    );
    enableSiteReadFakes(['https://site.example.test/']);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $siteCollection = Collection::query()->where('is_site_collection', true)->firstOrFail();
    $siteRead = SiteRead::query()->firstOrFail();

    expect($existingAgent->fresh()->collections()->whereKey($siteCollection->id)->exists())->toBeFalse();

    $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('siteRead.latest.id', $siteRead->id)
        ->has('siteRead.latest.linkableAgents', 1)
        ->where('siteRead.latest.linkableAgents.0.id', $existingAgent->id)
        ->where('siteRead.latest.linkableAgents.0.name', 'Lia'),
    );
});

it('refuses Instagram WhatsApp and invalid schemes before any outbound request', function (string $website, string $expectedMessage) {
    $user = User::factory()->withTeam()->create();
    $user->company()->update(['website' => $website]);
    Http::preventStrayRequests();
    Http::fake([]);
    SelectSitePages::fake()->preventStrayPrompts();
    Embeddings::fake();

    $this->actingAs($user)
        ->from(route('company.edit'))
        ->post(route('company.site-read.store'))
        ->assertSessionHasErrors(['website' => $expectedMessage]);

    expect(SiteRead::withoutGlobalScopes()->count())->toBe(0);
    Http::assertSentCount(0);
    SelectSitePages::assertNeverPrompted();
    Embeddings::assertNothingGenerated();
})->with([
    'Instagram' => [
        'https://instagram.com/minha-empresa',
        'Instagram e endereços de WhatsApp não podem ser lidos. Informe o site da Empresa ou preencha os dados manualmente.',
    ],
    'WhatsApp short link' => [
        'https://wa.me/5511999999999',
        'Instagram e endereços de WhatsApp não podem ser lidos. Informe o site da Empresa ou preencha os dados manualmente.',
    ],
    'WhatsApp API' => [
        'https://api.whatsapp.com/send?phone=5511999999999',
        'Instagram e endereços de WhatsApp não podem ser lidos. Informe o site da Empresa ou preencha os dados manualmente.',
    ],
    'FTP' => [
        'ftp://site.example.test',
        'Informe um site válido começando por http:// ou https://.',
    ],
]);

it('refuses a second read while one is in progress and states when it can be retried', function () {
    $this->travelTo('2026-01-01 10:00:00');
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/', 'timezone' => 'UTC']);
    SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'website' => $team->website,
        'status' => SiteReadStatus::InProgress,
        'started_at' => now(),
    ]);
    Http::preventStrayRequests();
    Http::fake([]);
    SelectSitePages::fake()->preventStrayPrompts();

    $this->actingAs($user)
        ->from(route('company.edit'))
        ->post(route('company.site-read.store'))
        ->assertSessionHasErrors([
            'site_read' => 'Já existe uma Leitura do site em andamento. Aguarde a conclusão; será possível iniciar outra em 02/01/2026 às 10:00.',
        ]);

    expect(SiteRead::withoutGlobalScopes()->count())->toBe(1);
    Http::assertSentCount(0);
    SelectSitePages::assertNeverPrompted();
});

it('refuses a read during the twenty-four-hour cooldown', function () {
    $this->travelTo('2026-01-01 10:00:00');
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/', 'timezone' => 'UTC']);
    SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'website' => $team->website,
        'status' => SiteReadStatus::Failed,
        'started_at' => now()->subHours(23),
        'completed_at' => now()->subHours(22),
        'error_message' => 'Falha anterior.',
    ]);
    Http::preventStrayRequests();
    Http::fake([]);

    $this->actingAs($user)
        ->from(route('company.edit'))
        ->post(route('company.site-read.store'))
        ->assertSessionHasErrors([
            'site_read' => 'Uma nova Leitura poderá ser iniciada em 01/01/2026 às 11:00.',
        ]);

    expect(SiteRead::withoutGlobalScopes()->count())->toBe(1);
    Http::assertSentCount(0);
});

it('completes a partial read and keeps failed pages as failed URL documents', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    fakeSiteReadFirecrawl(
        ['https://site.example.test/', 'https://site.example.test/politica'],
        ['https://site.example.test/' => '# Página inicial\n\nConteúdo principal do site disponível para o atendimento.'],
        ['https://site.example.test/politica'],
    );
    enableSiteReadFakes(['https://site.example.test/politica']);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $siteRead = SiteRead::withoutGlobalScopes()->firstOrFail();
    $failedPage = SiteReadPage::withoutGlobalScopes()
        ->where('site_read_id', $siteRead->getKey())
        ->where('status', SiteReadPageStatus::Failed)
        ->firstOrFail();
    $failedDocument = Document::withoutGlobalScopes()->where('site_read_page_id', $failedPage->getKey())->firstOrFail();

    expect($siteRead->status)->toBe(SiteReadStatus::Completed)
        ->and($siteRead->pages()->where('status', SiteReadPageStatus::Read)->count())->toBe(1)
        ->and($siteRead->pages()->where('status', SiteReadPageStatus::Failed)->count())->toBe(1)
        ->and($failedDocument->status)->toBe(DocumentStatus::Failed)
        ->and($failedDocument->error_message)->toBe('A página retornou um erro HTTP (404).');

    Http::assertSentCount(4);
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Conteúdo principal do site'));
});

it('marks every page as failed when the storage quota is exceeded', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    config(['knowledge.storage_quota_bytes' => 20]);
    fakeSiteReadFirecrawl(
        ['https://site.example.test/'],
        ['https://site.example.test/' => '# Página inicial\n\nEste conteúdo excede a cota de armazenamento configurada para a Empresa.'],
    );
    enableSiteReadFakes([]);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $siteRead = SiteRead::withoutGlobalScopes()->firstOrFail();
    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($siteRead->status)->toBe(SiteReadStatus::Failed)
        ->and($siteRead->error_message)->toBe('O conteúdo ultrapassa a cota de armazenamento de 500 MB da Empresa.')
        ->and($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe($siteRead->error_message);

    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('excede a cota de armazenamento'));
});

it('marks a read as failed with a friendly reason when every selected page is unavailable', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    fakeSiteReadFirecrawl(
        ['https://site.example.test/'],
        [],
        ['https://site.example.test/'],
    );
    enableSiteReadFakes([]);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $siteRead = SiteRead::withoutGlobalScopes()->firstOrFail();
    $page = SiteReadPage::withoutGlobalScopes()->firstOrFail();
    $document = Document::withoutGlobalScopes()->firstOrFail();

    expect($siteRead->status)->toBe(SiteReadStatus::Failed)
        ->and($siteRead->error_message)->toBe('A página retornou um erro HTTP (404).')
        ->and($siteRead->pages()->count())->toBe(1)
        ->and($page->status)->toBe(SiteReadPageStatus::Failed)
        ->and($page->error_message)->toBe($siteRead->error_message)
        ->and($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->error_message)->toBe($siteRead->error_message);

    Embeddings::assertNothingGenerated();
});

it('reprocesses retained pages in place and removes pages no longer selected', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    config(['knowledge.site_read.maximum_pages' => 2]);
    $homeUrl = 'https://site.example.test/';
    $aboutUrl = 'https://site.example.test/sobre';
    $contactUrl = 'https://site.example.test/contato';
    $oldHomeContent = '# Página inicial\n\nConteúdo anterior da Empresa.';
    $newHomeContent = '# Página inicial\n\nConteúdo atualizado da Empresa.';
    $mappedUrls = [$homeUrl, $aboutUrl, $contactUrl];
    $markdownByUrl = [
        $homeUrl => $oldHomeContent,
        $aboutUrl => '# Sobre\n\nPágina que deixará de ser escolhida.',
    ];
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/map' => function (Request $request) use (&$mappedUrls) {
            return Http::response([
                'success' => true,
                'links' => array_map(static fn (string $url): array => ['url' => $url], $mappedUrls),
            ]);
        },
        'https://firecrawl.example.test/v2/scrape' => function (Request $request) use (&$markdownByUrl) {
            $markdown = $markdownByUrl[$request['url']] ?? null;

            return is_string($markdown)
                ? Http::response(['success' => true, 'data' => ['markdown' => $markdown]])
                : Http::response(['success' => false], 503);
        },
        'https://site.example.test/*' => Http::response('Página sem conteúdo principal', 200),
    ]);
    SelectSitePages::fake([
        ['urls' => [$aboutUrl]],
        ['urls' => [$contactUrl]],
    ])->preventStrayPrompts();
    Embeddings::fake();
    config(['queue.default' => 'sync']);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));
    $collection = Collection::withoutGlobalScopes()->where('team_id', $team->getKey())
        ->where('is_site_collection', true)
        ->firstOrFail();
    $originalHomeDocument = Document::withoutGlobalScopes()
        ->where('collection_id', $collection->getKey())
        ->where('url', $homeUrl)
        ->firstOrFail();
    $removedAboutDocument = Document::withoutGlobalScopes()
        ->where('collection_id', $collection->getKey())
        ->where('url', $aboutUrl)
        ->firstOrFail();
    $this->travelTo(now()->addHours(25));
    $markdownByUrl = [
        $homeUrl => $newHomeContent,
        $contactUrl => '# Contato\n\nFale com nossa equipe.',
    ];

    $this->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $currentSiteRead = SiteRead::withoutGlobalScopes()->latest('started_at')->firstOrFail();
    $currentDocuments = Document::withoutGlobalScopes()
        ->where('collection_id', $collection->getKey())
        ->whereNotNull('site_read_page_id')
        ->orderBy('url')
        ->get();
    $updatedHomeDocument = $originalHomeDocument->fresh();
    $chunkContent = Chunk::withoutGlobalScopes()
        ->where('document_id', $updatedHomeDocument->getKey())
        ->pluck('content')
        ->implode("\n");

    expect($currentDocuments->pluck('url')->all())->toBe([$homeUrl, $contactUrl])
        ->and($updatedHomeDocument->getKey())->toBe($originalHomeDocument->getKey())
        ->and($updatedHomeDocument->content)->toBe($newHomeContent)
        ->and($currentSiteRead->pages()->where('url', $homeUrl)->value('id'))->toBe($updatedHomeDocument->site_read_page_id)
        ->and($currentDocuments->firstWhere('url', $contactUrl)->status)->toBe(DocumentStatus::Ready)
        ->and($currentDocuments->firstWhere('url', $contactUrl)->content)->toBe('# Contato\n\nFale com nossa equipe.')
        ->and($collection->ignoredSiteUrls()->exists())->toBeFalse()
        ->and($chunkContent)->toContain('Conteúdo atualizado da Empresa.')
        ->and($chunkContent)->not->toContain('Conteúdo anterior da Empresa.');

    $this->assertModelMissing($removedAboutDocument);
    $this->assertDatabaseCount('site_reads', 2);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://firecrawl.example.test/v2/scrape'
        && $request['url'] === $homeUrl);
});

it('reprocesses completed page documents when a site read job is retried', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $homeUrl = 'https://site.example.test/';
    $team->update(['website' => $homeUrl]);
    fakeSiteReadFirecrawl(
        [$homeUrl],
        [$homeUrl => '# Página inicial\n\nConteúdo do site.'],
    );
    SelectSitePages::fake([
        ['urls' => []],
        ['urls' => []],
    ])->preventStrayPrompts();
    Embeddings::fake();
    config(['queue.default' => 'sync']);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));
    $siteRead = SiteRead::withoutGlobalScopes()->firstOrFail();
    $page = SiteReadPage::withoutGlobalScopes()->firstOrFail();
    $document = Document::withoutGlobalScopes()->where('site_read_page_id', $page->getKey())->firstOrFail();
    $siteRead->update([
        'status' => SiteReadStatus::InProgress,
        'completed_at' => null,
    ]);

    ReadSite::dispatchSync($siteRead->getKey());

    expect($siteRead->fresh()->status)->toBe(SiteReadStatus::Completed)
        ->and($page->fresh()->status)->toBe(SiteReadPageStatus::Read)
        ->and($document->fresh()->status)->toBe(DocumentStatus::Ready)
        ->and($document->fresh()->getKey())->toBe($document->getKey())
        ->and($siteRead->pages()->count())->toBe(1)
        ->and($document->collection()->first()->documents()->where('site_read_page_id', $page->getKey())->count())->toBe(1);
});

it('leaves manually added documents untouched while reconciling site pages', function () {
    $this->travelTo('2026-01-01 10:00:00');
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    config(['knowledge.site_read.maximum_pages' => 2]);
    $homeUrl = 'https://site.example.test/';
    $aboutUrl = 'https://site.example.test/sobre';
    fakeSiteReadFirecrawl(
        [$homeUrl, $aboutUrl],
        [
            $homeUrl => '# Página inicial\n\nConteúdo do site.',
            $aboutUrl => '# Sobre\n\nConteúdo antigo do site.',
        ],
    );
    SelectSitePages::fake([
        ['urls' => [$aboutUrl]],
        ['urls' => []],
    ])->preventStrayPrompts();
    Embeddings::fake();
    config(['queue.default' => 'sync']);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));
    $collection = Collection::withoutGlobalScopes()->where('team_id', $team->getKey())
        ->where('is_site_collection', true)
        ->firstOrFail();
    $siteAboutDocument = Document::withoutGlobalScopes()
        ->where('collection_id', $collection->getKey())
        ->where('url', $aboutUrl)
        ->whereNotNull('site_read_page_id')
        ->firstOrFail();
    $manualHomeDocument = Document::factory()->for($collection)->create([
        'type' => DocumentType::Url,
        'status' => DocumentStatus::Ready,
        'title' => 'URL manual igual à página inicial',
        'content' => 'Texto manual da página inicial.',
        'url' => $homeUrl,
    ]);
    $manualAboutDocument = Document::factory()->for($collection)->create([
        'type' => DocumentType::Url,
        'status' => DocumentStatus::Ready,
        'title' => 'URL manual igual à página sobre',
        'content' => 'Texto manual da página sobre.',
        'url' => $aboutUrl,
    ]);
    $manualHomeUpdatedAt = $manualHomeDocument->updated_at;
    $manualAboutUpdatedAt = $manualAboutDocument->updated_at;
    $this->travelTo(now()->addHours(25));
    fakeSiteReadFirecrawl(
        [$homeUrl, $aboutUrl],
        [$homeUrl => '# Página inicial\n\nConteúdo atualizado do site.'],
    );

    $this->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    expect($manualHomeDocument->fresh()->content)->toBe('Texto manual da página inicial.')
        ->and($manualHomeDocument->fresh()->updated_at->equalTo($manualHomeUpdatedAt))->toBeTrue()
        ->and($manualHomeDocument->fresh()->site_read_page_id)->toBeNull()
        ->and($manualAboutDocument->fresh()->content)->toBe('Texto manual da página sobre.')
        ->and($manualAboutDocument->fresh()->updated_at->equalTo($manualAboutUpdatedAt))->toBeTrue()
        ->and($manualAboutDocument->fresh()->site_read_page_id)->toBeNull()
        ->and($collection->ignoredSiteUrls()->exists())->toBeFalse();

    $this->assertModelExists($manualHomeDocument);
    $this->assertModelExists($manualAboutDocument);
    $this->assertModelMissing($siteAboutDocument);
});

it('keeps a manually deleted site URL ignored during later reads', function () {
    $this->travelTo('2026-01-01 10:00:00');
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    config(['knowledge.site_read.maximum_pages' => 2]);
    $homeUrl = 'https://site.example.test/';
    $aboutUrl = 'https://site.example.test/sobre';
    fakeSiteReadFirecrawl(
        [$homeUrl, $aboutUrl],
        [
            $homeUrl => '# Página inicial\n\nConteúdo principal do site.',
            $aboutUrl => '# Sobre\n\nConteúdo institucional.',
        ],
    );
    SelectSitePages::fake([
        ['urls' => [$aboutUrl]],
        ['urls' => [$aboutUrl]],
    ])->preventStrayPrompts();
    Embeddings::fake();
    config(['queue.default' => 'sync']);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));
    $collection = Collection::withoutGlobalScopes()->where('team_id', $team->getKey())
        ->where('is_site_collection', true)
        ->firstOrFail();
    $aboutDocument = Document::withoutGlobalScopes()
        ->where('collection_id', $collection->getKey())
        ->where('url', $aboutUrl)
        ->firstOrFail();

    $this->delete(route('documents.destroy', $aboutDocument))
        ->assertRedirect(route('documents.index', $collection));
    $this->travelTo(now()->addHours(25));
    fakeSiteReadFirecrawl(
        [$homeUrl, $aboutUrl],
        [$homeUrl => '# Página inicial\n\nConteúdo principal atualizado.'],
    );

    $this->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $latestSiteRead = SiteRead::withoutGlobalScopes()->latest('started_at')->firstOrFail();
    $siteDocuments = Document::withoutGlobalScopes()
        ->where('collection_id', $collection->getKey())
        ->whereNotNull('site_read_page_id')
        ->get();

    expect($collection->ignoredSiteUrls()->where('url', $aboutUrl)->exists())->toBeTrue()
        ->and($latestSiteRead->pages()->pluck('url')->all())->toBe([$homeUrl])
        ->and($siteDocuments->pluck('url')->all())->toBe([$homeUrl]);

    $this->assertModelMissing($aboutDocument);
});

it('recreates the site collection when the team deleted it', function () {
    $this->travelTo('2026-01-01 10:00:00');
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $team->update(['website' => 'https://site.example.test/']);
    config(['knowledge.site_read.maximum_pages' => 1]);
    $homeUrl = 'https://site.example.test/';
    fakeSiteReadFirecrawl(
        [$homeUrl],
        [$homeUrl => '# Página inicial\n\nConteúdo institucional.'],
    );
    SelectSitePages::fake([
        ['urls' => []],
        ['urls' => []],
    ])->preventStrayPrompts();
    Embeddings::fake();
    config(['queue.default' => 'sync']);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));
    $originalCollection = Collection::withoutGlobalScopes()->where('team_id', $team->getKey())
        ->where('is_site_collection', true)
        ->firstOrFail();
    $originalCollectionId = $originalCollection->getKey();
    $this->delete(route('collections.destroy', $originalCollection))
        ->assertRedirect(route('collections.index'));
    $this->travelTo(now()->addHours(25));
    fakeSiteReadFirecrawl(
        [$homeUrl],
        [$homeUrl => '# Página inicial\n\nConteúdo institucional atualizado.'],
    );

    $this->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $recreatedCollection = Collection::withoutGlobalScopes()->where('team_id', $team->getKey())
        ->where('is_site_collection', true)
        ->firstOrFail();

    expect($recreatedCollection->getKey())->not->toBe($originalCollectionId)
        ->and($recreatedCollection->documents()->where('url', $homeUrl)->exists())->toBeTrue()
        ->and($recreatedCollection->ignoredSiteUrls()->exists())->toBeFalse()
        ->and(Collection::withoutGlobalScopes()->where('team_id', $team->getKey())->where('is_site_collection', true)->count())->toBe(1);
});

it('offers an explicit read when the saved website changes and removes the old domain after the next read', function () {
    $this->travelTo('2026-01-01 10:00:00');
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $oldHomeUrl = 'https://old.example.test/';
    $oldAboutUrl = 'https://old.example.test/sobre';
    $newHomeUrl = 'https://new.example.test/';
    $newContactUrl = 'https://new.example.test/contato';
    $team->update(['website' => $oldHomeUrl, 'timezone' => 'UTC']);
    config(['knowledge.site_read.maximum_pages' => 2]);
    $mappedUrls = [$oldHomeUrl, $oldAboutUrl];
    $markdownByUrl = [
        $oldHomeUrl => '# Site antigo\n\nConteúdo antigo.',
        $oldAboutUrl => '# Sobre\n\nConteúdo institucional antigo.',
    ];
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/map' => function (Request $request) use (&$mappedUrls) {
            return Http::response([
                'success' => true,
                'links' => array_map(static fn (string $url): array => ['url' => $url], $mappedUrls),
            ]);
        },
        'https://firecrawl.example.test/v2/scrape' => function (Request $request) use (&$markdownByUrl) {
            $markdown = $markdownByUrl[$request['url']] ?? null;

            return is_string($markdown)
                ? Http::response(['success' => true, 'data' => ['markdown' => $markdown]])
                : Http::response(['success' => false], 503);
        },
        'https://old.example.test/*' => Http::response('Página antiga sem conteúdo principal', 200),
        'https://new.example.test/*' => Http::response('Página nova sem conteúdo principal', 200),
    ]);
    SelectSitePages::fake([
        ['urls' => [$oldAboutUrl]],
        ['urls' => [$newContactUrl]],
    ])->preventStrayPrompts();
    Embeddings::fake();
    config(['queue.default' => 'sync']);

    $this->actingAs($user)->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));
    $collection = Collection::withoutGlobalScopes()->where('team_id', $team->getKey())
        ->where('is_site_collection', true)
        ->firstOrFail();
    $this->patch(route('company.update'), ['website' => $newHomeUrl])
        ->assertRedirect(route('company.edit'));

    $this->assertDatabaseCount('site_reads', 1);
    $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('siteRead.website', $newHomeUrl)
        ->where('siteRead.hasNewWebsite', true)
        ->where('siteRead.canStart', false)
        ->where('siteRead.latest.website', $oldHomeUrl)
        ->where('siteRead.availableAgainAt', '02/01/2026 às 10:00'));

    $this->travelTo(now()->addHours(25));

    $this->get(route('company.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('siteRead.hasNewWebsite', true)
        ->where('siteRead.canStart', true));

    $mappedUrls = [$newHomeUrl, $newContactUrl];
    $markdownByUrl = [
        $newHomeUrl => '# Site novo\n\nConteúdo atualizado.',
        $newContactUrl => '# Contato\n\nFale com a equipe.',
    ];

    $this->post(route('company.site-read.store'))->assertRedirect(route('company.edit'));

    $siteDocuments = Document::withoutGlobalScopes()
        ->where('collection_id', $collection->getKey())
        ->whereNotNull('site_read_page_id')
        ->orderBy('url')
        ->get();

    expect($siteDocuments->pluck('url')->all())->toBe([$newHomeUrl, $newContactUrl])
        ->and(SiteRead::withoutGlobalScopes()->count())->toBe(2);
});

it('forbids Members from starting a read and does not expose another company history', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $team->update(['website' => 'https://site.example.test/']);
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    Http::preventStrayRequests();
    Http::fake([]);
    SelectSitePages::fake()->preventStrayPrompts();

    $this->actingAs($member)
        ->from(route('company.edit'))
        ->post(route('company.site-read.store'))
        ->assertForbidden();

    expect(SiteRead::withoutGlobalScopes()->count())->toBe(0);
    Http::assertSentCount(0);
    SelectSitePages::assertNeverPrompted();

    $firstRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $team->getKey(),
        'website' => 'https://private.example.test/',
        'status' => SiteReadStatus::Failed,
        'started_at' => now()->subDays(2),
        'completed_at' => now()->subDays(2),
        'error_message' => 'Falha privada da primeira Empresa.',
    ]);
    $secondOwner = User::factory()->withTeam()->create();
    $secondCompany = $secondOwner->company();
    $secondCompany->update(['website' => 'https://other.example.test/']);

    $this->actingAs($secondOwner)
        ->get(route('company.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('siteRead.latest', null)
            ->where('siteRead.website', 'https://other.example.test/'))
        ->assertDontSee('private.example.test');

    expect(SiteRead::withoutGlobalScopes()->whereKey($firstRead->getKey())->exists())->toBeTrue();
});
