<?php

use App\Enums\TeamRole;
use App\Jobs\RegisterShopifyCatalogWebhooks;
use App\Jobs\StartShopifyCatalogImport;
use App\Models\Agent;
use App\Models\Category;
use App\Models\Integration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\ShopifyPendingInstallation;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'services.shopify.client_id' => 'shopify-client-id',
        'services.shopify.client_secret' => 'shopify-client-secret',
        'services.shopify.scopes' => ['read_products', 'read_inventory', 'read_customers', 'read_orders'],
        'services.shopify.api_version' => '2026-07',
    ]);
});

it('renders the embedded App Bridge shell with a bootstrap frame policy', function () {
    $this->withoutVite()
        ->get(route('shopify.embedded.bootstrap'))
        ->assertOk()
        ->assertSee('cdn.shopify.com/shopifycloud/app-bridge.js')
        ->assertHeader('Content-Security-Policy', 'frame-ancestors https://admin.shopify.com https://*.myshopify.com;')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('validates a fresh embedded ID token and stores only encrypted credentials plus a handoff hash', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/oauth/access_token' => Http::response([
            'access_token' => 'short-lived-access-token',
            'refresh_token' => 'rotating-refresh-token',
            'expires_in' => 3600,
            'refresh_token_expires_in' => 86400,
            'scope' => 'read_products,read_inventory,read_customers,read_orders',
        ]),
        'https://alpha.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'shop' => [
                    'name' => 'Alpha Store',
                    'myshopifyDomain' => 'alpha.myshopify.com',
                    'currencyCode' => 'BRL',
                    'ianaTimezone' => 'America/Sao_Paulo',
                    'shopAddress' => ['countryCodeV2' => 'BR'],
                ],
            ],
        ]),
    ]);

    $idToken = shopifyEmbeddedIdToken();
    $response = $this->postJson(route('shopify.embedded.authenticate'), [], [
        'Authorization' => 'Bearer '.$idToken,
    ])->assertOk();

    $homeUrl = $response->json('homeUrl');
    $handoffToken = parse_url($homeUrl, PHP_URL_FRAGMENT);
    parse_str(is_string($handoffToken) ? $handoffToken : '', $fragment);
    $pending = ShopifyPendingInstallation::query()->where('domain', 'alpha.myshopify.com')->sole();
    $storedCredentials = DB::table('shopify_pending_installations')->where('id', $pending->getKey())->value('credentials');

    expect($homeUrl)->toContain('signature=')
        ->and($fragment['handoff'] ?? null)->toBeString()
        ->and($pending->credentials['access_token'])->toBe('short-lived-access-token')
        ->and($pending->credentials['refresh_token'])->toBe('rotating-refresh-token')
        ->and($pending->handoff_token_hash)->toBe(hash('sha256', $fragment['handoff']))
        ->and($pending->isActive())->toBeTrue()
        ->and($storedCredentials)->not->toContain('short-lived-access-token')
        ->and($storedCredentials)->not->toContain($idToken)
        ->and($pending->handoff_token_hash)->not->toBe($idToken);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://alpha.myshopify.com/admin/oauth/access_token'
        && $request->data()['grant_type'] === 'urn:ietf:params:oauth:grant-type:token-exchange'
        && $request->data()['subject_token'] === $idToken
        && $request->data()['requested_token_type'] === 'urn:shopify:params:oauth:token-type:offline-access-token'
        && $request->data()['expiring'] === '1');

    $this->withoutVite()
        ->get(explode('#', $homeUrl, 2)[0])
        ->assertOk()
        ->assertHeader('Content-Security-Policy', 'frame-ancestors https://alpha.myshopify.com https://admin.shopify.com;')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertInertia(fn (Assert $page) => $page
            ->component('shopify/embedded-home')
            ->where('status', 'pending')
            ->where('shop.domain', 'alpha.myshopify.com'),
        );
});

