<?php

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifyAccessRevokedException;
use App\Ecommerce\ShopifyReconciliationSynchronizer;
use App\Ecommerce\ShopifyThrottleException;
use App\Enums\TeamRole;
use App\Jobs\ReconcileShopifyShop;
use App\Jobs\RegisterShopifyCatalogWebhooks;
use App\Jobs\SyncShopifyCatalogResource;
use App\Models\Category;
use App\Models\Integration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\User;
use App\Notifications\Shopify\ShopConnectionErrorNotification;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'app.url' => 'https://platform.example.test',
        'services.shopify.client_id' => 'client-id',
        'services.shopify.client_secret' => 'client-secret',
        'services.shopify.api_version' => '2026-07',
    ]);
});

it('queues daily reconciliation for connected synced shops and shops with recoverable errors', function () {
    $syncedShop = createShopifyReconciliationShop(['sync_status' => 'synced'])[3];
    $recoverableErrorShop = createShopifyReconciliationShop([
        'sync_status' => 'error',
        'sync_error' => 'Temporary mirror failure',
    ])[3];
    $importingShop = createShopifyReconciliationShop(['sync_status' => 'importing'])[3];
    createShopifyReconciliationShop(
        ['sync_status' => 'synced'],
        ['status' => 'error'],
    );
    Queue::fake([ReconcileShopifyShop::class]);

    $this->artisan('shopify:reconcile')->assertSuccessful();

    Queue::assertPushed(ReconcileShopifyShop::class, 2);
    Queue::assertPushed(ReconcileShopifyShop::class, fn (ReconcileShopifyShop $job): bool => $job->shopId === $syncedShop->getKey());
    Queue::assertPushed(ReconcileShopifyShop::class, fn (ReconcileShopifyShop $job): bool => $job->shopId === $recoverableErrorShop->getKey());
    Queue::assertNotPushed(ReconcileShopifyShop::class, fn (ReconcileShopifyShop $job): bool => $job->shopId === $importingShop->getKey());
});

