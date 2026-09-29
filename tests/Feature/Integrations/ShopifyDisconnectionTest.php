<?php

use App\Ai\Agents\WhatsAppAgent;
use App\Ai\Tools\SearchProducts;
use App\Ai\Tools\SendPurchaseLink;
use App\Ecommerce\ProductCatalogSearch;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\AgentContactMemory;
use App\Models\AgentVersion;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseAttribution;
use App\Models\Shop;
use App\Models\ShopifyPendingInstallation;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function (): void {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
        'services.shopify.client_secret' => 'shopify-client-secret',
        'services.shopify.api_version' => '2026-07',
    ]);
});

it('uninstalls Shopify and removes the complete mirror while retaining contact history', function () {
    $records = shopifyDisconnectionRecords();
    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => ['appUninstall' => ['userErrors' => []]],
        ]),
    ]);

    $this->actingAs($records['owner'])
        ->delete(route('integrations.destroy', $records['integration']))
        ->assertRedirect(route('integrations.index'));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://alpha.myshopify.com/admin/api/2026-07/graphql.json'
        && str_contains($request->data()['query'] ?? '', 'appUninstall')
        && $request->hasHeader('X-Shopify-Access-Token', 'active-access-token'));
    $this->assertSoftDeleted('shops', ['id' => $records['shop']->getKey()]);
    $this->assertSoftDeleted('integrations', ['id' => $records['integration']->getKey()]);
    shopifyAssertMirrorWasRemoved($this, $records['shop']);

    expect($records['integration']->fresh()->status)->toBe('disconnected')
        ->and($records['integration']->fresh()->credentials)->toBe([])
        ->and($records['agent']->fresh()->search_products)->toBeFalse()
        ->and($records['agent']->fresh()->send_purchase_link)->toBeFalse()
        ->and($records['agent']->fresh()->shop_tools_enabled)->toBeFalse()
        ->and($records['otherAgent']->fresh()->search_products)->toBeTrue()
        ->and($records['otherAgent']->fresh()->send_purchase_link)->toBeTrue()
        ->and($records['contact']->fresh())->not->toBeNull()
        ->and($records['contact']->fresh()->storeCustomer)->toBeNull()
        ->and($records['memory']->fresh()->content)->toBe('Memória preservada')
        ->and($records['message']->fresh()->content)->toBe('Mensagem preservada')
        ->and($records['owner']->company()->getKey())->toBe($records['team']->getKey());

    $records['shop']->restore();
    $records['integration']->restore();
    $publishedVersion = $records['agentVersion']->fresh();
    $shopConnected = $records['team']->shops()->exists();
    $channelAgent = new WhatsAppAgent(
        $records['agent']->fresh()->load('team'),
        collect(),
        app(AgentInstructionTemplate::class),
        app(KnowledgeBaseSearch::class),
        app(ProductCatalogSearch::class),
    );
    $channelTools = collect($channelAgent->tools());

    expect($publishedVersion->ecommerce_tools)->toBe([
        'search_products' => true,
        'send_purchase_link' => true,
    ])
        ->and($publishedVersion->shouldSearchProducts($shopConnected, $records['agent']->fresh()->shop_tools_enabled))->toBeFalse()
        ->and($publishedVersion->shouldSendPurchaseLink($shopConnected, $records['agent']->fresh()->shop_tools_enabled))->toBeFalse();
    expect($channelTools->contains(fn ($tool): bool => $tool instanceof SearchProducts))->toBeFalse()
        ->and($channelTools->contains(fn ($tool): bool => $tool instanceof SendPurchaseLink))->toBeFalse();
});

