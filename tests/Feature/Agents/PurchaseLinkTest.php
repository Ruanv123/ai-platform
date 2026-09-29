<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Ai\Agents\WhatsAppAgent;
use App\Ai\Tools\SendPurchaseLink;
use App\Ecommerce\ProductCatalogSearch;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseAttribution;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request as ToolRequest;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
});

function purchaseLinkShop(Team $team): Shop
{
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
    ]);

    return Shop::factory()->for($team)->for($integration)->create([
        'domain' => 'alpha.myshopify.com',
    ]);
}

function purchaseLinkProduct(Team $team, Shop $shop, array $overrides = []): Product
{
    return Product::factory()->for($team)->for($shop)->create($overrides);
}

function purchaseLinkPlaygroundDetails(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lia',
        'objective' => AgentObjective::Sales->value,
        'search_products' => false,
        'send_purchase_link' => true,
        'tone' => AgentTone::Friendly->value,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short->value,
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => [],
        'rules' => [],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
        'history' => [],
        'message' => 'Quero fechar a compra.',
    ], $overrides);
}

function purchaseLinkResultFromStream(string $content): array
{
    foreach (explode("\n\n", $content) as $block) {
        if (! str_starts_with($block, 'data: {')) {
            continue;
        }

        $event = json_decode(substr($block, 6), true, flags: JSON_THROW_ON_ERROR);

        if (($event['type'] ?? null) !== 'tool_result' || ($event['tool_name'] ?? null) !== 'send_purchase_link') {
            continue;
        }

        return json_decode((string) ($event['result'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
    }

    return [];
}

function purchaseLinkToolResult(SendPurchaseLink $tool, array $items): array
{
    return json_decode((string) $tool->handle(new ToolRequest(['items' => $items])), true, flags: JSON_THROW_ON_ERROR);
}

it('builds a multi-variant cart permalink in the Playground without attribution', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $shop = purchaseLinkShop($team);
    $product = purchaseLinkProduct($team, $shop);
    $firstVariant = ProductVariant::factory()->for($team)->for($shop)->for($product)->create([
        'external_id' => 'gid://shopify/ProductVariant/1001',
    ]);
    $secondVariant = ProductVariant::factory()->for($team)->for($shop)->for($product)->create([
        'external_id' => 'gid://shopify/ProductVariant/1002',
    ]);
    Http::fake();
    Http::preventStrayRequests();
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'send_purchase_link', [
            'items' => [
                ['variant_id' => $firstVariant->id, 'quantity' => 2],
                ['variant_id' => $secondVariant->id, 'quantity' => 1],
            ],
        ]),
        'Aqui está seu link para concluir a compra.',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        purchaseLinkPlaygroundDetails(),
    );

    $result = purchaseLinkResultFromStream($response->streamedContent());
    parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);

    $response->assertOk();
    expect($result['ok'])->toBeTrue()
        ->and(parse_url($result['url'], PHP_URL_HOST))->toBe('alpha.myshopify.com')
        ->and(parse_url($result['url'], PHP_URL_PATH))->toBe('/cart/1001:2,1002:1')
        ->and($query['utm_source'])->toBe('ia-platform')
        ->and($query['utm_medium'])->toBe('whatsapp')
        ->and($query['utm_campaign'])->toBe('agente-ia')
        ->and(isset($query['attributes']))->toBeFalse()
        ->and(str_contains($result['url'], $firstVariant->id))->toBeFalse()
        ->and(str_contains($result['url'], 'gid://shopify'))->toBeFalse()
        ->and(PurchaseAttribution::query()->count())->toBe(0);
    Http::assertNothingSent();
    PlaygroundAgent::assertPrompted(fn ($prompt): bool => collect($prompt->agent->tools())
        ->contains(fn ($tool): bool => $tool instanceof SendPurchaseLink));
});

it('shows the purchase link switch by default only when a Shopify shop is connected', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    purchaseLinkShop($team);
    $agent = Agent::factory()->for($team)->create([
        'objective' => AgentObjective::Sales,
        'send_purchase_link' => null,
    ]);

    $this->withoutVite()->actingAs($user)
        ->get(route('agents.edit', $agent))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('agents/edit')
            ->where('shopConnected', true)
            ->where('purchaseLinkConfigured', false)
            ->where('agent.sendPurchaseLink', true),
        );
});

