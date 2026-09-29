<?php

use App\Ecommerce\EcommerceAdapter;
use App\Enums\TeamRole;
use App\Jobs\RegisterShopifyCatalogWebhooks;
use App\Jobs\StartShopifyCatalogImport;
use App\Models\Integration;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'services.shopify.client_id' => 'client-id',
        'services.shopify.client_secret' => 'client-secret',
        'services.shopify.redirect' => 'http://localhost/integrations/ecommerce/shopify/callback',
        'services.shopify.scopes' => ['read_products', 'read_inventory', 'read_customers', 'read_orders'],
        'services.shopify.api_version' => '2026-07',
    ]);
});

it('starts a Shopify authorization with read scopes and a state bound to the active team', function () {
    $user = User::factory()->withTeam()->create();

    $response = $this->actingAs($user)->get(route('integrations.shopify.connect', ['shop' => 'alpha.myshopify.com']));

    $response->assertRedirect();
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['client_id'])->toBe('client-id')
        ->and(explode(',', $query['scope']))->toBe(['read_products', 'read_inventory', 'read_customers', 'read_orders'])
        ->and($query['redirect_uri'])->toBe('http://localhost/integrations/ecommerce/shopify/callback');

    $pendingAuthorizations = session()->all()['shopify_oauth']['pending'];
    expect($pendingAuthorizations[$query['state']]['team_id'])->toBe($user->company()->getKey())
        ->and($pendingAuthorizations[$query['state']]['domain'])->toBe('alpha.myshopify.com')
        ->and($pendingAuthorizations[$query['state']]['expires_at'])->toBeGreaterThan(now()->timestamp);
});

it('rejects an invalid shop domain before redirecting to Shopify', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->from(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->get(route('integrations.shopify.connect', ['shop' => 'alpha.example.com']))
        ->assertRedirect(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->assertSessionHasErrors('shop');
});

it('allows an Admin to start a Shopify connection', function () {
    $owner = User::factory()->withTeam()->create();
    $admin = User::factory()->create();
    $team = $owner->company();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);

    $this->actingAs($admin)
        ->get(route('integrations.shopify.connect', ['shop' => 'alpha.myshopify.com']))
        ->assertRedirect();
});

