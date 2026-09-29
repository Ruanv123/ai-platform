<?php

use App\Ecommerce\EcommerceAdapter;
use App\Jobs\CompleteShopifyBulkOperation;
use App\Jobs\ImportShopifyBulkOperation;
use App\Jobs\PollShopifyBulkOperation;
use App\Jobs\StartShopifyCatalogImport;
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
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'services.shopify.client_secret' => 'client-secret',
        'services.shopify.api_version' => '2026-07',
    ]);
});

it('starts the initial GraphQL bulk import and queues progress polling', function () {
    $shop = createImportShop();
    $graphqlUrl = 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
    Queue::fake([PollShopifyBulkOperation::class]);
    Http::preventStrayRequests();
    Http::fake([
        $graphqlUrl => Http::sequence()
            ->push(shopifyFixtureJson('catalog-counts.graphql.json'))
            ->push(shopifyFixtureJson('bulk-operation-started.graphql.json')),
    ]);

    (new StartShopifyCatalogImport($shop->getKey()))->handle(app(EcommerceAdapter::class));

    expect($shop->fresh()->sync_status)->toBe('importing')
        ->and($shop->fresh()->bulk_operation_id)->toBe('gid://shopify/BulkOperation/987654321')
        ->and($shop->fresh()->sync_total_count)->toBe(3);

    Queue::assertPushed(PollShopifyBulkOperation::class, fn (PollShopifyBulkOperation $job): bool => $job->shopId === $shop->getKey()
        && $job->operationId === 'gid://shopify/BulkOperation/987654321');
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['query'], 'bulkOperationRunQuery')
        && str_contains($request->data()['variables']['query'], 'inventoryQuantity')
        && str_contains($request->data()['variables']['query'], 'onlineStoreUrl'));
});

it('keeps the Shopify store in importing status and exposes progress to the integrations page', function () {
    $user = User::factory()->withTeam()->create();
    $this->withoutVite();
    $shop = createImportShop($user, [
        'sync_processed_count' => 2,
        'sync_total_count' => 3,
        'sync_progress' => 63,
    ]);

    $this->actingAs($user)
        ->get(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/show')
            ->where('shop.syncStatus', 'importing')
            ->where('shop.syncProgress', 63)
            ->where('shop.syncProcessedCount', 2)
            ->where('shop.syncTotalCount', 3),
        );

    expect($shop->fresh()->sync_processed_count)->toBe(2);
});

it('returns 401 for an invalid Shopify webhook signature', function () {
    $shop = createImportShop();
    Queue::fake([CompleteShopifyBulkOperation::class]);
    $body = shopifyFixture('bulk-operation-finish.webhook.json');

    $this->call('POST', route('webhooks.shopify'), [], [], [], shopifyWebhookHeaders('invalid-signature'), $body)
        ->assertUnauthorized();

    Queue::assertNothingPushed();
    expect($shop->fresh()->sync_status)->toBe('importing');
});

it('discards a signed Shopify webhook for an unknown shop', function () {
    Queue::fake([CompleteShopifyBulkOperation::class]);
    $body = shopifyFixture('bulk-operation-finish.webhook.json');

    $this->call('POST', route('webhooks.shopify'), [], [], [], shopifyWebhookHeaders(shopifyWebhookSignature($body), 'unknown.myshopify.com'), $body)
        ->assertOk();

    Queue::assertNothingPushed();
});

it('dispatches a signed bulk finish through GraphQL result lookup to JSONL import', function () {
    $shop = createImportShop();
    $shop->forceFill(['bulk_operation_id' => 'gid://shopify/BulkOperation/987654321'])->save();
    Queue::fake([CompleteShopifyBulkOperation::class, ImportShopifyBulkOperation::class]);
    $body = shopifyFixture('bulk-operation-finish.webhook.json');
    $headers = shopifyWebhookHeaders(shopifyWebhookSignature($body));

    $this->call('POST', route('webhooks.shopify'), [], [], [], $headers, $body)
        ->assertOk();

    Queue::assertPushed(CompleteShopifyBulkOperation::class, fn (CompleteShopifyBulkOperation $job): bool => $job->shopId === $shop->getKey()
        && $job->operationId === 'gid://shopify/BulkOperation/987654321');

    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/api/2026-07/graphql.json' => Http::response(shopifyFixtureJson('bulk-operation-finished.graphql.json')),
        'https://storage.googleapis.com/shopify/catalog-987654321.jsonl' => Http::response(shopifyFixture('catalog.jsonl'), 200, ['Content-Type' => 'application/x-ndjson']),
    ]);
    Embeddings::fake([
        [array_merge([1.0], array_fill(0, 1535, 0.0))],
    ]);

    (new CompleteShopifyBulkOperation($shop->getKey(), 'gid://shopify/BulkOperation/987654321'))
        ->handle(app(EcommerceAdapter::class));

    Queue::assertPushed(ImportShopifyBulkOperation::class, fn (ImportShopifyBulkOperation $job): bool => $job->shopId === $shop->getKey()
        && $job->operationId === 'gid://shopify/BulkOperation/987654321'
        && $job->url === 'https://storage.googleapis.com/shopify/catalog-987654321.jsonl');

    (new ImportShopifyBulkOperation(
        $shop->getKey(),
        'gid://shopify/BulkOperation/987654321',
        'https://storage.googleapis.com/shopify/catalog-987654321.jsonl',
    ))->handle();

    expect(DB::table('shops')->where('id', $shop->getKey())->value('sync_status'))->toBe('synced')
        ->and(Product::withoutGlobalScopes()->where('shop_id', $shop->getKey())->count())->toBe(1);
});

