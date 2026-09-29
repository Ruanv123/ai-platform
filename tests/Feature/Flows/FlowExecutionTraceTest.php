<?php

use App\Ai\Agents\ProductTriageAgent;
use App\Ai\Agents\WhatsAppAgent;
use App\Enums\AgentObjective;
use App\Enums\ChannelType;
use App\Enums\FlowCategory;
use App\Enums\FlowStatus;
use App\Enums\FlowTriggerType;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\FlowExecutionStep;
use App\Models\FlowVersion;
use App\Models\Integration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;

function traceFlowGraph(array $nodes, array $edges = []): array
{
    return ['version' => 1, 'nodes' => $nodes, 'edges' => $edges];
}

it('records ordered node steps with followed edges durations results and variable snapshots through the webhook', function () {
    $team = Team::factory()->create();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    $dsl = traceFlowGraph([
        ['id' => 'incoming', 'type' => 'trigger', 'config' => []],
        ['id' => 'remember-name', 'type' => 'define_variable', 'config' => ['name' => 'customer_name', 'value' => 'Ana']],
        ['id' => 'done', 'type' => 'end', 'config' => []],
    ], [
        ['id' => 'incoming-to-name', 'source' => 'incoming', 'target' => 'remember-name'],
        ['id' => 'name-to-done', 'source' => 'remember-name', 'target' => 'done', 'label' => 'Continuar'],
    ]);
    $flow = Flow::factory()->for($team)->published()->create([
        'channel_id' => $channel->getKey(),
        'trigger_type' => FlowTriggerType::Webhook,
        'trigger_event' => 'contact.created',
        'allow_unknown_contact' => true,
        'draft_dsl' => $dsl,
    ]);
    FlowVersion::factory()->for($flow)->create([
        'team_id' => $team->getKey(),
        'dsl' => $dsl,
        'category' => FlowCategory::Reaction,
    ]);

    $this->postJson(route('webhooks.flows', [$channel->getKey(), 'contact.created']), [
        'source' => 'landing-page',
    ])->assertOk()->assertJson(['accepted' => true]);

    $execution = FlowExecution::withoutGlobalScopes()->sole();
    $steps = DB::table('flow_execution_steps')
        ->where('flow_execution_id', $execution->getKey())
        ->orderBy('sequence')
        ->get();

    expect($steps)->toHaveCount(3)
        ->and($steps->pluck('node_id')->all())->toBe(['incoming', 'remember-name', 'done'])
        ->and($steps->pluck('result')->all())->toBe(['completed', 'completed', 'completed'])
        ->and($steps[0]->edge_id)->toBe('incoming-to-name')
        ->and($steps[0]->edge_target_node_id)->toBe('remember-name')
        ->and($steps[1]->edge_id)->toBe('name-to-done')
        ->and($steps[1]->edge_label)->toBe('Continuar')
        ->and($steps[2]->edge_id)->toBeNull()
        ->and($steps->every(fn (object $step): bool => is_int($step->duration_ms) && $step->duration_ms >= 0))->toBeTrue()
        ->and(json_decode($steps[0]->variables_snapshot, true, flags: JSON_THROW_ON_ERROR))->toMatchArray(['source' => 'landing-page'])
        ->and(json_decode($steps[1]->variables_snapshot, true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'source' => 'landing-page',
            'customer_name' => 'Ana',
        ]);
});

