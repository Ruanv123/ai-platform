<?php

use App\Enums\SiteReadStatus;
use App\Knowledge\PageReader;
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
