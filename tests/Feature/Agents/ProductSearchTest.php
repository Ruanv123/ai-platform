<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Models\Category;
use App\Models\Integration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\ToolCall;

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

        return is_array($result['results'] ?? null) ? $result['results'] : [];
    }

    return [];
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
