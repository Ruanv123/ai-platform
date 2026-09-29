<?php

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ShopifyCatalogSynchronizer;
use App\Ecommerce\ShopifyCustomerOrderSynchronizer;
use App\Jobs\RegisterShopifyCatalogWebhooks;
use App\Jobs\SyncShopifyCatalogResource;
use App\Models\Category;
use App\Models\Integration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'app.url' => 'https://platform.example.test',
        'services.shopify.client_secret' => 'client-secret',
        'services.shopify.api_version' => '2026-07',
    ]);
});

it('registers catalog, customer, order, and bulk-finish webhook subscriptions once per connected store', function () {
    $shop = createShopifyWebhookShop();
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    $webhookUri = route('webhooks.shopify');
    $topics = [
        'BULK_OPERATIONS_FINISH',
        'PRODUCTS_CREATE',
        'PRODUCTS_UPDATE',
        'PRODUCTS_DELETE',
        'INVENTORY_LEVELS_UPDATE',
        'COLLECTIONS_CREATE',
        'COLLECTIONS_UPDATE',
        'COLLECTIONS_DELETE',
        'CUSTOMERS_CREATE',
        'CUSTOMERS_UPDATE',
        'CUSTOMERS_DELETE',
        'ORDERS_CREATE',
        'ORDERS_UPDATED',
        'ORDERS_DELETE',
    ];
    $sequence = Http::sequence()->push(['data' => ['webhookSubscriptions' => ['nodes' => []]]]);

    foreach ($topics as $topic) {
        $sequence->push(['data' => [
            'webhookSubscriptionCreate' => [
                'webhookSubscription' => [
                    'id' => 'gid://shopify/WebhookSubscription/'.count($topics),
                    'topic' => $topic,
                    'uri' => $webhookUri,
                ],
                'userErrors' => [],
            ],
        ]]);
    }

    $sequence->push(['data' => ['webhookSubscriptions' => [
        'nodes' => array_map(fn (string $topic): array => ['topic' => $topic, 'uri' => $webhookUri], $topics),
    ]]]);
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => $sequence]);

    (new RegisterShopifyCatalogWebhooks($shop->getKey()))->handle(app(EcommerceAdapter::class));
    (new RegisterShopifyCatalogWebhooks($shop->getKey()))->handle(app(EcommerceAdapter::class));

    Http::assertSentCount(16);
    foreach ($topics as $topic) {
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
            && ($request->data()['variables']['topic'] ?? null) === $topic
            && ($request->data()['variables']['webhookSubscription']['uri'] ?? null) === $webhookUri);
    }
});

it('queues only signed resource identifiers from catalog webhook notices', function (string $topic, string $fixture, string $resourceType, string $externalId, bool $deleted) {
    $shop = createShopifyWebhookShop();
    $body = shopifyCatalogWebhookFixture($fixture);
    Queue::fake([SyncShopifyCatalogResource::class]);

    $this->call(
        'POST',
        route('webhooks.shopify'),
        [],
        [],
        [],
        shopifyCatalogWebhookHeaders($body, $topic),
        $body,
    )->assertOk();

    Queue::assertPushed(SyncShopifyCatalogResource::class, fn (SyncShopifyCatalogResource $job): bool => $job->shopId === $shop->getKey()
        && $job->resourceType === $resourceType
        && $job->externalId === $externalId
        && $job->deleted === $deleted
        && ! property_exists($job, 'payload'));
})->with([
    'product update' => ['products/update', 'product-update.webhook.json', 'product', 'gid://shopify/Product/1001', false],
    'product delete' => ['products/delete', 'product-delete.webhook.json', 'product', 'gid://shopify/Product/1001', true],
    'inventory update' => ['inventory_levels/update', 'inventory-level-update.webhook.json', 'inventory_item', 'gid://shopify/InventoryItem/5001', false],
    'collection update' => ['collections/update', 'collection-update.webhook.json', 'collection', 'gid://shopify/Collection/3001', false],
    'customer update' => ['customers/update', 'customer-update.webhook.json', 'customer', 'gid://shopify/Customer/7001', false],
    'customer delete' => ['customers/delete', 'customer-update.webhook.json', 'customer', 'gid://shopify/Customer/7001', true],
    'order update' => ['orders/updated', 'order-update.webhook.json', 'order', 'gid://shopify/Order/9001', false],
    'order delete' => ['orders/delete', 'order-update.webhook.json', 'order', 'gid://shopify/Order/9001', true],
]);

it('coalesces repeated notices for the same resource during the debounce window', function () {
    createShopifyWebhookShop();
    $body = shopifyCatalogWebhookFixture('product-update.webhook.json');
    Queue::fake([SyncShopifyCatalogResource::class]);

    foreach ([1, 2] as $delivery) {
        $this->call(
            'POST',
            route('webhooks.shopify'),
            [],
            [],
            [],
            shopifyCatalogWebhookHeaders($body, 'products/update'),
            $body,
        )->assertOk();
    }

    Queue::assertPushed(SyncShopifyCatalogResource::class, 1);
});

