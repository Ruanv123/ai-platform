<?php

use App\Ecommerce\EcommerceAdapter;
use App\Ecommerce\ProductEmbeddingSynchronizer;
use App\Ecommerce\ShopifyCatalogSynchronizer;
use App\Ecommerce\ShopifyCustomerOrderSynchronizer;
use App\Enums\ShopifyBulkOperationKind;
use App\Jobs\ImportShopifyBulkOperation;
use App\Jobs\PollShopifyBulkOperation;
use App\Jobs\StartShopifyCatalogImport;
use App\Jobs\StartShopifyCustomerOrderImport;
use App\Jobs\SyncShopifyCatalogResource;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Team;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'services.shopify.client_secret' => 'client-secret',
        'services.shopify.api_version' => '2026-07',
    ]);
});

it('starts the customer bulk import and records both customer and recent order counts', function () {
    $this->travelTo(Carbon::parse('2026-09-29 12:00:00'));
    $shop = customerOrderShop();
    $graphqlUrl = customerOrderGraphqlUrl();
    $firstOperationId = 'gid://shopify/BulkOperation/700001';
    $startedCustomer = ['data' => ['bulkOperationRunQuery' => [
        'bulkOperation' => ['id' => $firstOperationId, 'status' => 'CREATED'],
        'userErrors' => [],
    ]]];
    $shop->forceFill(['bulk_operation_kind' => ShopifyBulkOperationKind::Customers->value])->save();
    Queue::fake([PollShopifyBulkOperation::class]);
    Http::preventStrayRequests();
    Http::fake([
        $graphqlUrl => Http::sequence()
            ->push(customerOrderFixtureJson('customer-counts.graphql.json'))
            ->push($startedCustomer),
    ]);

    (new StartShopifyCustomerOrderImport($shop->getKey(), ShopifyBulkOperationKind::Customers))
        ->handle(app(EcommerceAdapter::class));

    expect($shop->fresh()->bulk_operation_id)->toBe($firstOperationId)
        ->and($shop->fresh()->bulk_operation_kind)->toBe('customers')
        ->and($shop->fresh()->sync_total_count)->toBe(2)
        ->and($shop->fresh()->sync_progress)->toBe(33);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['variables']['query'] ?? '', 'customers {'));
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && ($request->data()['variables']['orderQuery'] ?? null) === 'created_at:>=2026-07-31');
    Queue::assertPushed(PollShopifyBulkOperation::class);
});

it('starts orders from the date sixty days ago and records their count', function () {
    $this->travelTo(Carbon::parse('2026-09-29 12:00:00'));
    $shop = customerOrderShop([
        'bulk_operation_kind' => ShopifyBulkOperationKind::Orders->value,
    ]);
    $graphqlUrl = customerOrderGraphqlUrl();
    $operationId = 'gid://shopify/BulkOperation/700002';
    Queue::fake([PollShopifyBulkOperation::class]);
    Http::preventStrayRequests();
    Http::fake([
        $graphqlUrl => Http::sequence()
            ->push(customerOrderFixtureJson('orders-counts.graphql.json'))
            ->push(['data' => ['bulkOperationRunQuery' => [
                'bulkOperation' => ['id' => $operationId, 'status' => 'CREATED'],
                'userErrors' => [],
            ]]]),
    ]);

    (new StartShopifyCustomerOrderImport($shop->getKey(), ShopifyBulkOperationKind::Orders))
        ->handle(app(EcommerceAdapter::class));

    expect($shop->fresh()->bulk_operation_id)->toBe($operationId)
        ->and($shop->fresh()->bulk_operation_kind)->toBe('orders')
        ->and($shop->fresh()->sync_total_count)->toBe(1);

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && ($request->data()['variables']['orderQuery'] ?? null) === 'created_at:>=2026-07-31');
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['variables']['query'] ?? '', 'orders(query: "created_at:>=2026-07-31")'));
    Queue::assertPushed(PollShopifyBulkOperation::class);
});

