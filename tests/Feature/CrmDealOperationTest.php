<?php

use App\Ai\AgentToolFactory;
use App\Ai\AgentToolMode;
use App\Ai\Tools\GuaranteeCrmDeal;
use App\Crm\Adapters\RdStationCrmAdapter;
use App\Crm\Contracts\CrmProviderAdapter;
use App\Crm\CrmCardLookup;
use App\Crm\CrmErrorCategory;
use App\Crm\CrmOperationDispatcher;
use App\Crm\CrmOperationProcessor;
use App\Crm\CrmProviderException;
use App\Ecommerce\ProductCatalogSearch;
use App\Enums\AgentObjective;
use App\Enums\CrmOperationStatus;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmLinkedDeal;
use App\Models\CrmOperation;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\Support\Crm\Fakes\FakeCrmProviderAdapter;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    Queue::fake();
});

/**
 * @return array{team: Team, integration: Integration, agent: Agent, contact: Contact, tool: GuaranteeCrmDeal}
 */
function crmDealScenario(array $contactAttributes = [], array $crmSettings = []): array
{
    $team = Team::factory()->create();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'rd-access-token',
            'refresh_token' => 'rd-refresh-token',
            'expires_at' => now()->addHour()->timestamp,
        ],
        'crm_metadata' => [
            'pipelines' => [[
                'id' => 'pipeline-1',
                'name' => 'Vendas',
                'stages' => [['id' => 'stage-1', 'name' => 'Qualificação', 'status' => null, 'position' => 1]],
            ]],
            'owners' => [['id' => 'owner-default', 'name' => 'Pessoa responsável', 'email' => null]],
        ],
        'crm_settings' => array_merge(['default_owner_id' => 'owner-default'], $crmSettings),
    ]);
    $agent = Agent::factory()->for($team)->create();
    $contact = Contact::factory()->for($team)->create(array_merge([
        'name' => 'Ana Silva',
        'phone' => '+5511999998888',
    ], $contactAttributes));
    $tool = new GuaranteeCrmDeal($team, $agent, $contact, new CrmOperationDispatcher, 'pipeline-1', 'stage-1');

    return compact('team', 'integration', 'agent', 'contact', 'tool');
}

/** @return array<string, mixed> */
function crmDealToolResult(GuaranteeCrmDeal $tool, array $arguments = []): array
{
    return json_decode($tool->handle(new ToolRequest($arguments)), true, flags: JSON_THROW_ON_ERROR);
}

function processCrmDealOperation(CrmOperation $operation, CrmProviderAdapter $adapter): void
{
    (new ProcessCrmOperation($operation->getKey()))->handle(new CrmOperationProcessor($adapter), $adapter);
}

/** @return array<string, mixed> */
function rdDealFixture(string $name): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/Crm/RdStation/'.$name.'.json')), true, flags: JSON_THROW_ON_ERROR);
}

it('registers the tool request as a pending queued operation without calling the CRM', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;

    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual', 'estimated_value' => 1499.5]);

    $operation = CrmOperation::query()->findOrFail($result['operation_id']);
    expect($result)->toMatchArray(['ok' => true, 'registered' => true])
        ->and($operation->status)->toBe(CrmOperationStatus::Pending)
        ->and($operation->payload)->toMatchArray([
            'title' => 'Plano anual',
            'estimated_value' => 1499.5,
            'pipeline_id' => 'pipeline-1',
            'initial_stage_id' => 'stage-1',
            'default_owner_id' => 'owner-default',
        ])
        ->and($operation->contact_id)->toBe($scenario['contact']->getKey())
        ->and($adapter->phoneSearches)->toBe([])
        ->and($adapter->createPersonCalls)->toBe([]);

    Queue::assertPushedOn('crm-operations', ProcessCrmOperation::class);
});

