<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Ai\Agents\ProductTriageAgent;
use App\Ai\Agents\WhatsAppAgent;
use App\Ecommerce\ProductCatalogSearch;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Enums\MessageDirection;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\AgentContactMemory;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\Message;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
});

function productSearchPlaygroundDetails(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lia',
        'objective' => AgentObjective::Support->value,
        'tone' => AgentTone::Friendly->value,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short->value,
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => [],
        'rules' => [],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
        'history' => [],
        'message' => 'Quero um tênis de corrida leve.',
    ], $overrides);
}

function productSearchVector(): array
{
    return array_merge([1.0], array_fill(0, 1535, 0.0));
}

function productSearchShop(Team $team, array $overrides = []): Shop
{
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
    ]);

    return Shop::factory()->for($team)->for($integration)->create($overrides);
}

function productSearchResultsFromStream(string $content): array
{
    $payload = productSearchPayloadFromStream($content);

    return is_array($payload['results'] ?? null) ? $payload['results'] : [];
}

function productSearchPayloadFromStream(string $content): array
{
    foreach (explode("\n\n", $content) as $block) {
        if (! str_starts_with($block, 'data: {')) {
            continue;
        }

        $event = json_decode(substr($block, 6), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($event) || ($event['type'] ?? null) !== 'tool_result' || ($event['tool_name'] ?? null) !== 'search_products') {
            continue;
        }

        if (! is_string($event['result'] ?? null)) {
            return [];
        }

        $result = json_decode($event['result'], true, flags: JSON_THROW_ON_ERROR);

        return is_array($result) ? $result : [];
    }

    return [];
}

function createProductSearchCandidates(Team $team, Shop $shop, int $count, array $embedding): array
{
    $variants = [];

    for ($candidateNumber = 1; $candidateNumber <= $count; $candidateNumber++) {
        $product = Product::factory()->for($team)->for($shop)->create([
            'title' => "Tênis de corrida {$candidateNumber}",
            'description' => 'Leve e confortável para corrida.',
            'embedding' => $embedding,
        ]);
        $variants[] = ProductVariant::factory()->for($team)->for($shop)->for($product)->create([
            'title' => "Azul / {$candidateNumber}",
            'options' => [['name' => 'Tamanho', 'value' => (string) $candidateNumber]],
            'price' => '349.90',
        ]);
    }

    return $variants;
}

it('shows the default product search setting for sales agents only when the shop exists', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    productSearchShop($team, ['sync_status' => 'error']);
    $agent = $team->agents()->create([
        'name' => 'Lia',
        'objective' => AgentObjective::Sales,
        'tone' => AgentTone::Friendly,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short,
        'welcome_message' => 'Olá!',
        'instructions' => [],
        'rules' => [],
    ]);

    $this->withoutVite()->actingAs($user)
        ->get(route('agents.edit', $agent))
        ->assertInertia(fn (Assert $page) => $page
            ->component('agents/edit')
            ->where('shopConnected', true)
            ->where('productSearchConfigured', false)
            ->where('agent.searchProducts', true),
        );
});

it('saves a deliberate product search switch value on the agent configuration', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    productSearchShop($team);
    $agent = $team->agents()->create([
        'name' => 'Lia',
        'objective' => AgentObjective::Sales,
        'tone' => AgentTone::Friendly,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short,
        'welcome_message' => 'Olá!',
        'instructions' => [],
        'rules' => [],
    ]);

    $this->actingAs($user)->patch(route('agents.update', $agent), productSearchPlaygroundDetails([
        'objective' => AgentObjective::Sales->value,
        'search_products' => '0',
    ]))->assertRedirect(route('agents.edit', $agent));

    expect($agent->fresh()->search_products)->toBeFalse()
        ->and($agent->fresh()->shouldSearchProducts(true))->toBeFalse();
});

it('keeps the sales default derived until the switch is changed explicitly', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    productSearchShop($team);
    $agent = $team->agents()->create([
        'name' => 'Lia',
        'objective' => AgentObjective::Sales,
        'tone' => AgentTone::Friendly,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short,
        'welcome_message' => 'Olá!',
        'instructions' => [],
        'rules' => [],
    ]);

    $this->actingAs($user)->patch(route('agents.update', $agent), productSearchPlaygroundDetails([
        'objective' => AgentObjective::Sales->value,
    ]))->assertRedirect(route('agents.edit', $agent));

    expect($agent->fresh()->search_products)->toBeNull()
        ->and($agent->fresh()->shouldSearchProducts(true))->toBeTrue();
});