it('streams the Shopify JSONL fixture into the canonical catalog and repeats without duplicate rows or embeddings', function (string $provider) {
    $shop = createImportShop();
    $operationId = 'gid://shopify/BulkOperation/987654321';
    $shop->forceFill([
        'sync_status' => 'importing',
        'bulk_operation_id' => $operationId,
        'sync_total_count' => 3,
    ])->save();
    $jsonlUrl = 'https://storage.googleapis.com/shopify/catalog-987654321.jsonl';
    $vector = array_merge([1.0], array_fill(0, 1535, 0.0));
    Embeddings::fake([[$vector]]);
    Http::preventStrayRequests();
    Http::fake([
        $jsonlUrl => Http::response(shopifyFixture('catalog.jsonl', $provider), 200, ['Content-Type' => 'application/x-ndjson']),
    ]);

    (new ImportShopifyBulkOperation($shop->getKey(), $operationId, $jsonlUrl))->handle();

    $product = Product::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole();
    $variant = ProductVariant::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole();
    $category = Category::withoutGlobalScopes()->where('shop_id', $shop->getKey())->sole();

    expect($product->external_id)->toBe('gid://shopify/Product/1001')
        ->and($product->title)->toBe('Tênis de corrida leve')
        ->and($product->description)->toBe('Amortecimento em espuma & tecido respirável.')
        ->and($product->url)->toBe('https://alpha.myshopify.com/products/tenis-corrida-leve')
        ->and($product->status)->toBe('ACTIVE')
        ->and($product->online_store_published)->toBeTrue()
        ->and($product->embedding)->not->toBeNull()
        ->and($product->source_hash)->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($variant->sku)->toBe('RUN-42')
        ->and($variant->options)->toBe([['name' => 'Cor', 'value' => 'Azul'], ['name' => 'Tamanho', 'value' => '42']])
        ->and($variant->price)->toBe('349.90')
        ->and($variant->compare_at_price)->toBe('399.90')
        ->and($variant->inventory_quantity)->toBe(7)
        ->and($variant->continues_selling_when_out_of_stock)->toBeFalse()
        ->and($variant->available)->toBeTrue()
        ->and($category->title)->toBe('Corrida')
        ->and($product->categories->modelKeys())->toBe([$category->getKey()])
        ->and($shop->fresh()->sync_status)->toBe('synced')
        ->and($shop->fresh()->sync_progress)->toBe(100)
        ->and($shop->fresh()->sync_processed_count)->toBe(3)
        ->and($shop->fresh()->last_synced_at)->not->toBeNull();

    $firstSourceHash = $product->source_hash;
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('Tênis de corrida leve')
        && $prompt->contains('Amortecimento em espuma')
        && $prompt->contains('Corrida')
        && $prompt->contains('Tamanho: 42'));

    $nextOperationId = 'gid://shopify/BulkOperation/987654322';
    $shop->forceFill([
        'sync_status' => 'importing',
        'bulk_operation_id' => $nextOperationId,
        'sync_progress' => 0,
    ])->save();
    Embeddings::fake()->preventStrayEmbeddings();

    (new ImportShopifyBulkOperation($shop->getKey(), $nextOperationId, $jsonlUrl))->handle();

    expect(Product::withoutGlobalScopes()->where('shop_id', $shop->getKey())->count())->toBe(1)
        ->and(ProductVariant::withoutGlobalScopes()->where('shop_id', $shop->getKey())->count())->toBe(1)
        ->and(Category::withoutGlobalScopes()->where('shop_id', $shop->getKey())->count())->toBe(1)
        ->and($product->fresh()->source_hash)->toBe($firstSourceHash)
        ->and($shop->fresh()->sync_status)->toBe('synced');

})->with(['shopify']);

it('updates visible progress and schedules another status check while Shopify is processing', function () {
    $shop = createImportShop();
    $operationId = 'gid://shopify/BulkOperation/987654321';
    $shop->forceFill([
        'bulk_operation_id' => $operationId,
        'sync_total_count' => 10,
    ])->save();
    Queue::fake([PollShopifyBulkOperation::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => [
                'bulkOperation' => [
                    'id' => $operationId,
                    'status' => 'RUNNING',
                    'objectCount' => '2',
                ],
            ],
        ]),
    ]);

    (new PollShopifyBulkOperation($shop->getKey(), $operationId))->handle(app(EcommerceAdapter::class));

    expect($shop->fresh()->sync_processed_count)->toBe(2)
        ->and($shop->fresh()->sync_progress)->toBe(19);
    Queue::assertPushed(PollShopifyBulkOperation::class, fn (PollShopifyBulkOperation $job): bool => $job->shopId === $shop->getKey()
        && $job->operationId === $operationId);
});

function createImportShop(?User $user = null, array $shopAttributes = []): Shop
{
    $user ??= User::factory()->withTeam()->create();
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

    return Shop::factory()->for($team)->for($integration)->create([
        'domain' => 'alpha.myshopify.com',
        ...$shopAttributes,
    ]);
}

function shopifyFixture(string $name, string $provider = 'shopify'): string
{
    return file_get_contents(base_path("tests/Fixtures/ecommerce/{$provider}/{$name}"));
}

function shopifyFixtureJson(string $name): array
{
    return json_decode(shopifyFixture($name), true, flags: JSON_THROW_ON_ERROR);
}

function shopifyWebhookSignature(string $body): string
{
    return base64_encode(hash_hmac('sha256', $body, 'client-secret', true));
}

function shopifyWebhookHeaders(string $signature, string $shopDomain = 'alpha.myshopify.com'): array
{
    return [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $shopDomain,
        'HTTP_X_SHOPIFY_TOPIC' => 'bulk_operations/finish',
    ];
}