it('shows linked shop status inside the verified shop frame', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
    ]);
    Shop::factory()->for($team)->for($integration)->create([
        'domain' => 'alpha.myshopify.com',
        'name' => 'Alpha Store',
        'sync_status' => 'synced',
        'sync_progress' => 100,
    ]);
    $url = URL::temporarySignedRoute('shopify.embedded.home', now()->addMinutes(10), [
        'domain' => 'alpha.myshopify.com',
    ]);

    $this->withoutVite()
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Security-Policy', 'frame-ancestors https://alpha.myshopify.com https://admin.shopify.com;')
        ->assertInertia(fn (Assert $page) => $page
            ->component('shopify/embedded-home')
            ->where('status', 'connected')
            ->where('shop.teamName', $team->name)
            ->where('shop.syncStatus', 'synced')
            ->where('platformUrl', route('integrations.show', [
                'type' => 'ecommerce',
                'provider' => 'shopify',
            ])),
        );
});

it('rejects invalid embedded token claims and signatures before calling Shopify', function () {
    Http::preventStrayRequests();
    Http::fake();

    $invalidTokens = [
        shopifyEmbeddedIdToken(['exp' => now()->subMinute()->timestamp]),
        shopifyEmbeddedIdToken(['nbf' => now()->addMinute()->timestamp]),
        shopifyEmbeddedIdToken(['nbf' => null]),
        shopifyEmbeddedIdToken(['aud' => 'another-client']),
        shopifyEmbeddedIdToken(['iss' => 'https://alpha.example.com/admin']),
        shopifyEmbeddedIdToken(['dest' => 'https://beta.myshopify.com']),
        shopifyEmbeddedIdToken([], false),
    ];

    foreach ($invalidTokens as $idToken) {
        $this->postJson(route('shopify.embedded.authenticate'), [], [
            'Authorization' => 'Bearer '.$idToken,
        ])->assertUnauthorized()
            ->assertHeader('X-Shopify-Retry-Invalid-Session-Request', '1');
    }

    Http::assertNothingSent();
    $this->assertDatabaseCount('shopify_pending_installations', 0);
});