it('recovers changes without webhooks across products collections customers and orders and advances the cursor', function () {
    $this->travelTo('2026-09-29 12:00:00');
    [$team, , , $shop] = createShopifyReconciliationShop([
        'sync_status' => 'error',
        'sync_error' => 'Falha anterior',
        'suggested_action' => 'Tente novamente',
        'country_code' => 'BR',
        'last_synced_at' => now()->subDay(),
        'reconciliation_cursor' => now()->subHours(4),
    ]);
    $cursor = $shop->reconciliation_cursor;
    $product = Product::factory()->for($shop)->create([
        'external_id' => 'gid://shopify/Product/1001',
        'title' => 'Título antigo',
    ]);
    ProductVariant::factory()->for($shop)->for($product)->create([
        'external_id' => 'gid://shopify/ProductVariant/2001',
        'price' => '100.00',
    ]);
    $customer = StoreCustomer::factory()->for($shop)->create([
        'team_id' => $team->getKey(),
        'external_id' => 'gid://shopify/Customer/7001',
        'phone_e164' => '+5511987654321',
    ]);
    $order = StoreOrder::factory()->for($shop)->create([
        'team_id' => $team->getKey(),
        'store_customer_id' => $customer->getKey(),
        'customer_external_id' => 'gid://shopify/Customer/7001',
        'external_id' => 'gid://shopify/Order/9001',
        'financial_status' => 'PENDING',
        'ordered_at' => now()->subDays(9),
    ]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    $orderPage = 0;
    $updatedFilters = [];
    $vector = array_merge([1.0], array_fill(0, 1535, 0.0));
    Embeddings::fake([[$vector]]);
    Queue::fake([RegisterShopifyCatalogWebhooks::class, SyncShopifyCatalogResource::class]);
    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) use (&$orderPage, &$updatedFilters): PromiseInterface {
        $query = $request->data()['query'];
        $variables = $request->data()['variables'] ?? [];

        foreach ([
            'ShopifyReconciliationProducts' => ['products', 'gid://shopify/Product/1001'],
            'ShopifyReconciliationCollections' => ['collections', 'gid://shopify/Collection/3001'],
            'ShopifyReconciliationCustomers' => ['customers', 'gid://shopify/Customer/7001'],
            'ShopifyReconciliationOrders' => ['orders', 'gid://shopify/Order/9001'],
        ] as $operation => [$connection, $externalId]) {
            if (str_contains($query, $operation)) {
                $updatedFilters[$connection] = $variables['query'];

                return Http::response(['data' => [
                    $connection => [
                        'nodes' => [['id' => $externalId]],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    ],
                ]]);
            }
        }

        if (str_contains($query, 'ShopifyProductCatalogResource')) {
            return Http::response(shopifyReconciliationFixture('product.graphql.json'));
        }

        if (str_contains($query, 'ShopifyCollectionCatalogResource')) {
            return Http::response(shopifyReconciliationFixture('collection.graphql.json'));
        }

        if (str_contains($query, 'ShopifyCustomerResource')) {
            return Http::response(shopifyReconciliationFixture('customer-updated.graphql.json'));
        }

        if (str_contains($query, 'ShopifyOrderResource')) {
            $orderPage++;

            return Http::response(shopifyReconciliationFixture($orderPage === 1
                ? 'order-page-one.graphql.json'
                : 'order-page-two.graphql.json'));
        }

        if (str_contains($query, 'ShopifyReconciliationNodes')) {
            return Http::response(['data' => [
                'nodes' => array_map(fn (string $externalId): array => ['id' => $externalId], $variables['ids']),
            ]]);
        }

        throw new RuntimeException('Unexpected Shopify query: '.$query);
    });

    (new ReconcileShopifyShop($shop->getKey()))->handle(
        app(EcommerceAdapter::class),
        app(ShopifyReconciliationSynchronizer::class),
    );

    expect($product->fresh()->title)->toBe('Tênis de corrida leve')
        ->and(ProductVariant::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole()->price)->toBe('349.90')
        ->and(Category::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole()->title)->toBe('Corrida de trilha')
        ->and($customer->fresh()->phone_e164)->toBe('+5521998765432')
        ->and($order->fresh()->financial_status)->toBe('PAID')
        ->and(StoreOrder::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole()->items()->count())->toBe(2)
        ->and($shop->fresh()->sync_status)->toBe('synced')
        ->and($shop->fresh()->sync_error)->toBeNull()
        ->and($shop->fresh()->suggested_action)->toBeNull()
        ->and($shop->fresh()->last_synced_at->equalTo(now()))->toBeTrue()
        ->and($shop->fresh()->reconciliation_cursor->equalTo(now()))->toBeTrue();

    expect($updatedFilters)->toHaveCount(4);
    foreach ($updatedFilters as $filter) {
        expect($filter)->toContain('updated_at:>="'.$cursor->format(DATE_ATOM).'"');
    }

    Queue::assertPushed(RegisterShopifyCatalogWebhooks::class, fn (RegisterShopifyCatalogWebhooks $job): bool => $job->shopId === $shop->getKey());
});

