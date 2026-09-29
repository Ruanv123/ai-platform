<?php

use App\Ecommerce\ShopifyComplianceWebhookHandler;
use App\Jobs\Integrations\ProcessShopifyComplianceWebhook;
use App\Mail\ShopifyCustomerDataExport;
use App\Models\Agent;
use App\Models\AgentContactMemory;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Message;
use App\Models\PurchaseAttribution;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'services.shopify.client_secret' => 'shopify-client-secret',
    ]);
});

it('returns 401 for compliance webhooks with an invalid signature', function (string $topic, string $body) {
    Queue::fake([ProcessShopifyComplianceWebhook::class]);

    shopifyComplianceWebhook($this, $topic, $body, false)->assertUnauthorized();

    Queue::assertNothingPushed();
})->with([
    'customer data request' => ['customers/data_request', '{"customer":{"id":901}}'],
    'customer redact' => ['customers/redact', '{"customer":{"id":901}}'],
    'shop redact' => ['shop/redact', '{}'],
]);

it('deletes only the redacted customer and orders while preserving the Contact and its history', function () {
    $records = shopifyComplianceRecords();
    $targetContact = Contact::factory()->for($records['team'])->create([
        'name' => 'Contato Shopify',
    ]);
    $otherContact = Contact::factory()->for($records['team'])->create([
        'name' => 'Outro contato',
    ]);
    $targetCustomer = StoreCustomer::factory()->for($records['team'])->for($records['shop'])->for($targetContact)->create([
        'external_id' => 'gid://shopify/Customer/901',
    ]);
    $otherCustomer = StoreCustomer::factory()->for($records['team'])->for($records['shop'])->for($otherContact)->create([
        'external_id' => 'gid://shopify/Customer/902',
    ]);
    $targetOrder = shopifyComplianceOrder($records['team'], $records['shop'], $targetCustomer);
    $otherOrder = shopifyComplianceOrder($records['team'], $records['shop'], $otherCustomer);
    $targetItem = shopifyComplianceOrderItem($records['team'], $records['shop'], $targetOrder, 'gid://shopify/LineItem/901');
    $otherItem = shopifyComplianceOrderItem($records['team'], $records['shop'], $otherOrder, 'gid://shopify/LineItem/902');
    $attribution = PurchaseAttribution::factory()
        ->for($records['team'])
        ->for($records['shop'])
        ->for($targetContact)
        ->for($records['agent'])
        ->for($records['channel'])
        ->create();
    $targetOrder->forceFill(['purchase_attribution_id' => $attribution->getKey()])->save();
    $targetMessage = Message::factory()->for($targetContact)->for($records['channel'])->create([
        'content' => 'Histórico do WhatsApp',
    ]);
    $body = json_encode(['shop_id' => 123, 'customer' => ['id' => 901]], JSON_THROW_ON_ERROR);
    Queue::fake([ProcessShopifyComplianceWebhook::class]);

    shopifyComplianceWebhook($this, 'customers/redact', $body)->assertOk();

    Queue::assertPushed(ProcessShopifyComplianceWebhook::class, fn (ProcessShopifyComplianceWebhook $job): bool => $job->topic === 'customers/redact'
        && $job->shopDomain === 'alpha.myshopify.com'
        && $job->customerExternalId === 'gid://shopify/Customer/901'
        && ! property_exists($job, 'payload'));
    (new ProcessShopifyComplianceWebhook('customers/redact', 'alpha.myshopify.com', 'gid://shopify/Customer/901'))
        ->handle(app(ShopifyComplianceWebhookHandler::class));

    $this->assertDatabaseMissing('store_customers', ['id' => $targetCustomer->getKey()]);
    $this->assertDatabaseMissing('store_orders', ['id' => $targetOrder->getKey()]);
    $this->assertDatabaseMissing('store_order_items', ['id' => $targetItem->getKey()]);
    $this->assertDatabaseHas('store_customers', ['id' => $otherCustomer->getKey()]);
    $this->assertDatabaseHas('store_orders', ['id' => $otherOrder->getKey()]);
    $this->assertDatabaseHas('store_order_items', ['id' => $otherItem->getKey()]);
    $this->assertDatabaseHas('purchase_attributions', ['id' => $attribution->getKey()]);

    expect($targetContact->fresh())->not->toBeNull()
        ->and($targetContact->fresh()->storeCustomer)->toBeNull()
        ->and($targetMessage->fresh()->content)->toBe('Histórico do WhatsApp');
});