it('imports customers before orders and marks the store synced after the last phase', function () {
    $shop = customerOrderShop(['country_code' => 'BR']);
    $contact = Contact::factory()->for($shop->team)->create([
        'name' => 'Nome do atendimento',
        'phone' => '+55 (11) 98765-4321',
    ]);
    $operationId = 'gid://shopify/BulkOperation/700011';
    $customerFileUrl = 'https://storage.googleapis.com/shopify/customers-700011.jsonl';
    $shop->forceFill([
        'bulk_operation_kind' => ShopifyBulkOperationKind::Customers->value,
        'bulk_operation_id' => $operationId,
        'sync_total_count' => 2,
    ])->save();
    Queue::fake([StartShopifyCustomerOrderImport::class]);
    Http::preventStrayRequests();
    Http::fake([
        $customerFileUrl => Http::response(customerOrderFixture('customers.jsonl'), 200, ['Content-Type' => 'application/x-ndjson']),
    ]);

    (new ImportShopifyBulkOperation($shop->getKey(), $operationId, $customerFileUrl))
        ->handle(app(ProductEmbeddingSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    $customer = StoreCustomer::withoutGlobalScopes()
        ->where('shop_id', $shop->getKey())
        ->where('external_id', 'gid://shopify/Customer/7001')
        ->sole();
    $phoneLessCustomer = StoreCustomer::withoutGlobalScopes()
        ->where('shop_id', $shop->getKey())
        ->where('external_id', 'gid://shopify/Customer/7002')
        ->sole();

    expect($customer->first_name)->toBe('Mariana')
        ->and($customer->email)->toBe('mariana.costa@example.test')
        ->and($customer->phone_e164)->toBe('+5511987654321')
        ->and($customer->contact_id)->toBe($contact->getKey())
        ->and($phoneLessCustomer->phone_e164)->toBeNull()
        ->and($phoneLessCustomer->contact_id)->toBeNull()
        ->and($contact->fresh()->name)->toBe('Nome do atendimento')
        ->and($shop->fresh()->sync_status)->toBe('importing')
        ->and($shop->fresh()->bulk_operation_id)->toBeNull()
        ->and($shop->fresh()->bulk_operation_kind)->toBe('orders')
        ->and($shop->fresh()->sync_progress)->toBe(66);

    Queue::assertPushed(StartShopifyCustomerOrderImport::class, fn (StartShopifyCustomerOrderImport $job): bool => $job->shopId === $shop->getKey()
        && $job->kind === ShopifyBulkOperationKind::Orders, 1);

    $olderOrder = StoreOrder::factory()->for($shop->team)->for($shop)->create([
        'team_id' => $shop->team_id,
        'shop_id' => $shop->getKey(),
        'ordered_at' => now()->subDays(90),
    ]);
    $orderOperationId = 'gid://shopify/BulkOperation/700012';
    $orderFileUrl = 'https://storage.googleapis.com/shopify/orders-700012.jsonl';
    $shop->forceFill([
        'bulk_operation_id' => $orderOperationId,
        'bulk_operation_kind' => ShopifyBulkOperationKind::Orders->value,
        'sync_total_count' => 1,
    ])->save();
    Http::fake([
        $orderFileUrl => Http::response(customerOrderFixture('orders.jsonl'), 200, ['Content-Type' => 'application/x-ndjson']),
    ]);

    (new ImportShopifyBulkOperation($shop->getKey(), $orderOperationId, $orderFileUrl))
        ->handle(app(ProductEmbeddingSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    $registeredOrder = StoreOrder::withoutGlobalScopes()
        ->where('shop_id', $shop->getKey())
        ->where('external_id', 'gid://shopify/Order/9001')
        ->with('items')
        ->sole();
    $guestOrder = StoreOrder::withoutGlobalScopes()
        ->where('shop_id', $shop->getKey())
        ->where('external_id', 'gid://shopify/Order/9002')
        ->with('items')
        ->sole();

    expect($registeredOrder->store_customer_id)->toBe($customer->getKey())
        ->and($registeredOrder->customer_external_id)->toBe('gid://shopify/Customer/7001')
        ->and($registeredOrder->order_number)->toBe('#1042')
        ->and($registeredOrder->items)->toHaveCount(1)
        ->and($registeredOrder->items->first()->title)->toBe('Tênis de corrida leve')
        ->and($guestOrder->store_customer_id)->toBeNull()
        ->and($guestOrder->customer_external_id)->toBeNull()
        ->and($guestOrder->items)->toHaveCount(1)
        ->and(StoreOrder::withoutGlobalScopes()->whereKey($olderOrder->getKey())->exists())->toBeTrue()
        ->and($shop->fresh()->sync_status)->toBe('synced')
        ->and($shop->fresh()->sync_progress)->toBe(100)
        ->and($shop->fresh()->bulk_operation_kind)->toBeNull()
        ->and($shop->fresh()->last_synced_at)->not->toBeNull();

});

it('fetches current customer data on a webhook and relinks the Contact after a phone change', function () {
    $shop = customerOrderShop(['country_code' => 'BR']);
    $previousContact = Contact::factory()->for($shop->team)->create([
        'name' => 'Contato antigo',
        'phone' => '+55 (11) 98765-4321',
    ]);
    $currentContact = Contact::factory()->for($shop->team)->create([
        'name' => 'Contato atual',
        'phone' => '+55 (21) 99876-5432',
    ]);
    $customer = StoreCustomer::factory()->for($shop->team)->for($shop)->for($previousContact)->create([
        'external_id' => 'gid://shopify/Customer/7001',
        'first_name' => 'Nome antigo',
        'phone_e164' => '+5511987654321',
    ]);
    $order = StoreOrder::factory()->for($shop->team)->for($shop)->for($customer, 'storeCustomer')->create([
        'team_id' => $shop->team_id,
        'shop_id' => $shop->getKey(),
        'store_customer_id' => $customer->getKey(),
        'customer_external_id' => $customer->external_id,
    ]);
    $graphqlUrl = customerOrderGraphqlUrl();
    Http::preventStrayRequests();
    Http::fake([
        $graphqlUrl => Http::sequence()
            ->push(customerOrderFixtureJson('customer-updated.graphql.json'))
            ->push(customerOrderFixtureJson('customer-missing.graphql.json')),
    ]);

    (new SyncShopifyCatalogResource($shop->getKey(), 'customer', $customer->external_id, false))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect($customer->fresh()->first_name)->toBe('Mariana')
        ->and($customer->fresh()->phone_e164)->toBe('+5521998765432')
        ->and($customer->fresh()->contact_id)->toBe($currentContact->getKey())
        ->and($previousContact->fresh()->name)->toBe('Contato antigo')
        ->and($currentContact->fresh()->name)->toBe('Contato atual')
        ->and($order->fresh()->store_customer_id)->toBe($customer->getKey());

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['query'], 'customer(id: $id)')
        && ($request->data()['variables']['id'] ?? null) === $customer->external_id);

    (new SyncShopifyCatalogResource($shop->getKey(), 'customer', $customer->external_id, true))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect(StoreCustomer::withoutGlobalScopes()->whereKey($customer->getKey())->exists())->toBeFalse()
        ->and($order->fresh()->store_customer_id)->toBeNull()
        ->and($order->fresh()->customer_external_id)->toBeNull();
});

it('treats an order delete notice as a request to fetch current order state and deletes a missing order', function () {
    $shop = customerOrderShop();
    $customer = StoreCustomer::factory()->for($shop->team)->for($shop)->create([
        'external_id' => 'gid://shopify/Customer/7001',
    ]);
    $order = StoreOrder::factory()->create([
        'team_id' => $shop->team_id,
        'shop_id' => $shop->getKey(),
        'store_customer_id' => $customer->getKey(),
        'external_id' => 'gid://shopify/Order/9001',
        'customer_external_id' => $customer->external_id,
        'order_number' => '#old',
    ]);
    $oldItem = StoreOrderItem::factory()->for($shop->team)->for($shop)->for($order, 'order')->create([
        'team_id' => $shop->team_id,
        'shop_id' => $shop->getKey(),
        'store_order_id' => $order->getKey(),
        'external_id' => 'gid://shopify/LineItem/old',
        'product_variant_id' => null,
    ]);
    $graphqlUrl = customerOrderGraphqlUrl();
    Http::preventStrayRequests();
    Http::fake([
        $graphqlUrl => Http::sequence()
            ->push(customerOrderFixtureJson('order-page-one.graphql.json'))
            ->push(customerOrderFixtureJson('order-page-two.graphql.json'))
            ->push(customerOrderFixtureJson('order-missing.graphql.json')),
    ]);

    (new SyncShopifyCatalogResource($shop->getKey(), 'order', $order->external_id, true))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect($order->fresh()->order_number)->toBe('#1042')
        ->and($order->fresh()->items)->toHaveCount(2)
        ->and(StoreOrderItem::withoutGlobalScopes()->whereKey($oldItem->getKey())->exists())->toBeFalse();

    Http::assertSentCount(2);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === $graphqlUrl
        && str_contains($request->data()['query'], 'lineItems(first: 250')
        && ($request->data()['variables']['lineItemsAfter'] ?? null) === 'line-item-cursor-1');

    (new SyncShopifyCatalogResource($shop->getKey(), 'order', $order->external_id, true))
        ->handle(app(EcommerceAdapter::class), app(ShopifyCatalogSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect(StoreOrder::withoutGlobalScopes()->whereKey($order->getKey())->exists())->toBeFalse()
        ->and(StoreOrderItem::withoutGlobalScopes()->where('store_order_id', $order->getKey())->exists())->toBeFalse();
});

it('does not let an earlier phase completion advance a later phase', function () {
    $shop = customerOrderShop([
        'sync_status' => 'importing',
        'bulk_operation_id' => 'gid://shopify/BulkOperation/700022',
        'bulk_operation_kind' => ShopifyBulkOperationKind::Orders->value,
    ]);

    (new ImportShopifyBulkOperation(
        $shop->getKey(),
        'gid://shopify/BulkOperation/700021',
        null,
    ))->handle(app(ProductEmbeddingSynchronizer::class), app(ShopifyCustomerOrderSynchronizer::class));

    expect($shop->fresh()->sync_status)->toBe('importing')
        ->and($shop->fresh()->bulk_operation_id)->toBe('gid://shopify/BulkOperation/700022')
        ->and($shop->fresh()->bulk_operation_kind)->toBe('orders');
});

it('ignores a late catalog start once a customer phase is active', function () {
    $shop = customerOrderShop([
        'sync_status' => 'importing',
        'bulk_operation_kind' => ShopifyBulkOperationKind::Customers->value,
    ]);
    Http::preventStrayRequests();
    Http::fake();

    (new StartShopifyCatalogImport($shop->getKey()))->handle(app(EcommerceAdapter::class));

    expect($shop->fresh()->bulk_operation_id)->toBeNull()
        ->and($shop->fresh()->bulk_operation_kind)->toBe('customers')
        ->and($shop->fresh()->sync_status)->toBe('importing');
    Http::assertNothingSent();
});

function customerOrderShop(array $shopAttributes = []): Shop
{
    $team = Team::factory()->create();
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
        'country_code' => 'BR',
        'currency_code' => 'BRL',
        ...$shopAttributes,
    ]);
}

function customerOrderGraphqlUrl(): string
{
    return 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json';
}

function customerOrderFixture(string $name): string
{
    return file_get_contents(base_path('tests/Fixtures/ecommerce/shopify/'.$name));
}

function customerOrderFixtureJson(string $name): array
{
    return json_decode(customerOrderFixture($name), true, flags: JSON_THROW_ON_ERROR);
}