it('returns safe messages for dispatcher errors and hides unexpected exception details', function () {
    $scenario = crmDealScenario();
    $dispatcher = Mockery::mock(CrmOperationDispatcher::class);
    $dispatcher->shouldReceive('queueGuaranteeDeal')
        ->once()
        ->andThrow(new RuntimeException('database password: private-value'));
    $tool = new GuaranteeCrmDeal(
        $scenario['team'],
        $scenario['agent'],
        $scenario['contact'],
        $dispatcher,
        'pipeline-1',
        'stage-1',
    );

    $unexpectedResult = crmDealToolResult($tool, ['title' => 'Plano anual']);
    $scenario['integration']->forceFill(['status' => 'error'])->save();
    $domainResult = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);

    expect($unexpectedResult)->toBe([
        'ok' => false,
        'message' => 'Não foi possível registrar a Operação no CRM agora.',
    ])->and($domainResult)->toBe([
        'ok' => false,
        'message' => 'O CRM não está conectado.',
    ]);
});

it('creates a missing CRM person and deal with inherited and default ownership, then saves the canonical link', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $operationResult = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual', 'estimated_value' => 1499.5]);
    $operation = CrmOperation::query()->findOrFail($operationResult['operation_id']);

    processCrmDealOperation($operation, $adapter);

    $operation->refresh();
    $link = CrmContactLink::query()->where('contact_id', $scenario['contact']->getKey())->firstOrFail();
    $deal = CrmLinkedDeal::query()->firstOrFail();
    expect($operation->status)->toBe(CrmOperationStatus::Completed)
        ->and($operation->attempts)->toBe(1)
        ->and($adapter->phoneSearches)->toBe(['+5511999998888'])
        ->and($adapter->createPersonCalls[0])->toMatchArray([
            'name' => 'Ana Silva',
            'phone' => '+5511999998888',
            'owner_id' => 'owner-default',
        ])
        ->and($adapter->createDealCalls[0])->toMatchArray([
            'name' => 'Plano anual',
            'person_id' => 'person-1',
            'pipeline_id' => 'pipeline-1',
            'stage_id' => 'stage-1',
            'value' => 1499.5,
            'owner_id' => 'owner-default',
        ])
        ->and($link->external_person_id)->toBe('person-1')
        ->and($deal->crm_contact_link_id)->toBe($link->getKey())
        ->and($deal->external_deal_id)->toBe('deal-1')
        ->and($deal->pipeline_id)->toBe('pipeline-1')
        ->and($deal->stage_id)->toBe('stage-1')
        ->and($deal->status)->toBe('open')
        ->and($deal->value)->toBe(1499.5)
        ->and($deal->owner_id)->toBe('owner-default');
});

it('selects the newest phone match and records the duplicate match count in the operation summary', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [[
        'id' => 'person-oldest',
        'name' => 'Ficha antiga',
        'phone' => '+5511999998888',
        'email' => null,
        'organization_id' => null,
        'owner_id' => 'owner-old',
        'fields' => [],
        'updated_at' => '2025-01-01T00:00:00Z',
    ], [
        'id' => 'person-newest',
        'name' => 'Ficha mais recente',
        'phone' => '+5511999998888',
        'email' => null,
        'organization_id' => null,
        'owner_id' => 'owner-new',
        'fields' => [],
        'updated_at' => '2025-02-01T00:00:00Z',
    ]];
    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);
    $operation = CrmOperation::query()->findOrFail($result['operation_id']);

    processCrmDealOperation($operation, $adapter);

    expect(CrmContactLink::query()->where('contact_id', $scenario['contact']->getKey())->value('external_person_id'))->toBe('person-newest')
        ->and($operation->fresh()->summary)->toMatchArray([
            'crm_person_phone_match_count' => 2,
            'crm_person_phone_match_duplicates' => true,
        ])
        ->and($adapter->createPersonCalls)->toBe([]);
});