it('does not expose product search without a shop or when the agent switch is off', function () {
    $user = User::factory()->withTeam()->create();
    PlaygroundAgent::fake(['Resposta sem busca.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        productSearchPlaygroundDetails([
            'objective' => AgentObjective::Sales->value,
            'search_products' => true,
        ]),
    );

    $response->assertOk();
    $response->streamedContent();
    PlaygroundAgent::assertPrompted(fn ($prompt): bool => collect($prompt->agent->tools())
        ->every(fn ($tool): bool => $tool->name() !== 'search_products'));
});

it('does not expose product search when its saved switch is off', function () {
    $user = User::factory()->withTeam()->create();
    productSearchShop($user->company());
    PlaygroundAgent::fake(['Resposta sem busca.']);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        productSearchPlaygroundDetails([
            'objective' => AgentObjective::Sales->value,
            'search_products' => false,
        ]),
    );

    $response->assertOk();
    $response->streamedContent();
    PlaygroundAgent::assertPrompted(fn ($prompt): bool => collect($prompt->agent->tools())
        ->every(fn ($tool): bool => $tool->name() !== 'search_products'));
});

it('searches the shop catalog with semantic ranking and SQL filters from the Playground', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $shop = productSearchShop($team, ['sync_status' => 'error']);
    $shop->integration->forceFill(['status' => 'error'])->save();
    $embedding = productSearchVector();
    $product = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Tênis de corrida leve',
        'description' => 'Espuma confortável e tecido respirável.',
        'embedding' => $embedding,
    ]);
    $availableVariant = ProductVariant::factory()->for($team)->for($shop)->for($product)->create([
        'title' => 'Azul / 42',
        'options' => [
            ['name' => 'Cor', 'value' => 'Azul'],
            ['name' => 'Tamanho', 'value' => '42'],
        ],
        'price' => '349.90',
        'compare_at_price' => '399.90',
        'available' => true,
    ]);
    $category = Category::factory()->for($team)->for($shop)->create(['title' => 'Corrida']);
    $product->categories()->attach($category, ['team_id' => $team->getKey()]);

    $unavailableProduct = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Tênis sem estoque',
        'embedding' => $embedding,
    ]);
    ProductVariant::factory()->for($team)->for($shop)->for($unavailableProduct)->create([
        'options' => [['name' => 'Tamanho', 'value' => '42']],
        'price' => '349.90',
        'available' => false,
        'continues_selling_when_out_of_stock' => false,
    ]);

    $outOfBudgetProduct = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Tênis acima do orçamento',
        'embedding' => $embedding,
    ]);
    ProductVariant::factory()->for($team)->for($shop)->for($outOfBudgetProduct)->create([
        'options' => [['name' => 'Tamanho', 'value' => '42']],
        'price' => '450.00',
    ]);

    $wrongOptionProduct = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Tênis tamanho diferente',
        'embedding' => $embedding,
    ]);
    ProductVariant::factory()->for($team)->for($shop)->for($wrongOptionProduct)->create([
        'options' => [['name' => 'Tamanho', 'value' => '43']],
        'price' => '349.90',
    ]);

    $wrongCategoryProduct = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Tênis de passeio',
        'embedding' => $embedding,
    ]);
    ProductVariant::factory()->for($team)->for($shop)->for($wrongCategoryProduct)->create([
        'options' => [['name' => 'Tamanho', 'value' => '42']],
        'price' => '349.90',
    ]);
    $casualCategory = Category::factory()->for($team)->for($shop)->create(['title' => 'Passeio']);
    $wrongCategoryProduct->categories()->attach($casualCategory, ['team_id' => $team->getKey()]);

    $draftProduct = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Tênis em rascunho',
        'embedding' => $embedding,
        'status' => 'DRAFT',
    ]);
    ProductVariant::factory()->for($team)->for($shop)->for($draftProduct)->create([
        'options' => [['name' => 'Tamanho', 'value' => '42']],
        'price' => '349.90',
    ]);

    $archivedProduct = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Tênis arquivado',
        'embedding' => $embedding,
        'status' => 'ARCHIVED',
    ]);
    ProductVariant::factory()->for($team)->for($shop)->for($archivedProduct)->create([
        'options' => [['name' => 'Tamanho', 'value' => '42']],
        'price' => '349.90',
    ]);

    $unpublishedProduct = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Tênis não publicado',
        'embedding' => $embedding,
        'online_store_published' => false,
    ]);
    ProductVariant::factory()->for($team)->for($shop)->for($unpublishedProduct)->create([
        'options' => [['name' => 'Tamanho', 'value' => '42']],
        'price' => '349.90',
    ]);

    Embeddings::fake([[$embedding]]);
    ProductTriageAgent::fake()->preventStrayPrompts();
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_products', [
            'query' => 'tênis de corrida leve',
            'min_price' => 300,
            'max_price' => 400,
            'category' => 'Corrida',
            'option' => 'Tamanho 42',
            'available_only' => true,
        ]),
        'Encontrei o tênis azul no tamanho 42.',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        productSearchPlaygroundDetails([
            'objective' => AgentObjective::Sales->value,
        ]),
    );

    $content = $response->streamedContent();
    $results = productSearchResultsFromStream($content);
    $titles = array_column($results, 'title');
    expect($results)->toHaveCount(1)
        ->and($results[0]['title'])->toBe('Tênis de corrida leve')
        ->and($results[0]['variant_id'])->toBe($availableVariant->id)
        ->and($results[0]['variant'])->toBe('Azul / 42')
        ->and($results[0]['compare_at_price'])->toBe('399.90')
        ->and($results[0]['url'])->toBe($product->url)
        ->and($results[0]['available'])->toBeTrue()
        ->and($titles)->not->toContain('Tênis sem estoque')
        ->not->toContain('Tênis acima do orçamento')
        ->not->toContain('Tênis tamanho diferente')
        ->not->toContain('Tênis de passeio')
        ->not->toContain('Tênis em rascunho')
        ->not->toContain('Tênis arquivado')
        ->not->toContain('Tênis não publicado');
    Embeddings::assertGenerated(fn ($prompt): bool => $prompt->contains('tênis de corrida leve'));
    ProductTriageAgent::assertNeverPrompted();
});