it('removes products collections customers and orders that disappeared without webhooks', function () {
    $this->travelTo('2026-09-29 12:00:00');
    [$team, , , $shop] = createShopifyReconciliationShop([
        'last_synced_at' => now()->subDay(),
        'reconciliation_cursor' => now()->subHours(4),
    ]);
    $staleProduct = Product::factory()->for($shop)->create([
        'external_id' => 'gid://shopify/Product/1001',
    ]);
    $liveProduct = Product::factory()->for($shop)->create([
        'external_id' => 'gid://shopify/Product/1002',
    ]);
    $staleVariant = ProductVariant::factory()->for($shop)->for($staleProduct)->create();
    $category = Category::factory()->for($shop)->create([
        'external_id' => 'gid://shopify/Collection/3001',
    ]);
    DB::table('category_product')->insert([
        'team_id' => $team->getKey(),
        'product_id' => $liveProduct->getKey(),
        'category_id' => $category->getKey(),
        'last_import_operation_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $staleCustomer = StoreCustomer::factory()->for($shop)->create([
        'team_id' => $team->getKey(),
        'external_id' => 'gid://shopify/Customer/7001',
    ]);
    $staleOrder = StoreOrder::factory()->for($shop)->create([
        'team_id' => $team->getKey(),
        'store_customer_id' => $staleCustomer->getKey(),
        'external_id' => 'gid://shopify/Order/9001',
        'ordered_at' => now()->subDays(9),
    ]);
    $staleOrderItem = $staleOrder->items()->create([
        'team_id' => $team->getKey(),
        'shop_id' => $shop->getKey(),
        'external_id' => 'gid://shopify/LineItem/9101',
        'title' => 'Item antigo',
        'quantity' => 1,
        'unit_price' => '20.00',
    ]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Queue::fake([RegisterShopifyCatalogWebhooks::class, SyncShopifyCatalogResource::class]);
    Http::preventStrayRequests();
    Http::fake(function (ClientRequest $request) use ($liveProduct): PromiseInterface {
        $query = $request->data()['query'];
        $variables = $request->data()['variables'] ?? [];

        foreach (['products', 'collections', 'customers', 'orders'] as $connection) {
            if (str_contains($query, 'ShopifyReconciliation'.ucfirst($connection))) {
                return Http::response(['data' => [
                    $connection => [
                        'nodes' => [],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    ],
                ]]);
            }
        }

        if (str_contains($query, 'ShopifyReconciliationNodes')) {
            $nodes = array_values(array_filter(
                $variables['ids'],
                fn (string $externalId): bool => $externalId === $liveProduct->external_id,
            ));

            return Http::response(['data' => [
                'nodes' => array_map(fn (string $externalId): array => ['id' => $externalId], $nodes),
            ]]);
        }

        if (str_contains($query, 'ShopifyProductCatalogResource')) {
            return Http::response(shopifyReconciliationFixture('product-missing.graphql.json'));
        }

        if (str_contains($query, 'ShopifyCollectionCatalogResource')) {
            return Http::response(shopifyReconciliationFixture('collection-missing.graphql.json'));
        }

        if (str_contains($query, 'ShopifyCustomerResource')) {
            return Http::response(['data' => ['customer' => null]]);
        }

        if (str_contains($query, 'ShopifyOrderResource')) {
            return Http::response(['data' => ['order' => null]]);
        }

        throw new RuntimeException('Unexpected Shopify query: '.$query);
    });

    (new ReconcileShopifyShop($shop->getKey()))->handle(
        app(EcommerceAdapter::class),
        app(ShopifyReconciliationSynchronizer::class),
    );

    expect(Product::withoutGlobalScopes()->whereKey($staleProduct->getKey())->exists())->toBeFalse()
        ->and(ProductVariant::withoutGlobalScopes()->whereKey($staleVariant->getKey())->exists())->toBeFalse()
        ->and(Product::withoutGlobalScopes()->whereKey($liveProduct->getKey())->exists())->toBeTrue()
        ->and(Category::withoutGlobalScopes()->whereKey($category->getKey())->exists())->toBeFalse()
        ->and(StoreCustomer::withoutGlobalScopes()->whereKey($staleCustomer->getKey())->exists())->toBeFalse()
        ->and(StoreOrder::withoutGlobalScopes()->whereKey($staleOrder->getKey())->exists())->toBeFalse()
        ->and(DB::table('store_order_items')->where('id', $staleOrderItem->getKey())->exists())->toBeFalse();
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['query'], 'ShopifyReconciliationNodes'));
});

it('retries Shopify throttles with backoff without marking the shop as broken', function (string $throttleType, int $expectedRetryAfter) {
    [, , $integration, $shop] = createShopifyReconciliationShop([
        'last_synced_at' => now()->subDay(),
        'reconciliation_cursor' => now()->subHours(4),
    ]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Queue::fake([RegisterShopifyCatalogWebhooks::class]);
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => $throttleType === 'http_429'
        ? Http::response(['errors' => [['message' => 'Throttled']]], 429, ['Retry-After' => '17'])
        : Http::response([
            'data' => null,
            'errors' => [['message' => 'Throttled', 'extensions' => ['code' => 'THROTTLED']]],
            'extensions' => [
                'cost' => [
                    'requestedQueryCost' => 100,
                    'throttleStatus' => ['currentlyAvailable' => 0, 'restoreRate' => 5],
                ],
            ],
        ])]);
    $job = new ReconcileShopifyShop($shop->getKey());
    $exception = null;

    try {
        $job->handle(app(EcommerceAdapter::class), app(ShopifyReconciliationSynchronizer::class));
    } catch (ShopifyThrottleException $caught) {
        $exception = $caught;
    }

    if (! $exception instanceof ShopifyThrottleException) {
        throw new RuntimeException('Expected ShopifyThrottleException to be thrown.');
    }

    expect($exception->retryAfterSeconds)->toBe($expectedRetryAfter)
        ->and($job->backoff)->toBe([30, 60, 120, 300])
        ->and($integration->fresh()->status)->toBe('connected')
        ->and($shop->fresh()->sync_status)->toBe('synced')
        ->and($shop->fresh()->reconciliation_cursor->equalTo($shop->reconciliation_cursor))->toBeTrue();

    Notification::assertNothingSent();
})->with([
    'HTTP 429' => ['http_429', 17],
    'GraphQL throttle' => ['graphql_throttled', 20],
]);