it('completes Shopify OAuth, persists encrypted credentials and reuses the same shop on reconnect', function () {
    $user = User::factory()->withTeam()->create();
    $this->withoutVite();
    Queue::fake([RegisterShopifyCatalogWebhooks::class, StartShopifyCatalogImport::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/oauth/access_token' => Http::response([
            'access_token' => 'temporary-access-token',
            'refresh_token' => 'temporary-refresh-token',
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

    $this->actingAs($user);
    $firstState = startShopifyAuthorization($this, 'alpha.myshopify.com');
    $this->get(route('integrations.shopify.callback', shopifySignedCallback($firstState)))
        ->assertRedirect(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->assertSessionHas('shopify_success', 'Loja Shopify conectada.');

    $integration = Integration::query()->where('team_id', $user->company()->getKey())->firstOrFail();
    $shop = Shop::query()->where('team_id', $user->company()->getKey())->firstOrFail();

    expect($integration->type)->toBe('ecommerce')
        ->and($integration->provider)->toBe('shopify')
        ->and($integration->status)->toBe('connected')
        ->and($integration->credential('access_token'))->toBe('temporary-access-token')
        ->and($integration->credential('refresh_token'))->toBe('temporary-refresh-token')
        ->and($shop->integration_id)->toBe($integration->getKey())
        ->and($shop->domain)->toBe('alpha.myshopify.com')
        ->and($shop->name)->toBe('Alpha Store')
        ->and($shop->currency_code)->toBe('BRL')
        ->and($shop->country_code)->toBe('BR')
        ->and($shop->timezone)->toBe('America/Sao_Paulo')
        ->and($shop->sync_status)->toBe('importing')
        ->and(DB::table('integrations')->where('id', $integration->getKey())->value('credentials'))->not->toContain('temporary-access-token');

    $this->get(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/show')
            ->where('shop.domain', 'alpha.myshopify.com')
            ->where('shop.syncStatus', 'importing')
            ->where('shop.currencyCode', 'BRL'),
        );

    $integration->forceFill([
        'status' => 'error',
        'status_message' => 'A autorização expirou.',
    ])->save();
    $shop->forceFill([
        'sync_status' => 'error',
        'sync_error' => 'A autorização expirou.',
        'suggested_action' => 'Reconecte a Loja pela tela de Integrações.',
    ])->save();

    $secondState = startShopifyAuthorization($this, 'alpha.myshopify.com');
    $this->get(route('integrations.shopify.callback', shopifySignedCallback($secondState)))
        ->assertRedirect(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']));

    expect(Integration::query()->where('team_id', $user->company()->getKey())->count())->toBe(1)
        ->and(Shop::query()->where('team_id', $user->company()->getKey())->count())->toBe(1)
        ->and(Integration::query()->where('team_id', $user->company()->getKey())->value('id'))->toBe($integration->getKey())
        ->and(Shop::query()->where('team_id', $user->company()->getKey())->value('id'))->toBe($shop->getKey())
        ->and($integration->fresh()->status)->toBe('connected')
        ->and($integration->fresh()->status_message)->toBeNull()
        ->and($shop->fresh()->sync_status)->toBe('importing')
        ->and($shop->fresh()->sync_error)->toBeNull()
        ->and($shop->fresh()->suggested_action)->toBeNull();

    Queue::assertPushed(StartShopifyCatalogImport::class, 1);
    Queue::assertPushed(RegisterShopifyCatalogWebhooks::class, fn (RegisterShopifyCatalogWebhooks $job): bool => $job->shopId === $shop->getKey());

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://alpha.myshopify.com/admin/oauth/access_token'
        && $request->data()['expiring'] === '1');
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json'
        && str_contains($request->data()['query'], 'myshopifyDomain'));
});

it('uninstalls the previous store and clears its mirror when OAuth connects a replacement', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
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
    $oldShop = Shop::factory()->for($team)->for($integration)->create([
        'domain' => 'old.myshopify.com',
        'sync_status' => 'synced',
    ]);
    Product::factory()->for($team)->for($oldShop)->create();
    $this->withoutVite()->actingAs($user);
    Queue::fake([RegisterShopifyCatalogWebhooks::class, StartShopifyCatalogImport::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://beta.myshopify.com/admin/oauth/access_token' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'refresh_token_expires_in' => 86400,
            'scope' => 'read_products,read_inventory,read_customers,read_orders',
        ]),
        'https://beta.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'shop' => [
                    'name' => 'Beta Store',
                    'myshopifyDomain' => 'beta.myshopify.com',
                    'currencyCode' => 'BRL',
                    'ianaTimezone' => 'America/Sao_Paulo',
                    'shopAddress' => ['countryCodeV2' => 'BR'],
                ],
            ],
        ]),
        'https://old.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => ['appUninstall' => ['userErrors' => []]],
        ]),
    ]);

    $state = startShopifyAuthorization($this, 'beta.myshopify.com');
    $this->get(route('integrations.shopify.callback', shopifySignedCallback($state, 'beta.myshopify.com')))
        ->assertRedirect(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->assertSessionHas('shopify_success', 'Loja Shopify conectada.');

    expect($oldShop->fresh()->domain)->toBe('beta.myshopify.com')
        ->and($oldShop->fresh()->sync_status)->toBe('importing')
        ->and(Product::withoutGlobalScopes()->where('shop_id', $oldShop->getKey())->count())->toBe(0)
        ->and($integration->fresh()->credential('access_token'))->toBe('new-access-token');

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://old.myshopify.com/admin/api/2026-07/graphql.json'
        && str_contains($request->data()['query'], 'appUninstall')
        && $request->hasHeader('X-Shopify-Access-Token', 'old-access-token'));
});