it('reuses the existing contact link and open deal in the configured pipeline', function () {
    $scenario = crmDealScenario();
    $link = CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => 'person-existing',
    ]);
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [[
        'id' => 'person-existing',
        'name' => 'Nome do CRM',
        'phone' => '+5511999998888',
        'email' => null,
        'organization_id' => null,
        'owner_id' => 'owner-person',
        'fields' => [],
        'updated_at' => '2025-01-01T00:00:00Z',
    ]];
    $adapter->openDeals = [[
        'id' => 'deal-sales',
        'person_id' => 'person-existing',
        'organization_id' => null,
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-existing',
        'status' => 'open',
        'loss_reason_id' => null,
        'value' => 250.0,
        'owner_id' => 'owner-person',
        'updated_at' => '2025-01-01T00:00:00Z',
    ], [
        'id' => 'deal-other-pipeline',
        'person_id' => 'person-existing',
        'organization_id' => null,
        'pipeline_id' => 'pipeline-other',
        'stage_id' => 'stage-other',
        'status' => 'open',
        'loss_reason_id' => null,
        'value' => 800.0,
        'owner_id' => null,
        'updated_at' => '2025-02-01T00:00:00Z',
    ]];
    $operationResult = crmDealToolResult($scenario['tool'], ['title' => 'Renovação', 'estimated_value' => 300]);

    processCrmDealOperation(CrmOperation::query()->findOrFail($operationResult['operation_id']), $adapter);

    expect($adapter->createPersonCalls)->toBe([])
        ->and($adapter->createDealCalls)->toBe([])
        ->and($adapter->updateDealCalls)->toBe([['id' => 'deal-sales', 'attributes' => ['name' => 'Renovação', 'value' => 300.0]]])
        ->and(CrmContactLink::query()->count())->toBe(1)
        ->and(CrmLinkedDeal::query()->firstOrFail()->external_deal_id)->toBe('deal-sales')
        ->and(CrmLinkedDeal::query()->firstOrFail()->crm_contact_link_id)->toBe($link->getKey());
});

it('fills only an empty field on an existing CRM person', function () {
    $scenario = crmDealScenario();
    CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => 'person-existing',
    ]);
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [[
        'id' => 'person-existing',
        'name' => 'CRM preservado',
        'phone' => null,
        'email' => null,
        'organization_id' => null,
        'owner_id' => 'owner-person',
        'fields' => [],
        'updated_at' => '2025-01-01T00:00:00Z',
    ]];
    $operationResult = crmDealToolResult($scenario['tool'], ['title' => 'Consulta']);

    processCrmDealOperation(CrmOperation::query()->findOrFail($operationResult['operation_id']), $adapter);

    expect($adapter->updatePersonCalls)->toBe([[
        'id' => 'person-existing',
        'attributes' => ['phone' => '+5511999998888'],
    ]])->and($adapter->createPersonCalls)->toBe([])
        ->and($adapter->createDealCalls[0]['owner_id'])->toBe('owner-person');
});

it('falls back to the configured default owner when an existing person has no owner', function () {
    $scenario = crmDealScenario();
    CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => 'person-without-owner',
    ]);
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [[
        'id' => 'person-without-owner',
        'name' => 'Pessoa do CRM',
        'phone' => '+5511999998888',
        'email' => null,
        'organization_id' => null,
        'owner_id' => null,
        'fields' => [],
        'updated_at' => '2025-01-01T00:00:00Z',
    ]];
    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);

    processCrmDealOperation(CrmOperation::query()->findOrFail($result['operation_id']), $adapter);

    expect($adapter->createDealCalls[0]['owner_id'])->toBe('owner-default');
});

it('processes repeated operations for the same contact through one CRM person link', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;

    foreach (['Primeiro pedido', 'Segundo pedido'] as $title) {
        $result = crmDealToolResult($scenario['tool'], ['title' => $title]);
        processCrmDealOperation(CrmOperation::query()->findOrFail($result['operation_id']), $adapter);
    }

    expect(CrmContactLink::query()->where('contact_id', $scenario['contact']->getKey())->count())->toBe(1)
        ->and($adapter->createPersonCalls)->toHaveCount(1)
        ->and($adapter->createDealCalls)->toHaveCount(1)
        ->and($adapter->updateDealCalls)->toHaveCount(1);
});