it('returns variants that allow selling without stock and marks them as such', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $shop = productSearchShop($team);
    $embedding = productSearchVector();
    $product = Product::factory()->for($team)->for($shop)->create([
        'title' => 'Jaqueta sob encomenda',
        'embedding' => $embedding,
    ]);
    ProductVariant::factory()->for($team)->for($shop)->for($product)->create([
        'title' => 'Preta / M',
        'options' => [['name' => 'Tamanho', 'value' => 'M']],
        'price' => '250.00',
        'available' => false,
        'continues_selling_when_out_of_stock' => true,
    ]);

    Embeddings::fake([[$embedding]]);
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_products', ['query' => 'jaqueta preta']),
        'A jaqueta pode ser encomendada.',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        productSearchPlaygroundDetails([
            'objective' => AgentObjective::Sales->value,
            'search_products' => true,
        ]),
    );

    $results = productSearchResultsFromStream($response->streamedContent());

    expect($results)->toHaveCount(1)
        ->and($results[0]['title'])->toBe('Jaqueta sob encomenda')
        ->and($results[0]['available'])->toBeFalse()
        ->and($results[0]['continues_selling_when_out_of_stock'])->toBeTrue()
        ->and($results[0]['availability'])->toBe('Vendido mesmo sem estoque');
});

it('triages no more than thirty variants and returns the selected variants in subagent order with reasons', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $shop = productSearchShop($team);
    $embedding = productSearchVector();
    createProductSearchCandidates($team, $shop, 35, $embedding);
    $triageContext = null;

    Embeddings::fake([[$embedding]]);
    ProductTriageAgent::fake(function (string $prompt) use (&$triageContext): array {
        $triageContext = json_decode($prompt, true, flags: JSON_THROW_ON_ERROR);
        $selectedCandidates = array_reverse(array_slice($triageContext['candidates'], 2, 3));

        return [
            'recommendations' => array_map(fn (array $candidate): array => [
                'variant_id' => $candidate['variant_id'],
                'reason' => 'Combina com o que você procura.',
            ], $selectedCandidates),
        ];
    });
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_products', ['query' => 'tênis leve para corrida']),
        'Separei três opções para você.',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        productSearchPlaygroundDetails([
            'objective' => AgentObjective::Sales->value,
        ]),
    );

    $payload = productSearchPayloadFromStream($response->streamedContent());

    expect($triageContext['candidates'])->toHaveCount(30)
        ->and($payload['results'])->toHaveCount(3)
        ->and(array_column($payload['results'], 'variant_id'))->toBe(array_column(array_reverse(array_slice($triageContext['candidates'], 2, 3)), 'variant_id'))
        ->and(array_column($payload['results'], 'recommendation_reason'))->toBe([
            'Combina com o que você procura.',
            'Combina com o que você procura.',
            'Combina com o que você procura.',
        ])
        ->and($payload['triage_trace']['used'])->toBeTrue()
        ->and($payload['triage_trace']['status'])->toBe('completed');
    ProductTriageAgent::assertPromptedTimes(1);
});