it('rejects invalid, expired or refused callbacks without creating integration data', function (string $callbackType) {
    $user = User::factory()->withTeam()->create();
    $this->actingAs($user);
    Http::preventStrayRequests();
    $state = startShopifyAuthorization($this, 'alpha.myshopify.com');
    $parameters = shopifySignedCallback($state);

    if ($callbackType === 'invalid_state') {
        $parameters['state'] = str_repeat('x', 64);
        $parameters = shopifySignCallback($parameters);
    } elseif ($callbackType === 'invalid_hmac') {
        $parameters['hmac'] = str_repeat('0', 64);
    } elseif ($callbackType === 'refused') {
        $parameters['error'] = 'access_denied';
        $parameters = shopifySignCallback($parameters);
    } else {
        $parameters['timestamp'] = (string) now()->subMinutes(11)->timestamp;
        $parameters = shopifySignCallback($parameters);
    }

    $this->get(route('integrations.shopify.callback', $parameters))
        ->assertRedirect(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->assertSessionHas('shopify_error');

    expect(Integration::query()->count())->toBe(0)
        ->and(Shop::query()->count())->toBe(0);
})
    ->with(['invalid_state', 'invalid_hmac', 'refused', 'expired_timestamp']);

it('refreshes expired Shopify credentials before making a GraphQL request and stores rotated tokens', function () {
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'expired-access-token',
            'refresh_token' => 'current-refresh-token',
            'access_token_expires_at' => now()->subMinute()->timestamp,
            'refresh_token_expires_at' => now()->addDay()->timestamp,
            'scopes' => ['read_products', 'read_inventory', 'read_customers', 'read_orders'],
        ],
    ]);
    $shop = Shop::factory()->for($user->company())->for($integration)->create(['domain' => 'alpha.myshopify.com']);
    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/oauth/access_token' => Http::response([
            'access_token' => 'rotated-access-token',
            'refresh_token' => 'rotated-refresh-token',
            'expires_in' => 3600,
            'refresh_token_expires_in' => 86400,
        ]),
        'https://alpha.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'bulkOperationRunQuery' => [
                    'bulkOperation' => [
                        'id' => 'gid://shopify/BulkOperation/123',
                        'status' => 'CREATED',
                    ],
                ],
            ],
        ]),
    ]);

    $result = app(EcommerceAdapter::class)->queryShop($shop, 'mutation { bulkOperationRunQuery { bulkOperation { id status } } }');

    expect($result['bulkOperationRunQuery']['bulkOperation']['id'])->toBe('gid://shopify/BulkOperation/123')
        ->and($integration->fresh()->credential('access_token'))->toBe('rotated-access-token')
        ->and($integration->fresh()->credential('refresh_token'))->toBe('rotated-refresh-token')
        ->and($integration->fresh()->credential('access_token_expires_at'))->toBeGreaterThan(now()->timestamp);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://alpha.myshopify.com/admin/oauth/access_token'
        && $request->data()['grant_type'] === 'refresh_token'
        && $request->data()['refresh_token'] === 'current-refresh-token');
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json'
        && $request->hasHeader('X-Shopify-Access-Token', 'rotated-access-token'));
});

it('marks the Shopify connection as expired when refresh fails', function () {
    $user = User::factory()->withTeam()->create();
    $integration = Integration::factory()->for($user->company())->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'expired-access-token',
            'refresh_token' => 'expired-refresh-token',
            'access_token_expires_at' => now()->subMinute()->timestamp,
            'refresh_token_expires_at' => now()->addDay()->timestamp,
        ],
    ]);
    $shop = Shop::factory()->for($user->company())->for($integration)->create(['domain' => 'alpha.myshopify.com']);
    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/oauth/access_token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    expect(fn () => app(EcommerceAdapter::class)->queryShop($shop, 'query { shop { name } }'))
        ->toThrow(RuntimeException::class, 'Reconecte a Loja');

    expect($integration->fresh()->status)->toBe('error')
        ->and($integration->fresh()->status_message)->toContain('Reconecte a Loja')
        ->and($shop->fresh()->sync_status)->toBe('error')
        ->and($shop->fresh()->sync_error)->toContain('Reconecte a Loja');
});