it('defers and then reuses CRM writes across an interleaved same-contact operation', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $firstResult = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);
    $secondResult = crmDealToolResult($scenario['tool'], ['title' => 'Renovação']);
    $firstOperation = CrmOperation::query()->findOrFail($firstResult['operation_id']);
    $secondOperation = CrmOperation::query()->findOrFail($secondResult['operation_id']);
    $lockHeld = false;
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')
        ->times(3)
        ->with(30, Mockery::type(Closure::class))
        ->andReturnUsing(function (int $seconds, Closure $callback) use (&$lockHeld): mixed {
            if ($lockHeld) {
                throw new LockTimeoutException('Lock timeout.');
            }

            $lockHeld = true;
            try {
                return $callback();
            } finally {
                $lockHeld = false;
            }
        });
    Cache::shouldReceive('lock')
        ->times(3)
        ->with('crm:contact-link:'.$scenario['team']->getKey().':'.$scenario['contact']->getKey(), 120)
        ->andReturn($lock);
    $adapter->beforeCreatePerson = fn (): mixed => Fiber::suspend();
    $firstWorker = new Fiber(fn (): mixed => processCrmDealOperation($firstOperation, $adapter));
    $firstWorker->start();

    expect($firstWorker->isSuspended())->toBeTrue()
        ->and(CrmContactLink::query()->count())->toBe(0)
        ->and($adapter->createPersonCalls)->toBe([]);

    processCrmDealOperation($secondOperation, $adapter);

    expect($secondOperation->fresh()->status)->toBe(CrmOperationStatus::Pending)
        ->and($secondOperation->fresh()->attempts)->toBe(1)
        ->and($secondOperation->fresh()->failure_message)->toContain('Outra gravação do CRM')
        ->and(CrmContactLink::query()->count())->toBe(0)
        ->and($adapter->createPersonCalls)->toBe([]);

    $firstWorker->resume();
    processCrmDealOperation($secondOperation->fresh(), $adapter);

    expect($firstWorker->isTerminated())->toBeTrue()
        ->and($firstOperation->fresh()->status)->toBe(CrmOperationStatus::Completed)
        ->and($secondOperation->fresh()->status)->toBe(CrmOperationStatus::Completed)
        ->and($secondOperation->fresh()->attempts)->toBe(2)
        ->and(CrmContactLink::query()->where('contact_id', $scenario['contact']->getKey())->count())->toBe(1)
        ->and(CrmLinkedDeal::query()->count())->toBe(1)
        ->and($adapter->createPersonCalls)->toHaveCount(1)
        ->and($adapter->createDealCalls)->toHaveCount(1)
        ->and($adapter->updateDealCalls)->toHaveCount(1);
});

it('keeps transient failures within five CRM attempts with exponential delayed retries', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $adapter->personSearchFailures = array_fill(0, 5, new CrmProviderException(CrmErrorCategory::Temporary, 'CRM temporariamente indisponível.'));
    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);
    $operation = CrmOperation::query()->findOrFail($result['operation_id']);

    foreach (range(1, 5) as $_) {
        processCrmDealOperation($operation->fresh(), $adapter);
    }

    $operation->refresh();
    expect($operation->status)->toBe(CrmOperationStatus::Failed)
        ->and($operation->attempts)->toBe(5)
        ->and($operation->failure_message)->toBe('CRM temporariamente indisponível.')
        ->and($adapter->phoneSearches)->toHaveCount(5);
    Queue::assertPushed(ProcessCrmOperation::class, 5);
});

it('waits for a provider rate limit without spending a CRM attempt', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $adapter->personSearchFailures = [new CrmProviderException(CrmErrorCategory::RateLimit, 'Limite atingido.', 37)];
    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);
    $operation = CrmOperation::query()->findOrFail($result['operation_id']);

    processCrmDealOperation($operation, $adapter);

    expect($operation->fresh()->status)->toBe(CrmOperationStatus::Pending)
        ->and($operation->fresh()->attempts)->toBe(0);
    Queue::assertPushed(ProcessCrmOperation::class, fn (ProcessCrmOperation $job): bool => $job->delay !== null);
});

it('renews access once after provider refusal and retries the CRM operation', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $adapter->personSearchFailures = [new CrmProviderException(CrmErrorCategory::AccessDenied, 'Token recusado.')];
    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);
    $operation = CrmOperation::query()->findOrFail($result['operation_id']);

    processCrmDealOperation($operation, $adapter);

    expect($operation->fresh()->status)->toBe(CrmOperationStatus::Completed)
        ->and($operation->fresh()->attempts)->toBe(1)
        ->and($operation->fresh()->token_refresh_attempts)->toBe(1)
        ->and($adapter->renewalCount)->toBe(1);
});