it('uses conversation orders and the paired agent memory to select product variants', function () {
    $team = Team::factory()->create();
    $shop = productSearchShop($team);
    $agent = Agent::factory()->for($team)->create([
        'objective' => AgentObjective::Sales,
        'search_products' => true,
    ]);
    $agent->versions()->create([
        'team_id' => $team->getKey(),
        'number' => 1,
        'objective' => AgentObjective::Sales,
        'ecommerce_tools' => ['search_products' => true],
        'published_at' => now(),
    ]);
    $contact = Contact::factory()->for($team)->create();
    $storeCustomer = StoreCustomer::factory()->for($team)->for($shop)->for($contact)->create();
    $embedding = productSearchVector();
    $variants = createProductSearchCandidates($team, $shop, 6, $embedding);
    $order = StoreOrder::factory()->for($team)->for($shop)->for($storeCustomer, 'storeCustomer')->create([
        'order_number' => '#LOJA-81',
    ]);
    StoreOrderItem::factory()->for($team)->for($shop)->for($order, 'order')->create([
        'product_variant_id' => $variants[0]->getKey(),
        'title' => 'Tênis que Mariana já comprou',
        'quantity' => 1,
        'unit_price' => '349.90',
    ]);
    AgentContactMemory::factory()->for($team)->for($agent)->for($contact)->create([
        'content' => 'Prefere tênis azuis, leves e no tamanho 38.',
    ]);
    $history = collect([
        new Message([
            'direction' => MessageDirection::Incoming,
            'payload_type' => 'text',
            'content' => 'Quero algo confortável porque corro todos os dias.',
        ]),
    ]);
    $triageContext = null;

    Embeddings::fake([[$embedding]]);
    ProductTriageAgent::fake(function (string $prompt) use (&$triageContext): array {
        $triageContext = json_decode($prompt, true, flags: JSON_THROW_ON_ERROR);

        return [
            'recommendations' => array_map(fn (array $candidate): array => [
                'variant_id' => $candidate['variant_id'],
                'reason' => 'Atende às preferências lembradas.',
            ], array_slice($triageContext['candidates'], 1, 3)),
        ];
    });
    WhatsAppAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_products', ['query' => 'tênis confortável']),
        '',
    ]);
    $channelAgent = new WhatsAppAgent(
        $agent,
        $history,
        app(AgentInstructionTemplate::class),
        app(KnowledgeBaseSearch::class),
        app(ProductCatalogSearch::class),
        null,
        $contact,
    );

    $response = $channelAgent->prompt('Quero um tênis confortável.');
    $toolPayload = json_decode($response->toolResults->first()->result, true, flags: JSON_THROW_ON_ERROR);

    expect($triageContext['recent_conversation'][0]['content'])->toContain('corro todos os dias')
        ->and($triageContext['store_orders'][0]['order_number'])->toBe('#LOJA-81')
        ->and($triageContext['store_orders'][0]['items'][0]['title'])->toBe('Tênis que Mariana já comprou')
        ->and($triageContext['agent_memory'])->toBe('Prefere tênis azuis, leves e no tamanho 38.')
        ->and($toolPayload['results'])->toHaveCount(3);
    ProductTriageAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Prefere tênis azuis'));
});