it('keeps the Shopify mirror when the remote uninstall fails', function () {
    $records = shopifyDisconnectionRecords();
    Http::preventStrayRequests();
    Http::fake([
        'https://alpha.myshopify.com/admin/api/2026-07/graphql.json' => Http::response([
            'data' => ['appUninstall' => ['userErrors' => [[
                'field' => null,
                'message' => 'Uninstall could not be completed.',
            ]]]],
        ]),
    ]);

    $this->actingAs($records['owner'])
        ->from(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->delete(route('integrations.destroy', $records['integration']))
        ->assertRedirect(route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']))
        ->assertSessionHasErrors('integration');

    expect(Shop::query()->whereKey($records['shop']->getKey())->exists())->toBeTrue()
        ->and(Integration::query()->whereKey($records['integration']->getKey())->exists())->toBeTrue()
        ->and(Product::query()->where('shop_id', $records['shop']->getKey())->exists())->toBeTrue()
        ->and($records['agent']->fresh()->search_products)->toBeTrue()
        ->and($records['agent']->fresh()->send_purchase_link)->toBeTrue();
});

it('applies the same complete cleanup for repeated app uninstall webhooks', function () {
    $records = shopifyDisconnectionRecords();
    $body = '{}';

    foreach ([1, 2] as $delivery) {
        shopifySendWebhook($this, 'app/uninstalled', 'alpha.myshopify.com', $body)
            ->assertOk();
    }

    $this->assertSoftDeleted('shops', ['id' => $records['shop']->getKey()]);
    $this->assertSoftDeleted('integrations', ['id' => $records['integration']->getKey()]);
    shopifyAssertMirrorWasRemoved($this, $records['shop']);

    expect($records['agent']->fresh()->search_products)->toBeFalse()
        ->and($records['agent']->fresh()->send_purchase_link)->toBeFalse()
        ->and($records['agent']->fresh()->shop_tools_enabled)->toBeFalse()
        ->and($records['contact']->fresh())->not->toBeNull()
        ->and($records['memory']->fresh()->content)->toBe('Memória preservada')
        ->and($records['message']->fresh()->content)->toBe('Mensagem preservada')
        ->and(ShopifyPendingInstallation::query()->where('domain', 'alpha.myshopify.com')->exists())->toBeFalse();
});

function shopifyDisconnectionRecords(): array
{
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'active-access-token',
            'refresh_token' => 'active-refresh-token',
            'access_token_expires_at' => now()->addHour()->timestamp,
            'refresh_token_expires_at' => now()->addDays(30)->timestamp,
        ],
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create([
        'domain' => 'alpha.myshopify.com',
    ]);
    $contact = Contact::factory()->for($team)->create(['name' => 'Pessoa atendida']);
    $agent = Agent::factory()->for($team)->create([
        'search_products' => true,
        'send_purchase_link' => true,
    ]);
    $agentVersion = AgentVersion::factory()->for($team)->for($agent)->create([
        'ecommerce_tools' => [
            'search_products' => true,
            'send_purchase_link' => true,
        ],
    ]);
    $channel = $agent->channels()->create(['team_id' => $team->getKey(), 'type' => 'whatsapp']);
    $message = Message::factory()->for($contact)->for($channel)->create([
        'content' => 'Mensagem preservada',
    ]);
    $memory = AgentContactMemory::factory()->for($team)->for($agent)->for($contact)->create([
        'content' => 'Memória preservada',
    ]);
    $customer = StoreCustomer::factory()->for($team)->for($shop)->for($contact)->create([
        'external_id' => 'gid://shopify/Customer/701',
    ]);
    $product = Product::factory()->for($team)->for($shop)->create();
    $variant = ProductVariant::factory()->for($team)->for($shop)->for($product)->create();
    $category = Category::factory()->for($team)->for($shop)->create();
    $category->products()->attach($product->getKey(), ['team_id' => $team->getKey()]);
    $order = StoreOrder::factory()->for($team)->for($shop)->for($customer, 'storeCustomer')->create([
        'customer_external_id' => $customer->external_id,
    ]);
    StoreOrderItem::factory()->for($team)->for($shop)->for($order, 'order')->for($variant, 'productVariant')->create();
    $attribution = PurchaseAttribution::factory()
        ->for($team)
        ->for($shop)
        ->for($contact)
        ->for($agent)
        ->for($channel)
        ->create();
    $order->forceFill(['purchase_attribution_id' => $attribution->getKey()])->save();
    ShopifyPendingInstallation::factory()->create(['domain' => $shop->domain]);
    $otherTeam = Team::factory()->create();
    $otherAgent = Agent::factory()->for($otherTeam)->create([
        'search_products' => true,
        'send_purchase_link' => true,
    ]);

    return compact(
        'owner',
        'team',
        'integration',
        'shop',
        'contact',
        'agent',
        'agentVersion',
        'channel',
        'message',
        'memory',
        'customer',
        'product',
        'variant',
        'category',
        'order',
        'attribution',
        'otherAgent',
    );
}

function shopifyAssertMirrorWasRemoved(TestCase $test, Shop $shop): void
{
    foreach ([
        'products',
        'product_variants',
        'categories',
        'store_customers',
        'store_orders',
        'store_order_items',
        'purchase_attributions',
    ] as $table) {
        $test->assertSame(0, DB::table($table)->where('shop_id', $shop->getKey())->count(), $table.' should be empty for the disconnected shop.');
    }

    $test->assertSame(0, DB::table('category_product')->count());
}

function shopifySendWebhook(TestCase $test, string $topic, string $domain, string $body, bool $validSignature = true): TestResponse
{
    $signature = base64_encode(hash_hmac('sha256', $body, 'shopify-client-secret', true));
    if (! $validSignature) {
        $signature = base64_encode(str_repeat('x', 32));
    }

    return $test->call('POST', route('webhooks.shopify'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $domain,
        'HTTP_X_SHOPIFY_TOPIC' => $topic,
    ], $body);
}