it('keeps a CRM operation pending when its one token renewal is refused', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $adapter->personSearchFailures = [new CrmProviderException(CrmErrorCategory::AccessDenied, 'Token recusado.')];
    $adapter->renewalFailure = new CrmProviderException(CrmErrorCategory::AccessDenied, 'Renovação recusada.');
    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);

    processCrmDealOperation(CrmOperation::query()->findOrFail($result['operation_id']), $adapter);

    $operation = CrmOperation::query()->findOrFail($result['operation_id']);
    expect($operation->status)->toBe(CrmOperationStatus::Pending)
        ->and($operation->attempts)->toBe(1)
        ->and($operation->token_refresh_attempts)->toBe(1)
        ->and($operation->failure_message)->toContain('Reconecte a conta')
        ->and($scenario['integration']->fresh()->status)->toBe('error')
        ->and($adapter->renewalCount)->toBe(1);
    Queue::assertPushed(ProcessCrmOperation::class, 1);
});

it('keeps an operation pending with a readable message while the integration is unavailable', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);
    $scenario['integration']->forceFill(['status' => 'error'])->save();

    processCrmDealOperation(CrmOperation::query()->findOrFail($result['operation_id']), $adapter);

    $operation = CrmOperation::query()->findOrFail($result['operation_id']);
    expect($operation->status)->toBe(CrmOperationStatus::Pending)
        ->and($operation->attempts)->toBe(0)
        ->and($operation->failure_message)->toContain('aguarda a reconexão')
        ->and($adapter->phoneSearches)->toBe([]);
    Queue::assertPushed(ProcessCrmOperation::class, 1);
});

it('fails immediately for a definitive CRM error with a readable reason', function () {
    $scenario = crmDealScenario(['phone' => 'telefone inválido']);
    $adapter = new FakeCrmProviderAdapter;
    $result = crmDealToolResult($scenario['tool'], ['title' => 'Plano anual']);

    processCrmDealOperation(CrmOperation::query()->findOrFail($result['operation_id']), $adapter);

    $operation = CrmOperation::query()->findOrFail($result['operation_id']);
    expect($operation->status)->toBe(CrmOperationStatus::Failed)
        ->and($operation->attempts)->toBe(1)
        ->and($operation->failure_message)->toContain('telefone válido')
        ->and($adapter->createPersonCalls)->toBe([]);
});

it('simulates the tool in the Playground without creating an operation or calling the CRM', function () {
    $scenario = crmDealScenario();
    $adapter = new FakeCrmProviderAdapter;
    $playgroundTool = new GuaranteeCrmDeal(
        $scenario['team'],
        $scenario['agent'],
        null,
        new CrmOperationDispatcher,
        'pipeline-1',
        'stage-1',
        true,
        '+5511999998888',
    );

    $result = crmDealToolResult($playgroundTool, ['title' => 'Plano anual', 'estimated_value' => 1499.5]);

    expect($result)->toMatchArray([
        'ok' => true,
        'simulated' => true,
        'would_send' => [
            'title' => 'Plano anual',
            'estimated_value' => 1499.5,
            'contact_name' => null,
            'contact_phone' => '+5511999998888',
            'pipeline_id' => 'pipeline-1',
            'initial_stage_id' => 'stage-1',
        ],
    ])->and(CrmOperation::query()->count())->toBe(0)
        ->and($adapter->phoneSearches)->toBe([]);
});