it('marks failed token refresh as a shop error and notifies each owner and admin once', function () {
    [$team, $owner, $integration, $shop] = createShopifyReconciliationShop([], [
        'credentials' => [
            'access_token' => 'expired-access-token',
            'refresh_token' => 'expired-refresh-token',
            'access_token_expires_at' => now()->subMinute()->timestamp,
            'refresh_token_expires_at' => now()->addDay()->timestamp,
            'scopes' => ['read_products', 'read_inventory', 'read_customers', 'read_orders'],
        ],
    ]);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/oauth/access_token' => Http::response(['error' => 'invalid_grant'], 400),
    ]);

    expect(fn () => app(EcommerceAdapter::class)->queryShop($shop, 'query { shop { name } }'))
        ->toThrow(RuntimeException::class, 'Reconecte a Loja');
    expect(fn () => app(EcommerceAdapter::class)->queryShop($shop, 'query { shop { name } }'))
        ->toThrow(RuntimeException::class, 'Reconecte a Loja');

    expect($integration->fresh()->status)->toBe('error')
        ->and($shop->fresh()->sync_status)->toBe('error')
        ->and($shop->fresh()->sync_error)->toContain('Reconecte a Loja')
        ->and($shop->fresh()->suggested_action)->toBe('Reconecte a Loja pela tela de Integrações.');

    Notification::assertSentToTimes($owner, ShopConnectionErrorNotification::class, 1);
    Notification::assertSentToTimes($admin, ShopConnectionErrorNotification::class, 1);
    Notification::assertNotSentTo($member, ShopConnectionErrorNotification::class);
});

it('marks revoked GraphQL access as an error and notifies the team', function () {
    [$team, $owner, $integration, $shop] = createShopifyReconciliationShop();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => Http::response(['errors' => [[
        'message' => 'Access denied',
        'extensions' => ['code' => 'ACCESS_DENIED'],
    ]]], 200)]);

    expect(fn () => app(EcommerceAdapter::class)->queryShop($shop, 'query { shop { name } }'))
        ->toThrow(ShopifyAccessRevokedException::class);

    expect($integration->fresh()->status)->toBe('error')
        ->and($shop->fresh()->sync_status)->toBe('error')
        ->and($shop->fresh()->suggested_action)->toContain('Reconecte a Loja');

    Notification::assertSentToTimes($owner, ShopConnectionErrorNotification::class, 1);
    Notification::assertSentToTimes($admin, ShopConnectionErrorNotification::class, 1);
});

function createShopifyReconciliationShop(array $shopAttributes = [], array $integrationAttributes = []): array
{
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $integration = Integration::factory()->for($team)->create(array_replace([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'shopify-access-token',
            'refresh_token' => 'shopify-refresh-token',
            'access_token_expires_at' => now()->addHour()->timestamp,
            'refresh_token_expires_at' => now()->addDay()->timestamp,
            'scopes' => ['read_products', 'read_inventory', 'read_customers', 'read_orders'],
        ],
    ], $integrationAttributes));
    $shop = Shop::factory()->for($team)->for($integration)->create(array_replace([
        'domain' => 'alpha.myshopify.com',
        'sync_status' => 'synced',
        'last_synced_at' => now()->subDay(),
        'reconciliation_cursor' => null,
    ], $shopAttributes));

    return [$team, $owner, $integration, $shop];
}

function shopifyReconciliationFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path('tests/Fixtures/ecommerce/shopify/'.$name)),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}