it('renders the failed node and all unreached nodes on the execution detail screen', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    $contact = Contact::factory()->for($team)->named('Ana Souza')->create(['phone' => '5511999999999']);
    $dsl = traceFlowGraph([
        ['id' => 'broken-message', 'type' => 'message', 'config' => ['exception_ladder' => ['finish']]],
        ['id' => 'never-reached', 'type' => 'end', 'config' => []],
    ], [
        ['id' => 'broken-to-end', 'source' => 'broken-message', 'target' => 'never-reached'],
    ]);
    $flow = Flow::factory()->for($team)->published()->create([
        'name' => 'Recepção principal',
        'channel_id' => $channel->getKey(),
        'trigger_type' => FlowTriggerType::Webhook,
        'trigger_event' => 'contact.created',
        'draft_dsl' => $dsl,
    ]);
    FlowVersion::factory()->for($flow)->create([
        'team_id' => $team->getKey(),
        'number' => 4,
        'dsl' => $dsl,
        'category' => FlowCategory::Reaction,
    ]);

    $this->postJson(route('webhooks.flows', [$channel->getKey(), 'contact.created']), [
        'phone' => $contact->phone,
    ])->assertOk();
    $execution = FlowExecution::withoutGlobalScopes()->sole();

    $response = $this->actingAs($user)->get(route('flows.executions.show', [$flow, $execution]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('flows/executions/show')
        ->where('execution.id', $execution->getKey())
        ->where('execution.flowName', 'Recepção principal')
        ->where('execution.contact.name', 'Ana Souza')
        ->where('execution.contact.phone', '+5511999999999')
        ->where('execution.channel.label', 'WhatsApp de testes')
        ->where('execution.version', 4)
        ->where('execution.status', 'failed')
        ->where('execution.finalReason', 'O Nó de Mensagem não possui texto.')
        ->where('steps.0.nodeId', 'broken-message')
        ->where('steps.0.result', 'failed')
        ->where('steps.0.error', 'O Nó de Mensagem não possui texto.')
        ->where('steps.0.highlighted', true)
        ->where('unreachedNodes.0.nodeId', 'never-reached')
        ->where('unreachedNodes.0.reached', false),
    );
});