it('does not expose purchase link generation without a connected shop', function () {
    $user = User::factory()->withTeam()->create();
    PlaygroundAgent::fake(['Não há uma Loja conectada.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        purchaseLinkPlaygroundDetails(),
    );

    $response->assertOk();
    $response->streamedContent();
    PlaygroundAgent::assertPrompted(fn ($prompt): bool => collect($prompt->agent->tools())
        ->every(fn ($tool): bool => $tool->name() !== 'send_purchase_link'));
});

it('refuses hidden or unavailable variants without recording an attribution', function () {
    $team = Team::factory()->create();
    $shop = purchaseLinkShop($team);
    $agent = Agent::factory()->for($team)->create();
    $hiddenProduct = purchaseLinkProduct($team, $shop, ['online_store_published' => false]);
    $hiddenVariant = ProductVariant::factory()->for($team)->for($shop)->for($hiddenProduct)->create([
        'external_id' => 'gid://shopify/ProductVariant/2001',
    ]);
    $visibleProduct = purchaseLinkProduct($team, $shop);
    $unavailableVariant = ProductVariant::factory()->for($team)->for($shop)->for($visibleProduct)->create([
        'external_id' => 'gid://shopify/ProductVariant/2002',
        'available' => false,
        'continues_selling_when_out_of_stock' => false,
    ]);
    $contact = Contact::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->for($agent)->create();
    $tool = new SendPurchaseLink($team, $agent, $contact, $channel);

    $hiddenResult = purchaseLinkToolResult($tool, [['variant_id' => $hiddenVariant->id, 'quantity' => 1]]);
    $unavailableResult = purchaseLinkToolResult($tool, [['variant_id' => $unavailableVariant->id, 'quantity' => 1]]);

    expect($hiddenResult['ok'])->toBeFalse()
        ->and($hiddenResult['message'])->toContain('não está publicado')
        ->and($unavailableResult['ok'])->toBeFalse()
        ->and($unavailableResult['message'])->toContain('indisponível')
        ->and(PurchaseAttribution::query()->count())->toBe(0);
});

it('allows a variant that the shop permits customers to buy without stock', function () {
    $team = Team::factory()->create();
    $shop = purchaseLinkShop($team);
    $agent = Agent::factory()->for($team)->create();
    $product = purchaseLinkProduct($team, $shop);
    $variant = ProductVariant::factory()->for($team)->for($shop)->for($product)->create([
        'external_id' => 'gid://shopify/ProductVariant/2501',
        'available' => false,
        'continues_selling_when_out_of_stock' => true,
    ]);
    $tool = new SendPurchaseLink($team, $agent, null, null);

    $result = purchaseLinkToolResult($tool, [['variant_id' => $variant->id, 'quantity' => 1]]);

    expect($result['ok'])->toBeTrue()
        ->and(parse_url($result['url'], PHP_URL_PATH))->toBe('/cart/2501:1')
        ->and(PurchaseAttribution::query()->count())->toBe(0);
});

it('records one opaque channel attribution and prefills the linked store customer email', function () {
    $team = Team::factory()->create();
    $shop = purchaseLinkShop($team);
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->for($agent)->create();
    $contact = Contact::factory()->for($team)->create();
    StoreCustomer::factory()->for($team)->for($shop)->for($contact)->create([
        'email' => 'cliente@example.com',
    ]);
    $product = purchaseLinkProduct($team, $shop);
    $variant = ProductVariant::factory()->for($team)->for($shop)->for($product)->create([
        'external_id' => 'gid://shopify/ProductVariant/3001',
    ]);
    Http::fake();
    Http::preventStrayRequests();
    $tool = new SendPurchaseLink($team, $agent, $contact, $channel);

    $result = purchaseLinkToolResult($tool, [['variant_id' => $variant->id, 'quantity' => 3]]);
    parse_str((string) parse_url($result['url'], PHP_URL_QUERY), $query);
    $token = $query['attributes']['_platform_attribution'];

    expect($result['ok'])->toBeTrue()
        ->and($query['checkout']['email'])->toBe('cliente@example.com')
        ->and(strlen($token))->toBeGreaterThan(40)
        ->and(str_contains($result['url'], $agent->id))->toBeFalse()
        ->and(str_contains($result['url'], $channel->id))->toBeFalse()
        ->and(str_contains($result['url'], $variant->id))->toBeFalse()
        ->and(PurchaseAttribution::query()->count())->toBe(1);
    $attribution = PurchaseAttribution::query()->sole();
    expect($attribution->team_id)->toBe($team->getKey())
        ->and($attribution->shop_id)->toBe($shop->getKey())
        ->and($attribution->contact_id)->toBe($contact->getKey())
        ->and($attribution->agent_id)->toBe($agent->getKey())
        ->and($attribution->channel_id)->toBe($channel->getKey())
        ->and($attribution->token_hash)->toBe(hash('sha256', $token));
    Http::assertNothingSent();
});

it('records attribution when the published channel agent calls the purchase link tool', function () {
    $team = Team::factory()->create();
    $shop = purchaseLinkShop($team);
    $agent = Agent::factory()->for($team)->create([
        'objective' => AgentObjective::Sales,
        'send_purchase_link' => true,
    ]);
    $agent->versions()->create([
        'team_id' => $team->getKey(),
        'number' => 1,
        'objective' => AgentObjective::Sales,
        'ecommerce_tools' => [
            'search_products' => false,
            'send_purchase_link' => true,
        ],
        'published_at' => now(),
    ]);
    $channel = Channel::factory()->for($team)->for($agent)->create();
    $contact = Contact::factory()->for($team)->create();
    $product = purchaseLinkProduct($team, $shop);
    $variant = ProductVariant::factory()->for($team)->for($shop)->for($product)->create([
        'external_id' => 'gid://shopify/ProductVariant/4001',
    ]);
    WhatsAppAgent::fake([
        new ToolCall((string) Str::uuid7(), 'send_purchase_link', [
            'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ]),
        'Enviei o link de compra.',
    ]);
    $channelAgent = new WhatsAppAgent(
        $agent,
        collect(),
        app(AgentInstructionTemplate::class),
        app(KnowledgeBaseSearch::class),
        app(ProductCatalogSearch::class),
        null,
        $contact,
        null,
        $channel,
    );

    $response = $channelAgent->prompt('Quero comprar esta Variante.');

    expect($response->text)->toBe('Enviei o link de compra.')
        ->and(PurchaseAttribution::query()->count())->toBe(1);
    $attribution = PurchaseAttribution::query()->sole();
    expect($attribution->team_id)->toBe($team->getKey())
        ->and($attribution->shop_id)->toBe($shop->getKey())
        ->and($attribution->contact_id)->toBe($contact->getKey())
        ->and($attribution->agent_id)->toBe($agent->getKey())
        ->and($attribution->channel_id)->toBe($channel->getKey());
    WhatsAppAgent::assertPrompted(fn ($prompt): bool => collect($prompt->agent->tools())
        ->contains(fn ($tool): bool => $tool instanceof SendPurchaseLink));
});