it('accepts a handoff URL only once while preserving its hashed context through signup and onboarding', function () {
    $this->withoutVite();
    [$pending, $handoffToken] = createShopifyPendingInstallation('alpha.myshopify.com');
    $pending->forceFill(['handoff_started_at' => null])->save();

    $this->get(route('shopify.install.start', ['handoffToken' => $handoffToken]))
        ->assertRedirect(route('login'))
        ->assertSessionHas('shopify_install.pending.id', $pending->getKey())
        ->assertSessionHas('shopify_install.pending.handoff_hash', hash('sha256', $handoffToken))
        ->assertSessionMissing('shopify_install.pending.handoff_token');

    expect($pending->fresh()->handoff_started_at)->not->toBeNull();

    $this->get(route('shopify.install.start', ['handoffToken' => $handoffToken]))
        ->assertStatus(410);

    $this->post(route('register.store'), [
        'name' => 'Shopify Owner',
        'email' => 'shopify-owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('onboarding.company'));

    $this->assertAuthenticated();
    $this->assertSame(hash('sha256', $handoffToken), session('shopify_install.pending.handoff_hash'));

    $this->post(route('onboarding.company.store'), shopifyInstallationCompanyDetails())
        ->assertRedirect(route('shopify.install.resume'));

    $this->get(route('shopify.install.resume'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shopify/install')
            ->where('shop.domain', 'alpha.myshopify.com')
            ->has('teams', 1)
            ->where('teams.0.role', 'owner'),
        );
});

it('offers only Owner and Admin teams and rejects a Member team submission', function () {
    $this->withoutVite();
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    [$pending, $handoffToken] = createShopifyPendingInstallation('alpha.myshopify.com');

    $this->actingAs($owner)
        ->withSession(shopifyInstallationSession($pending, $handoffToken))
        ->get(route('shopify.install.resume'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shopify/install')
            ->has('teams', 1)
            ->where('teams.0.role', 'owner'),
        );

    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $this->actingAs($admin)
        ->withSession(shopifyInstallationSession($pending, $handoffToken))
        ->get(route('shopify.install.resume'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shopify/install')
            ->has('teams', 1)
            ->where('teams.0.role', 'admin'),
        );

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $this->actingAs($member)
        ->withSession(shopifyInstallationSession($pending, $handoffToken))
        ->post(route('shopify.install.store'), ['team_id' => $team->getKey()])
        ->assertForbidden();
});

it('asks before replacing a shop, clears its mirror, and reuses the soft-deleted rows', function () {
    $this->withoutVite();
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'access_token_expires_at' => now()->addHour()->timestamp,
            'refresh_token_expires_at' => now()->addDay()->timestamp,
        ],
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create([
        'domain' => 'old.myshopify.com',
        'sync_status' => 'synced',
        'sync_progress' => 100,
    ]);
    $product = Product::factory()->for($team)->for($shop)->create();
    ProductVariant::factory()->for($team)->for($shop)->for($product)->create();
    Category::factory()->for($team)->for($shop)->create();
    $agent = Agent::factory()->for($team)->create(['search_products' => true]);
    [$pending, $handoffToken] = createShopifyPendingInstallation('new.myshopify.com');
    Queue::fake([RegisterShopifyCatalogWebhooks::class, StartShopifyCatalogImport::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://old.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => ['appUninstall' => ['userErrors' => []]],
        ]),
    ]);

    $this->actingAs($owner)
        ->withSession(shopifyInstallationSession($pending, $handoffToken))
        ->post(route('shopify.install.store'), ['team_id' => $team->getKey()])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shopify/install-confirm')
            ->where('existingShop.domain', 'old.myshopify.com')
            ->where('shop.domain', 'new.myshopify.com'),
        );

    $this->post(route('shopify.install.store'), [
        'team_id' => $team->getKey(),
        'confirm_replacement' => true,
    ])->assertRedirect(route('integrations.show', [
        'type' => 'ecommerce',
        'provider' => 'shopify',
    ]));

    expect(Shop::query()->where('team_id', $team->getKey())->sole()->getKey())->toBe($shop->getKey())
        ->and($shop->fresh()->domain)->toBe('new.myshopify.com')
        ->and($shop->fresh()->sync_status)->toBe('importing')
        ->and(Integration::query()->where('team_id', $team->getKey())->sole()->getKey())->toBe($integration->getKey())
        ->and($integration->fresh()->credential('access_token'))->toBe('pending-access-token')
        ->and($agent->fresh()->search_products)->toBeFalse()
        ->and(Product::withoutGlobalScopes()->where('shop_id', $shop->getKey())->count())->toBe(0)
        ->and(ProductVariant::withoutGlobalScopes()->where('shop_id', $shop->getKey())->count())->toBe(0)
        ->and(Category::withoutGlobalScopes()->where('shop_id', $shop->getKey())->count())->toBe(0)
        ->and($pending->fresh()->consumed_at)->not->toBeNull()
        ->and($pending->fresh()->handoff_token_hash)->toBeNull();

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://old.myshopify.com/admin/api/2026-07/graphql.json'
        && str_contains($request->data()['query'], 'appUninstall')
        && $request->hasHeader('X-Shopify-Access-Token', 'old-access-token'));
});

it('restores a soft-deleted same-domain shop and restarts its import', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'disconnected',
        'credentials' => [],
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create([
        'domain' => 'alpha.myshopify.com',
        'sync_status' => 'synced',
    ]);
    $shop->delete();
    $integration->delete();
    [$pending, $handoffToken] = createShopifyPendingInstallation('alpha.myshopify.com');
    Queue::fake([RegisterShopifyCatalogWebhooks::class, StartShopifyCatalogImport::class]);

    $this->actingAs($owner)
        ->withSession(shopifyInstallationSession($pending, $handoffToken))
        ->post(route('shopify.install.store'), ['team_id' => $team->getKey()])
        ->assertRedirect(route('integrations.show', [
            'type' => 'ecommerce',
            'provider' => 'shopify',
        ]));

    expect(Shop::query()->where('team_id', $team->getKey())->sole()->getKey())->toBe($shop->getKey())
        ->and($shop->fresh()->sync_status)->toBe('importing')
        ->and(Integration::query()->where('team_id', $team->getKey())->sole()->getKey())->toBe($integration->getKey())
        ->and($integration->fresh()->credential('access_token'))->toBe('pending-access-token')
        ->and($pending->fresh()->consumed_at)->not->toBeNull();
});