it('lists sibling executions from the same flow filtered by status', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    $failingDsl = traceFlowGraph([
        ['id' => 'broken-message', 'type' => 'message', 'config' => ['exception_ladder' => ['finish']]],
    ]);
    $flow = Flow::factory()->for($team)->published()->create([
        'channel_id' => $channel->getKey(),
        'trigger_type' => FlowTriggerType::Webhook,
        'trigger_event' => 'contact.created',
        'allow_unknown_contact' => true,
        'draft_dsl' => $failingDsl,
    ]);
    FlowVersion::factory()->for($flow)->create([
        'team_id' => $team->getKey(),
        'dsl' => $failingDsl,
        'category' => FlowCategory::Reaction,
    ]);

    $this->postJson(route('webhooks.flows', [$channel->getKey(), 'contact.created']), [
        'source' => 'first',
    ])->assertOk();
    $this->postJson(route('webhooks.flows', [$channel->getKey(), 'contact.created']), [
        'source' => 'second',
    ])->assertOk();
    $executions = FlowExecution::withoutGlobalScopes()->get();
    $execution = $executions->first();

    $response = $this->actingAs($user)->get(route('flows.executions.show', [$flow, $execution]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('siblingStatus', 'failed')
        ->has('siblingExecutions.data', 2)
        ->where('siblingExecutions.counts.failed', 2)
        ->where('siblingExecutions.counts.all', 2),
    );

    $this->actingAs($user)
        ->get(route('flows.executions.show', [$flow, $execution, 'execution_status' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('siblingStatus', 'all')
            ->has('siblingExecutions.data', 2),
        );
});

it('highlights the node where an execution is waiting', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    $contact = Contact::factory()->for($team)->create();
    $dsl = traceFlowGraph([
        [
            'id' => 'await-reply',
            'type' => 'capture_response',
            'config' => ['variable' => 'reply', 'terminal' => true],
        ],
    ]);
    $flow = Flow::factory()->for($team)->published()->create([
        'channel_id' => $channel->getKey(),
        'trigger_type' => FlowTriggerType::Webhook,
        'trigger_event' => 'contact.created',
        'draft_dsl' => $dsl,
    ]);
    FlowVersion::factory()->for($flow)->conversation()->create([
        'team_id' => $team->getKey(),
        'dsl' => $dsl,
    ]);

    $this->postJson(route('webhooks.flows', [$channel->getKey(), 'contact.created']), [
        'phone' => $contact->phone,
    ])->assertOk();
    $execution = FlowExecution::withoutGlobalScopes()->sole();

    $this->actingAs($user)
        ->get(route('flows.executions.show', [$flow, $execution]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('execution.status', 'waiting')
            ->where('steps.0.nodeId', 'await-reply')
            ->where('steps.0.result', 'waiting')
            ->where('steps.0.highlighted', true),
        );
});

it('returns 404 when another company requests an execution trace', function () {
    $owner = User::factory()->withTeam()->create();
    $flow = Flow::factory()->for($owner->company())->create(['status' => FlowStatus::Published]);
    $version = FlowVersion::factory()->for($flow)->create(['team_id' => $owner->company()->getKey()]);
    $execution = FlowExecution::factory()->for($version, 'flowVersion')->create();
    $otherUser = User::factory()->withTeam()->create();

    $this->actingAs($otherUser)
        ->get(route('flows.executions.show', [$flow, $execution]))
        ->assertNotFound();
});

it('includes product triage tokens and cost in the flow execution trace', function () {
    $team = Team::factory()->create();
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
    $channel = Channel::factory()->for($team)->for($agent)->create(['type' => ChannelType::WhatsAppTest]);
    $contact = Contact::factory()->for($team)->create(['phone' => '+5511999999999']);
    $integration = Integration::factory()->for($team)->create([
        'type' => 'ecommerce',
        'provider' => 'shopify',
        'status' => 'connected',
    ]);
    $shop = Shop::factory()->for($team)->for($integration)->create();
    $embedding = array_merge([1.0], array_fill(0, 1535, 0.0));

    for ($candidateNumber = 1; $candidateNumber <= 6; $candidateNumber++) {
        $product = Product::factory()->for($team)->for($shop)->create([
            'title' => "Tênis de corrida {$candidateNumber}",
            'embedding' => $embedding,
        ]);
        ProductVariant::factory()->for($team)->for($shop)->for($product)->create();
    }

    $dsl = traceFlowGraph([
        ['id' => 'incoming', 'type' => 'trigger', 'config' => []],
        ['id' => 'recommend', 'type' => 'deliver_agent', 'config' => ['prompt' => 'Quero tênis de corrida.']],
        ['id' => 'done', 'type' => 'end', 'config' => []],
    ], [
        ['id' => 'incoming-to-agent', 'source' => 'incoming', 'target' => 'recommend'],
        ['id' => 'agent-to-end', 'source' => 'recommend', 'target' => 'done'],
    ]);
    $flow = Flow::factory()->for($team)->published()->create([
        'channel_id' => $channel->getKey(),
        'trigger_type' => FlowTriggerType::Webhook,
        'trigger_event' => 'contact.created',
        'draft_dsl' => $dsl,
    ]);
    FlowVersion::factory()->for($flow)->create([
        'team_id' => $team->getKey(),
        'dsl' => $dsl,
        'category' => FlowCategory::Reaction,
    ]);
    config([
        'flows.ai_trace_pricing.default_input_per_million_brl' => 2.5,
        'flows.ai_trace_pricing.default_output_per_million_brl' => 10,
        'ai.product_triage.model' => 'gpt-5.6-luna',
        'knowledge.product_search.candidate_limit' => 30,
        'knowledge.product_search.offer_limit' => 3,
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
    WhatsAppAgent::fake([
        new ToolCall((string) Str::uuid7(), 'search_products', ['query' => 'tênis de corrida']),
        '',
    ]);

    $this->postJson(route('webhooks.flows', [$channel->getKey(), 'contact.created']), [
        'phone' => $contact->phone,
    ])->assertOk()->assertJson(['accepted' => true]);
    ProductTriageAgent::assertPromptedTimes(1);

    $execution = FlowExecution::withoutGlobalScopes()->sole();
    $recommendationSteps = $execution->steps()->where('node_id', 'recommend')->get();
    $recommendationCost = $recommendationSteps->sum(fn (FlowExecutionStep $step): float => (float) $step->ai_cost_brl);
    $recommendationInputTokens = $recommendationSteps->sum(fn (FlowExecutionStep $step): int => (int) $step->ai_input_tokens);
    $recommendationOutputTokens = $recommendationSteps->sum(fn (FlowExecutionStep $step): int => (int) $step->ai_output_tokens);

    expect($recommendationSteps)->not->toBeEmpty()
        ->and($recommendationInputTokens)->toBe(1000)
        ->and($recommendationOutputTokens)->toBe(500)
        ->and($recommendationCost)->toBe(0.0075)
        ->and((float) $execution->ai_cost_brl)->toBe($recommendationCost);
});