it('triages a contact without store orders using conversation and agent memory', function () {
    $team = Team::factory()->create();
    $shop = productSearchShop($team);
    $agent = Agent::factory()->for($team)->create([
        'objective' => AgentObjective::Sales,
        'search_products' => true,
    ]);
    $agent->versions()->create([
        'team_id' => $team->getKey(),
        'number' => 1,
        'objective' => AgentObjective::Sales,
        'ecommerce_tools' => ['search_products' => true],
        'published_at' => now(),
    ]);
    $contact = Contact::factory()->for($team)->create();
    $embedding = productSearchVector();
    createProductSearchCandidates($team, $shop, 6, $embedding);
    AgentContactMemory::factory()->for($team)->for($agent)->for($contact)->create([
        'content' => 'Prefere roupas de algodão.',
    ]);
    $history = collect([
        new Message([
            'direction' => MessageDirection::Incoming,
            'payload_type' => 'text',
            'content' => 'Procuro uma camiseta para o verão.',
        ]),
    ]);
    $triageContext = null;

    Embeddings::fake([[$embedding]]);
    ProductTriageAgent::fake(function (string $prompt) use (&$triageContext): array {
        $triageContext = json_decode($prompt, true, flags: JSON_THROW_ON_ERROR);

        return [
            'recommendations' => array_map(fn (array $candidate): array => [
                'variant_id' => $candidate['variant_id'],
                'reason' => 'Combina com o tecido preferido.',
            ], array_slice($triageContext['candidates'], 0, 3)),
        ];
    });
    WhatsAppAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_products', ['query' => 'camiseta de verão']),
        '',
    ]);
    $channelAgent = new WhatsAppAgent(
        $agent,
        $history,
        app(AgentInstructionTemplate::class),
        app(KnowledgeBaseSearch::class),
        app(ProductCatalogSearch::class),
        null,
        $contact,
    );

    $channelAgent->prompt('Procuro uma camiseta para o verão.');

    expect($triageContext['store_orders'])->toBe([])
        ->and($triageContext['recent_conversation'][0]['content'])->toBe('Procuro uma camiseta para o verão.')
        ->and($triageContext['agent_memory'])->toBe('Prefere roupas de algodão.');
    ProductTriageAgent::assertPromptedTimes(1);
});

it('falls back to the original catalog order when the triage subagent fails', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $shop = productSearchShop($team);
    $embedding = productSearchVector();
    createProductSearchCandidates($team, $shop, 6, $embedding);

    Embeddings::fake([[$embedding]]);
    ProductTriageAgent::fake(function (): never {
        throw new RuntimeException('Provider unavailable.');
    });
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_products', ['query' => 'tênis leve']),
        'Encontrei opções para você.',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        productSearchPlaygroundDetails([
            'objective' => AgentObjective::Sales->value,
        ]),
    );

    $payload = productSearchPayloadFromStream($response->streamedContent());

    expect($payload['results'])->toHaveCount(5)
        ->and($payload['results'][0]['title'])->toBe('Tênis de corrida 1')
        ->and($payload['results'])->each->not->toHaveKey('recommendation_reason')
        ->and($payload['triage_trace']['used'])->toBeTrue()
        ->and($payload['triage_trace']['status'])->toBe('fallback')
        ->and($payload['triage_trace']['cost_brl'])->toBeNull();
});

it('records the triage subagent usage and cost in the tool trace metadata', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $shop = productSearchShop($team);
    $embedding = productSearchVector();
    createProductSearchCandidates($team, $shop, 6, $embedding);
    config([
        'flows.ai_trace_pricing.default_input_per_million_brl' => 2.5,
        'flows.ai_trace_pricing.default_output_per_million_brl' => 10,
        'ai.product_triage.model' => 'gpt-5.6-luna',
    ]);

    Embeddings::fake([[$embedding]]);
    ProductTriageAgent::fake(function (string $prompt): StructuredTextResponse {
        $payload = json_decode($prompt, true, flags: JSON_THROW_ON_ERROR);
        $recommendations = array_map(fn (array $candidate): array => [
            'variant_id' => $candidate['variant_id'],
            'reason' => 'Boa opção para você.',
        ], array_slice($payload['candidates'], 0, 3));

        return new StructuredTextResponse(
            ['recommendations' => $recommendations],
            json_encode(['recommendations' => $recommendations], JSON_THROW_ON_ERROR),
            new Usage(1000, 500),
            new Meta('openai', 'gpt-5.6-luna'),
        );
    });
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_products', ['query' => 'tênis leve']),
        'Encontrei opções para você.',
    ]);

    $response = $this->actingAs($user)->postJson(
        route('agents.playground.stream'),
        productSearchPlaygroundDetails([
            'objective' => AgentObjective::Sales->value,
        ]),
    );

    $payload = productSearchPayloadFromStream($response->streamedContent());

    expect($payload['triage_trace']['model'])->toBe('gpt-5.6-luna')
        ->and($payload['triage_trace']['input_tokens'])->toBe(1000)
        ->and($payload['triage_trace']['output_tokens'])->toBe(500)
        ->and($payload['triage_trace']['cost_brl'])->toBe(0.0075);
});
