<?php

use App\Enums\SiteReadStatus;
use App\Knowledge\PageReader;
use App\Knowledge\SiteReadUsage;
use App\Models\SiteRead;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('returns the Firecrawl site map when configured', function () {
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/map' => Http::response([
            'success' => true,
            'links' => [
                ['url' => 'https://docs.example.test/'],
                ['url' => 'https://docs.example.test/about'],
                ['url' => 'https://outside.example.test/about'],
            ],
        ]),
    ]);

    $urls = app(PageReader::class)->mapUrls('https://docs.example.test/');

    expect($urls)->toBe([
        'https://docs.example.test/',
        'https://docs.example.test/about',
    ]);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://firecrawl.example.test/v2/map'
        && $request->hasHeader('Authorization', 'Bearer test-firecrawl-key')
        && $request['url'] === 'https://docs.example.test/');
    Http::assertSentCount(1);
});

it('maps HTTP links from the fallback page without a Firecrawl key', function () {
    config(['services.firecrawl.key' => null]);
    Http::preventStrayRequests();
    Http::fake([
        'https://docs.example.test/company/index.html' => Http::response(<<<'HTML'
            <html>
                <body>
                    <a href="/about">About</a>
                    <a href="../services?from=menu">Services</a>
                    <a href="https://outside.example.test/contact#form">Contact</a>
                    <a href="//cdn.example.test/page">CDN</a>
                    <a href="mailto:hello@example.test">Email</a>
                    <a href="#faq">FAQ</a>
                </body>
            </html>
            HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
    ]);

    $urls = app(PageReader::class)->mapUrls('https://docs.example.test/company/index.html');

    expect($urls)->toBe([
        'https://docs.example.test/about',
        'https://docs.example.test/services?from=menu',
    ]);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://docs.example.test/company/index.html');
    Http::assertSentCount(1);
});

it('falls back to page links when Firecrawl returns an empty site map', function () {
    $user = User::factory()->withTeam()->create();
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $user->company()->getKey(),
        'website' => 'https://docs.example.test/',
        'status' => SiteReadStatus::InProgress,
        'started_at' => now(),
        'firecrawl_credits_used' => 0,
    ]);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/map' => Http::response([
            'success' => true,
            'links' => [],
        ]),
        'https://docs.example.test/' => Http::response('<a href="/about">About</a>', 200),
    ]);

    $urls = app(PageReader::class)->mapUrls('https://docs.example.test/', $siteRead->getKey());

    expect($urls)->toBe(['https://docs.example.test/about'])
        ->and($siteRead->fresh()->firecrawl_credits_used)->toBe(1);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://firecrawl.example.test/v2/map');
    Http::assertSentCount(2);
});

it('counts a PDF scrape base credit and each parsed PDF page', function () {
    $user = User::factory()->withTeam()->create();
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $user->company()->getKey(),
        'website' => 'https://docs.example.test/handbook.pdf',
        'status' => SiteReadStatus::InProgress,
        'started_at' => now(),
        'firecrawl_credits_used' => 0,
    ]);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::response([
            'success' => true,
            'data' => [
                'markdown' => '# Handbook',
                'pages' => [
                    ['pageNumber' => 1, 'markdown' => '# Handbook'],
                    ['pageNumber' => 2, 'markdown' => 'Policies'],
                    ['pageNumber' => 3, 'markdown' => 'Contact'],
                ],
                'metadata' => [
                    'contentType' => 'application/pdf',
                    'totalPages' => 4,
                ],
            ],
        ]),
    ]);

    $markdown = app(PageReader::class)->readMarkdown('https://docs.example.test/handbook.pdf', $siteRead->getKey());

    expect($markdown)->toBe('# Handbook')
        ->and($siteRead->fresh()->firecrawl_credits_used)->toBe(4);
});

it('uses Firecrawl PDF page metadata when parsed pages are not returned', function () {
    $user = User::factory()->withTeam()->create();
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $user->company()->getKey(),
        'website' => 'https://docs.example.test/handbook.pdf',
        'status' => SiteReadStatus::InProgress,
        'started_at' => now(),
        'firecrawl_credits_used' => 0,
    ]);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::response([
            'success' => true,
            'data' => [
                'markdown' => '# Handbook',
                'metadata' => [
                    'contentType' => 'application/pdf',
                    'numPages' => 3,
                    'totalPages' => 4,
                ],
            ],
        ]),
    ]);

    app(PageReader::class)->readMarkdown('https://docs.example.test/handbook.pdf', $siteRead->getKey());

    expect($siteRead->fresh()->firecrawl_credits_used)->toBe(4);
});

it('counts only the base scrape credit for non-PDF responses', function () {
    $user = User::factory()->withTeam()->create();
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $user->company()->getKey(),
        'website' => 'https://docs.example.test/handbook',
        'status' => SiteReadStatus::InProgress,
        'started_at' => now(),
        'firecrawl_credits_used' => 0,
    ]);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::response([
            'success' => true,
            'data' => [
                'markdown' => '# Handbook',
                'metadata' => [
                    'contentType' => 'text/html; charset=utf-8',
                    'numPages' => 4,
                    'totalPages' => 4,
                ],
            ],
        ]),
    ]);

    $markdown = app(PageReader::class)->readMarkdown('https://docs.example.test/handbook', $siteRead->getKey());

    expect($markdown)->toBe('# Handbook')
        ->and($siteRead->fresh()->firecrawl_credits_used)->toBe(1);
});

it('marks PDF credit usage unknown when Firecrawl omits its page count', function () {
    $user = User::factory()->withTeam()->create();
    $siteRead = SiteRead::withoutGlobalScopes()->create([
        'team_id' => $user->company()->getKey(),
        'website' => 'https://docs.example.test/handbook.pdf',
        'status' => SiteReadStatus::InProgress,
        'started_at' => now(),
        'firecrawl_credits_used' => 0,
    ]);
    config([
        'services.firecrawl.key' => 'test-firecrawl-key',
        'services.firecrawl.base_url' => 'https://firecrawl.example.test/v2',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://firecrawl.example.test/v2/scrape' => Http::response([
            'success' => true,
            'data' => [
                'markdown' => '# Handbook',
                'metadata' => ['contentType' => 'application/pdf'],
            ],
        ]),
    ]);

    app(PageReader::class)->readMarkdown('https://docs.example.test/handbook.pdf', $siteRead->getKey());
    app(SiteReadUsage::class)->recordFirecrawlCredits($siteRead->getKey(), 1);

    expect($siteRead->fresh()->firecrawl_credits_used)->toBeNull();
});