it('requires integration management permission and uninstalls Shopify before soft deleting its records', function () {
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $team = $owner->company();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'secret-access-token',
            'refresh_token' => 'secret-refresh-token',
            'access_token_expires_at' => now()->addHour()->timestamp,
            'refresh_token_expires_at' => now()->addDay()->timestamp,
        ],
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create();
    Http::preventStrayRequests();
    Http::fake([
        'https://'.$shop->domain.'/admin/api/2026-07/graphql.json' => Http::response([
            'data' => ['appUninstall' => ['userErrors' => []]],
        ]),
    ]);

    $this->actingAs($member)->get(route('integrations.shopify.connect', ['shop' => $shop->domain]))->assertForbidden();
    $this->get(route('integrations.shopify.callback'))->assertForbidden();
    $this->delete(route('integrations.destroy', $integration))->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('integrations.destroy', $integration))
        ->assertRedirect(route('integrations.index'));

    $this->assertSoftDeleted('integrations', ['id' => $integration->getKey()]);
    $this->assertSoftDeleted('shops', ['id' => $shop->getKey()]);

    expect(Integration::withTrashed()->findOrFail($integration->getKey())->credentials)->toBe([])
        ->and(Integration::withTrashed()->findOrFail($integration->getKey())->status)->toBe('disconnected');
});

it('marks Shopify as available in the catalog while keeping other ecommerce providers planned', function () {
    $user = User::factory()->withTeam()->create();
    $this->withoutVite();

    $this->actingAs($user)
        ->get(route('integrations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ecommerceCatalog.0.value', 'shopify')
            ->where('ecommerceCatalog.0.available', true)
            ->where('ecommerceCatalog.0.planned', false)
            ->where('ecommerceCatalog.1.available', false)
            ->where('ecommerceCatalog.1.planned', true),
        );
});

it('rejects Shopify through the generic integration credential workflow', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    Http::preventStrayRequests();

    $this->actingAs($user)
        ->postJson(route('integrations.store'), [
            'name' => 'Shopify API',
            'type' => 'ecommerce',
            'provider' => 'shopify',
            'credentials' => ['access_token' => 'unapproved-token'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');

    $genericIntegration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'woocommerce',
    ]);

    $this->patchJson(route('integrations.update', $genericIntegration), [
        'name' => 'Shopify API',
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'credentials' => ['access_token' => 'unapproved-token'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');

    $shopifyIntegration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
    ]);

    $this->patchJson(route('integrations.update', $shopifyIntegration), [
        'name' => 'Other store',
        'type' => 'ecommerce',
        'provider' => 'woocommerce',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');

    $this->postJson(route('integrations.test', $shopifyIntegration))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');

    expect(Integration::query()->where('team_id', $team->getKey())->count())->toBe(2)
        ->and($genericIntegration->fresh()->provider)->toBe('woocommerce')
        ->and($shopifyIntegration->fresh()->provider)->toBe('shopify')
        ->and($shopifyIntegration->fresh()->status)->toBe('connected');

    Http::assertNothingSent();
});

function shopifySignedCallback(string $state, string $domain = 'alpha.myshopify.com'): array
{
    return shopifySignCallback([
        'code' => 'authorization-code',
        'shop' => $domain,
        'state' => $state,
        'timestamp' => (string) now()->timestamp,
    ]);
}

function shopifySignCallback(array $parameters): array
{
    unset($parameters['hmac']);
    ksort($parameters, SORT_STRING);

    $parts = [];
    foreach ($parameters as $key => $value) {
        $parts[] = $key.'='.$value;
    }

    $parameters['hmac'] = hash_hmac('sha256', implode('&', $parts), 'client-secret');

    return $parameters;
}

function startShopifyAuthorization(TestCase $test, string $domain): string
{
    $response = $test->get(route('integrations.shopify.connect', ['shop' => $domain]));
    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    return $query['state'];
}