it('loads, saves, and publishes the configured CRM pipeline and initial stage', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'crm_metadata' => [
            'pipelines' => [[
                'id' => 'pipeline-sales',
                'name' => 'Vendas',
                'stages' => [['id' => 'stage-qualification', 'name' => 'Qualificação', 'status' => null, 'position' => 1]],
            ]],
            'owners' => [],
        ],
        'crm_metadata_loaded_at' => now(),
    ]);
    $agent = Agent::factory()->for($team)->create(['objective' => AgentObjective::Sales]);

    $this->actingAs($user)->get(route('agents.edit', $agent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('agent.crmEnsureDealEnabled', true)
            ->where('agent.crmPipelineId', 'pipeline-sales')
            ->where('agent.crmInitialStageId', 'stage-qualification')
            ->where('crmPipelines.0.id', 'pipeline-sales')
            ->where('crmPipelines.0.stages.0.id', 'stage-qualification'));

    $details = [
        'name' => 'Lia',
        'objective' => AgentObjective::Sales->value,
        'tone' => 'friendly',
        'uses_emojis' => false,
        'response_length' => 'short',
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => ['Atenda com clareza.'],
        'rules' => ['Não invente informações.'],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
        'crm_ensure_deal_enabled' => true,
        'crm_pipeline_id' => 'pipeline-sales',
        'crm_initial_stage_id' => 'stage-qualification',
    ];

    $this->patch(route('agents.update', $agent), $details)->assertRedirect(route('agents.edit', $agent));
    $this->post(route('agents.publish', $agent), $details)->assertRedirect(route('agents.edit', $agent));

    expect($agent->fresh()->crm_pipeline_id)->toBe('pipeline-sales')
        ->and($agent->fresh()->crm_initial_stage_id)->toBe('stage-qualification')
        ->and($agent->fresh()->crm_ensure_deal_enabled)->toBeTrue()
        ->and(AgentVersion::query()->where('agent_id', $agent->getKey())->firstOrFail()->crm_tools)->toBe([
            'ensure_deal_enabled' => true,
            'pipeline_id' => 'pipeline-sales',
            'initial_stage_id' => 'stage-qualification',
        ]);

    $factory = new AgentToolFactory(
        app(KnowledgeBaseSearch::class),
        app(ProductCatalogSearch::class),
        crmCardLookup: new CrmCardLookup(new FakeCrmProviderAdapter),
    );
    $contact = Contact::factory()->for($team)->create();
    $tool = collect($factory->make($agent->fresh(), $contact, AgentToolMode::Real, []))
        ->first(fn (mixed $tool): bool => $tool instanceof GuaranteeCrmDeal);
    $this->assertInstanceOf(GuaranteeCrmDeal::class, $tool);
    $result = crmDealToolResult($tool, ['title' => 'Negócio publicado']);

    expect(CrmOperation::query()->findOrFail($result['operation_id'])->payload)->toMatchArray([
        'pipeline_id' => 'pipeline-sales',
        'initial_stage_id' => 'stage-qualification',
    ]);
});

it('saves the CRM default owner selected from the connected account metadata', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'crm_metadata' => [
            'pipelines' => [],
            'owners' => [['id' => 'owner-1', 'name' => 'Pessoa responsável', 'email' => null]],
        ],
        'crm_metadata_loaded_at' => now(),
    ]);

    $this->actingAs($user)->put(route('integrations.crm.default-owner.update'), ['default_owner_id' => 'owner-1'])
        ->assertRedirect(route('integrations.show', 'crm'));

    expect($integration->fresh()->crm_settings['default_owner_id'])->toBe('owner-1');
});