it('emails the Owner a JSON attachment with the customer record, orders, and attributions', function () {
    $records = shopifyComplianceRecords();
    $contact = Contact::factory()->for($records['team'])->create(['name' => 'Cliente da loja']);
    $customer = StoreCustomer::factory()->for($records['team'])->for($records['shop'])->for($contact)->create([
        'external_id' => 'gid://shopify/Customer/903',
        'first_name' => 'Marina',
        'last_name' => 'Silva',
        'email' => 'marina@example.test',
    ]);
    $order = shopifyComplianceOrder($records['team'], $records['shop'], $customer, [
        'external_id' => 'gid://shopify/Order/9903',
        'order_number' => '#903',
        'total_amount' => '129.90',
        'currency_code' => 'BRL',
    ]);
    shopifyComplianceOrderItem($records['team'], $records['shop'], $order, 'gid://shopify/LineItem/903');
    $attribution = PurchaseAttribution::factory()
        ->for($records['team'])
        ->for($records['shop'])
        ->for($contact)
        ->for($records['agent'])
        ->for($records['channel'])
        ->create();
    $order->forceFill(['purchase_attribution_id' => $attribution->getKey()])->save();
    $body = json_encode(['customer' => ['id' => '903']], JSON_THROW_ON_ERROR);
    Queue::fake([ProcessShopifyComplianceWebhook::class]);
    Mail::fake();

    shopifyComplianceWebhook($this, 'customers/data_request', $body)->assertOk();

    Queue::assertPushed(ProcessShopifyComplianceWebhook::class, fn (ProcessShopifyComplianceWebhook $job): bool => $job->topic === 'customers/data_request'
        && $job->customerExternalId === 'gid://shopify/Customer/903');
    (new ProcessShopifyComplianceWebhook('customers/data_request', 'alpha.myshopify.com', 'gid://shopify/Customer/903'))
        ->handle(app(ShopifyComplianceWebhookHandler::class));

    $sentMailable = null;
    Mail::assertSent(ShopifyCustomerDataExport::class, function (ShopifyCustomerDataExport $mail) use (&$sentMailable, $records): bool {
        $sentMailable = $mail;

        return $mail->hasTo($records['owner']->email);
    });

    $export = json_decode($sentMailable->jsonContent, true, 512, JSON_THROW_ON_ERROR);
    $attachment = $sentMailable->attachments()[0];

    expect($export['shop_domain'])->toBe('alpha.myshopify.com')
        ->and($export['customer']['email'])->toBe('marina@example.test')
        ->and($export['customer']['contact_id'])->toBe($contact->getKey())
        ->and($export['orders'][0]['order_number'])->toBe('#903')
        ->and($export['orders'][0]['items'][0]['title'])->toBe('Tênis de corrida')
        ->and($export['orders'][0]['attribution']['contact_id'])->toBe($contact->getKey())
        ->and($export['orders'][0]['attribution']['agent_name'])->toBe($records['agent']->name)
        ->and($export['orders'][0]['attribution']['channel_type'])->toBe('whatsapp')
        ->and($attachment->as)->toBe('shopify-customer-data.json')
        ->and($attachment->mime)->toBe('application/json');
});

it('hard deletes an already disconnected shop when Shopify sends shop redact repeatedly', function () {
    $records = shopifyComplianceRecords();
    $customer = StoreCustomer::factory()->for($records['team'])->for($records['shop'])->create();
    $order = shopifyComplianceOrder($records['team'], $records['shop'], $customer);
    $body = '{}';
    Queue::fake([ProcessShopifyComplianceWebhook::class]);

    shopifyComplianceWebhook($this, 'app/uninstalled', $body)->assertOk();
    shopifyComplianceWebhook($this, 'shop/redact', $body)->assertOk();
    Queue::assertPushed(ProcessShopifyComplianceWebhook::class, fn (ProcessShopifyComplianceWebhook $job): bool => $job->topic === 'shop/redact'
        && $job->shopDomain === 'alpha.myshopify.com'
        && $job->customerExternalId === null);
    (new ProcessShopifyComplianceWebhook('shop/redact', 'alpha.myshopify.com', null))
        ->handle(app(ShopifyComplianceWebhookHandler::class));

    shopifyComplianceWebhook($this, 'shop/redact', $body)->assertOk();
    (new ProcessShopifyComplianceWebhook('shop/redact', 'alpha.myshopify.com', null))
        ->handle(app(ShopifyComplianceWebhookHandler::class));

    $this->assertDatabaseMissing('shops', ['id' => $records['shop']->getKey()]);
    $this->assertDatabaseMissing('integrations', ['id' => $records['integration']->getKey()]);
    $this->assertDatabaseMissing('store_customers', ['id' => $customer->getKey()]);
    $this->assertDatabaseMissing('store_orders', ['id' => $order->getKey()]);
    $this->assertDatabaseHas('contacts', ['id' => $records['contact']->getKey()]);
    $this->assertDatabaseHas('messages', ['id' => $records['message']->getKey()]);
    $this->assertDatabaseHas('agent_contact_memories', ['id' => $records['memory']->getKey()]);
});

function shopifyComplianceRecords(): array
{
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
        'credentials' => ['access_token' => 'active-access-token'],
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create([
        'domain' => 'alpha.myshopify.com',
    ]);
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->for($agent)->create();
    $contact = Contact::factory()->for($team)->create();
    $message = Message::factory()->for($contact)->for($channel)->create();
    $memory = AgentContactMemory::factory()->for($team)->for($agent)->for($contact)->create();

    return compact('owner', 'team', 'integration', 'shop', 'agent', 'channel', 'contact', 'message', 'memory');
}

function shopifyComplianceOrder(Team $team, Shop $shop, StoreCustomer $customer, array $overrides = []): StoreOrder
{
    return StoreOrder::factory()
        ->for($team)
        ->for($shop)
        ->for($customer, 'storeCustomer')
        ->create(array_replace([
            'customer_external_id' => $customer->external_id,
        ], $overrides));
}

function shopifyComplianceOrderItem(Team $team, Shop $shop, StoreOrder $order, string $externalId): StoreOrderItem
{
    return StoreOrderItem::query()->create([
        'team_id' => $team->getKey(),
        'shop_id' => $shop->getKey(),
        'store_order_id' => $order->getKey(),
        'product_variant_id' => null,
        'external_id' => $externalId,
        'title' => 'Tênis de corrida',
        'quantity' => 1,
        'unit_price' => '129.90',
    ]);
}

function shopifyComplianceWebhook(TestCase $test, string $topic, string $body, bool $validSignature = true): TestResponse
{
    $signature = base64_encode(hash_hmac('sha256', $body, 'shopify-client-secret', true));
    if (! $validSignature) {
        $signature = base64_encode(str_repeat('x', 32));
    }

    return $test->call('POST', route('webhooks.shopify'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'alpha.myshopify.com',
        'HTTP_X_SHOPIFY_TOPIC' => $topic,
    ], $body);
}