it('fetches current product data and refreshes embeddings only when searchable text changes', function () {
    $shop = createShopifyWebhookShop();
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    $sequence = Http::sequence()
        ->push(shopifyCatalogGraphqlFixture('product.graphql.json'))
        ->push(shopifyCatalogGraphqlFixture('product-price-updated.graphql.json'))
        ->push(shopifyCatalogGraphqlFixture('product-draft.graphql.json'));
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => $sequence]);
    $vector = array_merge([1.0], array_fill(0, 1535, 0.0));
    Embeddings::fake([[$vector]]);

    $job = new SyncShopifyCatalogResource($shop->getKey(), 'product', 'gid://shopify/Product/1001', false);
    $job->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    $product = Product::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole();
    $variant = ProductVariant::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole();
    $category = Category::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole();
    $firstSourceHash = $product->source_hash;

    expect($product->title)->toBe('Tênis de corrida leve')
        ->and($product->description)->toBe('Amortecimento em espuma & tecido respirável.')
        ->and($product->online_store_published)->toBeTrue()
        ->and($variant->inventory_quantity)->toBe(7)
        ->and($category->title)->toBe('Corrida');

    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Tênis de corrida leve')
        && $prompt->contains('Corrida')
        && $prompt->contains('Tamanho: 42'));
    Embeddings::fake()->preventStrayEmbeddings();

    $job->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect($product->fresh()->source_hash)->toBe($firstSourceHash)
        ->and($variant->fresh()->price)->toBe('359.90')
        ->and($variant->fresh()->inventory_quantity)->toBe(2);

    Embeddings::fake([[$vector]]);
    $job->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect($product->fresh()->title)->toBe('Tênis de corrida leve - edição nova')
        ->and($product->fresh()->status)->toBe('DRAFT')
        ->and($product->fresh()->online_store_published)->toBeFalse()
        ->and($product->fresh()->url)->toBeNull()
        ->and($product->fresh()->source_hash)->not->toBe($firstSourceHash);

    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('edição nova'));
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['query'], 'product(id: $id)')
        && ($request->data()['variables']['id'] ?? null) === 'gid://shopify/Product/1001');
});

it('uses the latest summed inventory quantity returned for the inventory item', function () {
    $shop = createShopifyWebhookShop();
    $product = Product::factory()->for($shop)->create(['external_id' => 'gid://shopify/Product/1001']);
    $variant = ProductVariant::factory()->for($shop)->for($product)->create([
        'external_id' => 'gid://shopify/ProductVariant/2001',
        'inventory_quantity' => 2,
        'available' => false,
    ]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => Http::response(shopifyCatalogGraphqlFixture('inventory-item.graphql.json'))]);
    Embeddings::fake()->preventStrayEmbeddings();

    (new SyncShopifyCatalogResource($shop->getKey(), 'inventory_item', 'gid://shopify/InventoryItem/5001', false))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect($variant->fresh()->inventory_quantity)->toBe(9)
        ->and($variant->fresh()->available)->toBeTrue();
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['query'], 'inventoryItem(id: $id)')
        && ($request->data()['variables']['id'] ?? null) === 'gid://shopify/InventoryItem/5001');
});