it('creates a CRM person and deal through the RD API using provider fixtures', function () {
    Http::fake(function (HttpRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ([$request->method(), $path]) {
            ['GET', '/crm/v2/contacts'] => Http::response(rdDealFixture('contacts-empty')),
            ['POST', '/crm/v2/contacts'] => Http::response(rdDealFixture('person-created')),
            ['GET', '/crm/v2/contacts/67c000000000000000000005'] => Http::response(rdDealFixture('person-created')),
            ['GET', '/crm/v2/deals'] => Http::response(rdDealFixture('deals-empty')),
            ['POST', '/crm/v2/deals'] => Http::response(rdDealFixture('deal-created')),
            default => throw new RuntimeException('Unexpected RD Station request: '.$request->url()),
        };
    });
    Http::preventStrayRequests();
    $scenario = crmDealScenario([], ['default_owner_id' => '65a000000000000000000003']);
    $scenario['integration']->forceFill(['crm_metadata' => [
        'pipelines' => [[
            'id' => '65a000000000000000000001',
            'name' => 'Vendas RD',
            'stages' => [['id' => '65a000000000000000000002', 'name' => 'Qualificação', 'status' => null, 'position' => 1]],
        ]],
        'owners' => [],
    ]])->save();
    $tool = new GuaranteeCrmDeal(
        $scenario['team'],
        $scenario['agent'],
        $scenario['contact'],
        new CrmOperationDispatcher,
        '65a000000000000000000001',
        '65a000000000000000000002',
    );
    $result = crmDealToolResult($tool, ['title' => 'Plano anual', 'estimated_value' => 1499.5]);
    processCrmDealOperation(CrmOperation::query()->findOrFail($result['operation_id']), new RdStationCrmAdapter);

    $createPerson = Http::recorded()->first(fn (array $record): bool => $record[0]->method() === 'POST' && parse_url($record[0]->url(), PHP_URL_PATH) === '/crm/v2/contacts')[0];
    $createDeal = Http::recorded()->first(fn (array $record): bool => $record[0]->method() === 'POST' && parse_url($record[0]->url(), PHP_URL_PATH) === '/crm/v2/deals')[0];
    expect($createPerson->data()['data'])->toMatchArray([
        'name' => 'Ana Silva',
        'phones' => [['phone' => '+5511999998888', 'type' => 'mobile']],
        'owner_id' => '65a000000000000000000003',
    ])->and($createDeal->data()['data'])->toMatchArray([
        'name' => 'Plano anual',
        'contact_ids' => ['67c000000000000000000005'],
        'pipeline_id' => '65a000000000000000000001',
        'stage_id' => '65a000000000000000000002',
        'status' => 'ongoing',
        'total_price' => 1499.5,
        'owner_id' => '65a000000000000000000003',
    ])->and(CrmOperation::query()->findOrFail($result['operation_id'])->status)->toBe(CrmOperationStatus::Completed)
        ->and(CrmLinkedDeal::query()->firstOrFail()->external_deal_id)->toBe('65b000000000000000000005');
});

it('updates an existing open RD deal instead of creating a duplicate', function () {
    Http::fake(function (HttpRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ([$request->method(), $path]) {
            ['GET', '/crm/v2/contacts/67c000000000000000000002'] => Http::response(rdDealFixture('contact')),
            ['GET', '/crm/v2/deals'] => Http::response(rdDealFixture('open-deals')),
            ['PUT', '/crm/v2/deals/65b000000000000000000001'] => Http::response(rdDealFixture('deal-updated')),
            default => throw new RuntimeException('Unexpected RD Station request: '.$request->url()),
        };
    });
    Http::preventStrayRequests();
    $scenario = crmDealScenario();
    $scenario['integration']->forceFill(['crm_metadata' => [
        'pipelines' => [[
            'id' => '65a000000000000000000001',
            'name' => 'Vendas RD',
            'stages' => [['id' => '65a000000000000000000002', 'name' => 'Qualificação', 'status' => null, 'position' => 1]],
        ]],
        'owners' => [],
    ]])->save();
    $tool = new GuaranteeCrmDeal(
        $scenario['team'],
        $scenario['agent'],
        $scenario['contact'],
        new CrmOperationDispatcher,
        '65a000000000000000000001',
        '65a000000000000000000002',
    );
    CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => '67c000000000000000000002',
    ]);
    $result = crmDealToolResult($tool, ['title' => 'Renovação premium', 'estimated_value' => 2800]);

    processCrmDealOperation(CrmOperation::query()->findOrFail($result['operation_id']), new RdStationCrmAdapter);

    $updateDeal = Http::recorded()->first(fn (array $record): bool => $record[0]->method() === 'PUT' && parse_url($record[0]->url(), PHP_URL_PATH) === '/crm/v2/deals/65b000000000000000000001')[0];
    expect($updateDeal->data()['data'])->toBe(['name' => 'Renovação premium', 'total_price' => 2800.0])
        ->and(Http::recorded()->filter(fn (array $record): bool => $record[0]->method() === 'POST' && parse_url($record[0]->url(), PHP_URL_PATH) === '/crm/v2/deals'))->toHaveCount(0)
        ->and(CrmLinkedDeal::query()->firstOrFail()->external_deal_id)->toBe('65b000000000000000000001');
});