it('removes catalog data and credentials after a signed Shopify uninstall', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
        'credentials' => ['access_token' => 'old-access-token', 'refresh_token' => 'old-refresh-token'],
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create(['domain' => 'alpha.myshopify.com']);
    $product = Product::factory()->for($team)->for($shop)->create();
    ProductVariant::factory()->for($team)->for($shop)->for($product)->create();
    Category::factory()->for($team)->for($shop)->create();
    $agent = Agent::factory()->for($team)->create(['search_products' => true]);
    createShopifyPendingInstallation('alpha.myshopify.com');
    $body = '{}';

    $this->call('POST', route('webhooks.shopify'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, 'shopify-client-secret', true)),
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'alpha.myshopify.com',
        'HTTP_X_SHOPIFY_TOPIC' => 'app/uninstalled',
    ], $body)->assertOk();

    $this->assertSoftDeleted('shops', ['id' => $shop->getKey()]);
    $this->assertSoftDeleted('integrations', ['id' => $integration->getKey()]);

    expect(DB::table('products')->where('shop_id', $shop->getKey())->count())->toBe(0)
        ->and(DB::table('product_variants')->where('shop_id', $shop->getKey())->count())->toBe(0)
        ->and(DB::table('categories')->where('shop_id', $shop->getKey())->count())->toBe(0)
        ->and($integration->fresh()->credential('access_token'))->toBeNull()
        ->and($agent->fresh()->search_products)->toBeFalse()
        ->and(ShopifyPendingInstallation::query()->where('domain', 'alpha.myshopify.com')->exists())->toBeFalse();
});

it('returns 200 for the mandatory Shopify compliance webhook topics', function (string $topic) {
    $body = '{}';

    $this->call('POST', route('webhooks.shopify'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, 'shopify-client-secret', true)),
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'unknown.myshopify.com',
        'HTTP_X_SHOPIFY_TOPIC' => $topic,
    ], $body)->assertOk();
})->with([
    'customers/data_request',
    'customers/redact',
    'shop/redact',
]);

function shopifyEmbeddedIdToken(array $overrides = [], bool $validSignature = true): string
{
    $header = shopifyEmbeddedBase64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $claims = shopifyEmbeddedBase64Url(json_encode(array_replace([
        'aud' => 'shopify-client-id',
        'dest' => 'https://alpha.myshopify.com',
        'exp' => now()->addMinutes(5)->timestamp,
        'iss' => 'https://alpha.myshopify.com/admin',
        'nbf' => now()->subMinute()->timestamp,
        'sub' => '12345',
    ], $overrides), JSON_THROW_ON_ERROR));
    $signature = shopifyEmbeddedBase64Url(hash_hmac('sha256', $header.'.'.$claims, 'shopify-client-secret', true));
    if (! $validSignature) {
        $signature[0] = $signature[0] === 'a' ? 'b' : 'a';
    }

    return $header.'.'.$claims.'.'.$signature;
}

function shopifyEmbeddedBase64Url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function createShopifyPendingInstallation(string $domain): array
{
    $handoffToken = Str::random(64);
    $pending = ShopifyPendingInstallation::factory()->create([
        'domain' => $domain,
        'shop_name' => 'Pending Store',
        'credentials' => [
            'access_token' => 'pending-access-token',
            'refresh_token' => 'pending-refresh-token',
            'access_token_expires_at' => now()->addHour()->timestamp,
            'refresh_token_expires_at' => now()->addDay()->timestamp,
            'scopes' => ['read_products', 'read_inventory', 'read_customers', 'read_orders'],
        ],
        'handoff_token_hash' => hash('sha256', $handoffToken),
        'expires_at' => now()->addMinutes(15),
        'handoff_started_at' => now(),
        'consumed_at' => null,
    ]);

    return [$pending, $handoffToken];
}

function shopifyInstallationSession(ShopifyPendingInstallation $pending, string $handoffToken): array
{
    return [
        'shopify_install.pending' => [
            'id' => $pending->getKey(),
            'handoff_hash' => hash('sha256', $handoffToken),
        ],
    ];
}

function shopifyInstallationCompanyDetails(): array
{
    return [
        'name' => 'Shopify Company',
        'segment' => 'tecnologia',
        'business_description' => 'Atendimento automatizado.',
        'products_services' => 'Produtos digitais.',
        'target_audience' => 'Pequenas empresas.',
        'business_hours' => [
            'monday' => ['enabled' => '1', 'open' => '09:00', 'close' => '18:00'],
        ],
        'timezone' => 'America/Sao_Paulo',
    ];
}