it('reconciles collection membership and queues affected products for current-state refresh', function () {
    $shop = createShopifyWebhookShop();
    $previousProduct = Product::factory()->for($shop)->create(['external_id' => 'gid://shopify/Product/1001']);
    $category = Category::factory()->for($shop)->create([
        'external_id' => 'gid://shopify/Collection/3001',
        'title' => 'Corrida',
    ]);
    DB::table('category_product')->insert([
        'team_id' => $shop->team_id,
        'product_id' => $previousProduct->getKey(),
        'category_id' => $category->getKey(),
        'last_import_operation_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => Http::response(shopifyCatalogGraphqlFixture('collection.graphql.json'))]);
    Queue::fake([SyncShopifyCatalogResource::class]);

    (new SyncShopifyCatalogResource($shop->getKey(), 'collection', 'gid://shopify/Collection/3001', false))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect($category->fresh()->title)->toBe('Corrida de trilha')
        ->and(DB::table('category_product')->where('product_id', $previousProduct->getKey())->exists())->toBeFalse();

    Queue::assertPushed(SyncShopifyCatalogResource::class, 2);
    Queue::assertPushed(SyncShopifyCatalogResource::class, fn (SyncShopifyCatalogResource $job): bool => $job->externalId === 'gid://shopify/Product/1001');
    Queue::assertPushed(SyncShopifyCatalogResource::class, fn (SyncShopifyCatalogResource $job): bool => $job->externalId === 'gid://shopify/Product/1002');
});

it('removes a deleted product and its local variants and category links', function () {
    $shop = createShopifyWebhookShop();
    $product = Product::factory()->for($shop)->create(['external_id' => 'gid://shopify/Product/1001']);
    $variant = ProductVariant::factory()->for($shop)->for($product)->create();
    $category = Category::factory()->for($shop)->create();
    DB::table('category_product')->insert([
        'team_id' => $shop->team_id,
        'product_id' => $product->getKey(),
        'category_id' => $category->getKey(),
        'last_import_operation_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => Http::response(shopifyCatalogGraphqlFixture('product-missing.graphql.json'))]);

    (new SyncShopifyCatalogResource($shop->getKey(), 'product', 'gid://shopify/Product/1001', true))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect(Product::withoutGlobalScopes()->whereKey($product->getKey())->exists())->toBeFalse()
        ->and(ProductVariant::withoutGlobalScopes()->whereKey($variant->getKey())->exists())->toBeFalse()
        ->and(DB::table('category_product')->where('product_id', $product->getKey())->exists())->toBeFalse()
        ->and(Category::withoutGlobalScopes()->whereKey($category->getKey())->exists())->toBeFalse();
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['query'], 'product(id: $id)'));
});

it('keeps a product when a delete notice arrives after it exists again in Shopify', function () {
    $shop = createShopifyWebhookShop();
    $product = Product::factory()->for($shop)->create([
        'external_id' => 'gid://shopify/Product/1001',
        'title' => 'Título antigo',
    ]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => Http::response(shopifyCatalogGraphqlFixture('product.graphql.json'))]);
    Embeddings::fake([[array_merge([1.0], array_fill(0, 1535, 0.0))]]);

    (new SyncShopifyCatalogResource($shop->getKey(), 'product', 'gid://shopify/Product/1001', true))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect($product->fresh()->title)->toBe('Tênis de corrida leve');
});

it('removes a deleted collection and queues its products to refresh searchable categories', function () {
    $shop = createShopifyWebhookShop();
    $product = Product::factory()->for($shop)->create(['external_id' => 'gid://shopify/Product/1001']);
    $category = Category::factory()->for($shop)->create(['external_id' => 'gid://shopify/Collection/3001']);
    DB::table('category_product')->insert([
        'team_id' => $shop->team_id,
        'product_id' => $product->getKey(),
        'category_id' => $category->getKey(),
        'last_import_operation_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Queue::fake([SyncShopifyCatalogResource::class]);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => Http::response(shopifyCatalogGraphqlFixture('collection-missing.graphql.json'))]);

    (new SyncShopifyCatalogResource($shop->getKey(), 'collection', 'gid://shopify/Collection/3001', true))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect(Category::withoutGlobalScopes()->whereKey($category->getKey())->exists())->toBeFalse()
        ->and(DB::table('category_product')->where('product_id', $product->getKey())->exists())->toBeFalse();
    Queue::assertPushed(SyncShopifyCatalogResource::class, fn (SyncShopifyCatalogResource $job): bool => $job->resourceType === 'product'
        && $job->externalId === 'gid://shopify/Product/1001');
});

it('removes stale products when a delayed update notice finds that Shopify no longer has them', function () {
    $shop = createShopifyWebhookShop();
    $product = Product::factory()->for($shop)->create(['external_id' => 'gid://shopify/Product/1001']);
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Http::preventStrayRequests();
    Http::fake([$graphqlUrl => Http::response(shopifyCatalogGraphqlFixture('product-missing.graphql.json'))]);

    (new SyncShopifyCatalogResource($shop->getKey(), 'product', 'gid://shopify/Product/1001', false))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect(Product::withoutGlobalScopes()->whereKey($product->getKey())->exists())->toBeFalse();
});

function createShopifyWebhookShop(): Shop
{
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $integration = Integration::factory()->for($team)->create([
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
    ]);

    return Shop::factory()->for($team)->for($integration)->create(['domain' => 'alpha.myshopify.com']);
}

function shopifyCatalogWebhookFixture(string $name): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/ecommerce/shopify/'.$name));
}

function shopifyCatalogGraphqlFixture(string $name): array
{
    $fixture = json_decode(shopifyCatalogWebhookFixture($name), true, flags: JSON_THROW_ON_ERROR);

    return is_array($fixture) ? $fixture : [];
}

function shopifyCatalogWebhookHeaders(string $body, string $topic): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode(hash_hmac('sha256', $body, 'client-secret', true)),
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'alpha.myshopify.com',
        'HTTP_X_SHOPIFY_TOPIC' => $topic,
    ];
}
