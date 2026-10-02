<?php

use App\Ai\AgentToolFactory;
use App\Ai\AgentToolMode;
use App\Ai\Tools\MoveCrmDealStage;
use App\Ai\Tools\RegisterCrmNote;
use App\Crm\Adapters\RdStationCrmAdapter;
use App\Crm\Contracts\CrmProviderAdapter;
use App\Crm\CrmCardLookup;
use App\Crm\CrmFieldMappingService;
use App\Crm\CrmOperationDispatcher;
use App\Crm\CrmOperationProcessor;
use App\Ecommerce\ProductCatalogSearch;
use App\Enums\AgentObjective;
use App\Enums\CrmOperationStatus;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Knowledge\AgentInstructionTemplate;
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
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\Support\Crm\Fakes\FakeCrmProviderAdapter;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    Queue::fake();
});

function crmStageNoteScenario(): array
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
                'stages' => [
                    ['id' => 'stage-first', 'name' => 'Entrada', 'status' => null, 'position' => 1],
                    ['id' => 'stage-qualified', 'name' => 'Qualificação', 'status' => null, 'position' => 2],
                    ['id' => 'stage-won', 'name' => 'Ganho', 'status' => 'won', 'position' => 3],
                    ['id' => 'stage-lost', 'name' => 'Perdido', 'status' => 'lost', 'position' => 4],
                ],
            ]],
            'owners' => [],
        ],
        'crm_settings' => [],
    ]);
    $agent = Agent::factory()->for($team)->create([
        'objective' => AgentObjective::Sales,
        'crm_pipeline_id' => 'pipeline-1',
        'crm_initial_stage_id' => 'stage-first',
        'crm_allowed_stages' => [['stage_id' => 'stage-qualified', 'description' => 'Quando o Contato confirmar interesse.']],
    ]);
    $contact = Contact::factory()->for($team)->create([
        'name' => 'Ana Silva',
        'phone' => '+5511999998888',
    ]);

    return compact('team', 'integration', 'agent', 'contact');
}

function crmStageNotePerson(): array
{
    return [
        'id' => 'person-1',
        'name' => 'Ana Silva',
        'phone' => '+5511999998888',
        'email' => null,
        'organization_id' => null,
        'owner_id' => null,
        'fields' => [],
        'updated_at' => '2026-09-29T10:00:00Z',
    ];
}

function crmStageNoteDeal(array $attributes = []): array
{
    return array_merge([
        'id' => 'deal-1',
        'person_id' => 'person-1',
        'organization_id' => null,
        'pipeline_id' => 'pipeline-1',
        'stage_id' => 'stage-first',
        'status' => 'open',
        'loss_reason_id' => null,
        'value' => 900.0,
        'owner_id' => null,
        'updated_at' => '2026-09-29T10:00:00Z',
    ], $attributes);
}

function processCrmStageNoteOperation(CrmOperation $operation, CrmProviderAdapter $adapter): void
{
    (new ProcessCrmOperation($operation->getKey()))->handle(new CrmOperationProcessor($adapter, app(CrmFieldMappingService::class)), $adapter);
}

function crmStageNoteFixture(string $name): array
{
    return json_decode(file_get_contents(base_path('tests/Fixtures/Crm/RdStation/'.$name.'.json')), true, flags: JSON_THROW_ON_ERROR);
}

it('moves an open linked deal to an allowed stage and updates its canonical link', function (): void {
    $scenario = crmStageNoteScenario();
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [crmStageNotePerson()];
    $adapter->openDeals = [crmStageNoteDeal()];
    $tool = new MoveCrmDealStage(
        $scenario['team'],
        $scenario['agent'],
        $scenario['contact'],
        new CrmOperationDispatcher($adapter),
        'pipeline-1',
        [['stage_id' => 'stage-qualified', 'name' => 'Qualificação', 'description' => 'Quando o Contato confirmar interesse.']],
    );

    $result = json_decode($tool->handle(new ToolRequest(['stage_id' => 'stage-qualified'])), true, flags: JSON_THROW_ON_ERROR);
    $operation = CrmOperation::query()->findOrFail($result['operation_id']);
    expect($result['registered'])->toBeTrue()
        ->and($operation->status)->toBe(CrmOperationStatus::Pending)
        ->and($operation->type)->toBe('move_deal')
        ->and($adapter->moveDealCalls)->toBe([]);

    processCrmStageNoteOperation($operation, $adapter);

    $operation->refresh();
    expect($operation->status)->toBe(CrmOperationStatus::Completed)
        ->and($adapter->moveDealCalls)->toBe([['id' => 'deal-1', 'stage_id' => 'stage-qualified']])
        ->and(CrmLinkedDeal::query()->firstOrFail()->stage_id)->toBe('stage-qualified');
});

it('refuses closed or unconfigured stages and does not queue without an open deal', function (): void {
    $scenario = crmStageNoteScenario();
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [crmStageNotePerson()];
    $tool = new MoveCrmDealStage(
        $scenario['team'],
        $scenario['agent'],
        $scenario['contact'],
        new CrmOperationDispatcher($adapter),
        'pipeline-1',
        [['stage_id' => 'stage-qualified', 'name' => 'Qualificação', 'description' => 'Quando confirmar interesse.']],
    );

    $closedStageResult = json_decode($tool->handle(new ToolRequest(['stage_id' => 'stage-won'])), true, flags: JSON_THROW_ON_ERROR);
    $noDealResult = json_decode($tool->handle(new ToolRequest(['stage_id' => 'stage-qualified'])), true, flags: JSON_THROW_ON_ERROR);

    expect($closedStageResult['ok'])->toBeFalse()
        ->and($noDealResult['ok'])->toBeFalse()
        ->and($noDealResult['message'])->toContain('Negócio aberto')
        ->and(CrmOperation::query()->count())->toBe(0);
});

it('registers a note against the CRM person and open deal through a queued operation', function (): void {
    $scenario = crmStageNoteScenario();
    $link = CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => 'person-1',
    ]);
    $adapter = new FakeCrmProviderAdapter;
    $adapter->openDeals = [crmStageNoteDeal()];
    $tool = new RegisterCrmNote(
        $scenario['team'],
        $scenario['agent'],
        $scenario['contact'],
        new CrmOperationDispatcher($adapter),
        'pipeline-1',
    );

    $result = json_decode($tool->handle(new ToolRequest(['body' => '  Confirmou interesse no plano anual.  '])), true, flags: JSON_THROW_ON_ERROR);
    $operation = CrmOperation::query()->findOrFail($result['operation_id']);
    expect($operation->status)->toBe(CrmOperationStatus::Pending)
        ->and($operation->type)->toBe('register_note')
        ->and($operation->payload['body'])->toBe('Confirmou interesse no plano anual.')
        ->and($adapter->createNoteCalls)->toBe([]);

    processCrmStageNoteOperation($operation, $adapter);

    $operation->refresh();
    expect($operation->status)->toBe(CrmOperationStatus::Completed)
        ->and($operation->summary['note_registered'])->toBeTrue()
        ->and($adapter->createNoteCalls)->toBe([[
            'person_id' => 'person-1',
            'body' => 'Confirmou interesse no plano anual.',
            'deal_id' => 'deal-1',
        ]])
        ->and(CrmLinkedDeal::query()->firstOrFail()->crm_contact_link_id)->toBe($link->getKey());
});

it('simulates both CRM write tools in the Playground without creating operations', function (): void {
    $scenario = crmStageNoteScenario();
    $adapter = new FakeCrmProviderAdapter;
    $dispatcher = new CrmOperationDispatcher($adapter);
    $moveTool = new MoveCrmDealStage(
        $scenario['team'],
        $scenario['agent'],
        null,
        $dispatcher,
        'pipeline-1',
        [['stage_id' => 'stage-qualified', 'name' => 'Qualificação', 'description' => 'Quando confirmar interesse.']],
        true,
        '+5511999998888',
    );
    $noteTool = new RegisterCrmNote($scenario['team'], $scenario['agent'], null, $dispatcher, 'pipeline-1', true, '+5511999998888');

    $moveResult = json_decode($moveTool->handle(new ToolRequest(['stage_id' => 'stage-qualified'])), true, flags: JSON_THROW_ON_ERROR);
    $noteResult = json_decode($noteTool->handle(new ToolRequest(['body' => 'Resumo do atendimento.'])), true, flags: JSON_THROW_ON_ERROR);

    expect($moveResult['simulated'])->toBeTrue()
        ->and($noteResult['simulated'])->toBeTrue()
        ->and(CrmOperation::query()->count())->toBe(0)
        ->and($adapter->phoneSearches)->toBe([])
        ->and($adapter->createNoteCalls)->toBe([]);
});

it('defaults the four CRM actions and first pipeline stage for a sales agent created after connection', function (): void {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'crm_metadata' => ['pipelines' => [[
            'id' => 'pipeline-first',
            'name' => 'Vendas',
            'stages' => [
                ['id' => 'stage-first', 'name' => 'Entrada', 'status' => null, 'position' => 1],
                ['id' => 'stage-won', 'name' => 'Ganho', 'status' => 'won', 'position' => 2],
            ],
        ]]],
        'crm_metadata_loaded_at' => now(),
    ]);
    $agent = Agent::factory()->for($team)->create();
    $this->actingAs($user);

    $this->patch(route('agents.update', $agent), [
        'name' => $agent->name,
        'objective' => AgentObjective::Sales->value,
        'tone' => $agent->tone->value,
        'uses_emojis' => $agent->uses_emojis,
        'response_length' => $agent->response_length->value,
        'welcome_message' => $agent->welcome_message,
        'instructions' => [],
        'rules' => [],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
    ])->assertRedirect(route('agents.edit', $agent));

    expect($agent->fresh()->crm_lookup_enabled)->toBeTrue()
        ->and($agent->fresh()->crm_ensure_deal_enabled)->toBeTrue()
        ->and($agent->fresh()->crm_move_deal_enabled)->toBeTrue()
        ->and($agent->fresh()->crm_register_note_enabled)->toBeTrue()
        ->and($agent->fresh()->crm_pipeline_id)->toBe('pipeline-first')
        ->and($agent->fresh()->crm_initial_stage_id)->toBe('stage-first');
});

it('saves allowed stage criteria, rejects won and lost stages, and includes criteria in the prompt', function (): void {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'crm_metadata' => ['pipelines' => [[
            'id' => 'pipeline-1',
            'name' => 'Vendas',
            'stages' => [
                ['id' => 'stage-first', 'name' => 'Entrada', 'status' => null, 'position' => 1],
                ['id' => 'stage-qualified', 'name' => 'Qualificação', 'status' => null, 'position' => 2],
                ['id' => 'stage-won', 'name' => 'Ganho', 'status' => 'won', 'position' => 3],
                ['id' => 'stage-lost', 'name' => 'Perdido', 'status' => 'lost', 'position' => 4],
            ],
        ]]],
        'crm_metadata_loaded_at' => now(),
    ]);
    $agent = Agent::factory()->for($team)->create(['objective' => AgentObjective::Sales]);
    $this->actingAs($user);

    $validDetails = [
        'name' => 'Lia',
        'objective' => AgentObjective::Sales->value,
        'tone' => 'friendly',
        'uses_emojis' => false,
        'response_length' => 'short',
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => [],
        'rules' => [],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
        'crm_move_deal_enabled' => true,
        'crm_register_note_enabled' => true,
        'crm_pipeline_id' => 'pipeline-1',
        'crm_initial_stage_id' => 'stage-first',
        'crm_allowed_stages' => [['stage_id' => 'stage-qualified', 'description' => 'Quando o Contato confirmar interesse.']],
    ];

    $this->patch(route('agents.update', $agent), $validDetails)->assertRedirect(route('agents.edit', $agent));
    $this->post(route('agents.publish', $agent), $validDetails)->assertRedirect(route('agents.edit', $agent));
    expect($agent->fresh()->crm_allowed_stages)->toBe([['stage_id' => 'stage-qualified', 'description' => 'Quando o Contato confirmar interesse.']])
        ->and($agent->fresh()->crm_move_deal_enabled)->toBeTrue()
        ->and($agent->fresh()->crm_register_note_enabled)->toBeTrue();
    expect(AgentVersion::query()->where('agent_id', $agent->getKey())->firstOrFail()->crm_tools)->toMatchArray([
        'move_deal_enabled' => true,
        'register_note_enabled' => true,
        'pipeline_id' => 'pipeline-1',
        'initial_stage_id' => 'stage-first',
        'allowed_stages' => [['stage_id' => 'stage-qualified', 'description' => 'Quando o Contato confirmar interesse.']],
    ]);

    $this->patch(route('agents.update', $agent), array_merge($validDetails, [
        'crm_allowed_stages' => [['stage_id' => 'stage-won', 'description' => 'Não permitir.']],
    ]))->assertSessionHasErrors('crm_allowed_stages.0.stage_id');
    $this->patch(route('agents.update', $agent), array_merge($validDetails, [
        'crm_allowed_stages' => [['stage_id' => 'stage-lost', 'description' => 'Não permitir.']],
    ]))->assertSessionHasErrors('crm_allowed_stages.0.stage_id');

    $prompt = app(AgentInstructionTemplate::class)->render($agent->fresh());
    expect($prompt)->toContain('Etapas liberadas no CRM')
        ->and($prompt)->toContain('Qualificação: Quando o Contato confirmar interesse.');

    $detailsWithoutAllowedStages = $validDetails;
    unset($detailsWithoutAllowedStages['crm_allowed_stages']);
    $this->patch(route('agents.update', $agent), $detailsWithoutAllowedStages)->assertRedirect(route('agents.edit', $agent));
    expect($agent->fresh()->crm_allowed_stages)->toBe([]);
});

it('uses only published CRM settings for real tools when a legacy version lacks new action settings', function (): void {
    $scenario = crmStageNoteScenario();
    $scenario['agent']->versions()->create([
        'team_id' => $scenario['team']->getKey(),
        'number' => 1,
        'objective' => AgentObjective::Sales,
        'ecommerce_tools' => [],
        'crm_tools' => ['ensure_deal_enabled' => true],
        'published_at' => now(),
    ]);
    $scenario['agent']->forceFill([
        'crm_move_deal_enabled' => true,
        'crm_register_note_enabled' => false,
        'crm_pipeline_id' => 'draft-pipeline',
        'crm_initial_stage_id' => 'draft-stage',
        'crm_allowed_stages' => [['stage_id' => 'stage-qualified', 'description' => 'Somente no rascunho.']],
    ])->save();
    $adapter = new FakeCrmProviderAdapter;
    app()->instance(CrmOperationDispatcher::class, new CrmOperationDispatcher($adapter));
    $factory = new AgentToolFactory(
        app(KnowledgeBaseSearch::class),
        app(ProductCatalogSearch::class),
        crmCardLookup: new CrmCardLookup($adapter),
    );

    $tools = $factory->make($scenario['agent']->fresh(), $scenario['contact'], AgentToolMode::Real, []);
    $moveTool = collect($tools)->first(fn (mixed $tool): bool => $tool instanceof MoveCrmDealStage);
    $noteTool = collect($tools)->first(fn (mixed $tool): bool => $tool instanceof RegisterCrmNote);
    expect($moveTool)->toBeNull()
        ->and($noteTool)->toBeInstanceOf(RegisterCrmNote::class);

    $result = json_decode($noteTool->handle(new ToolRequest(['body' => 'Resumo publicado.'])), true, flags: JSON_THROW_ON_ERROR);
    $operation = CrmOperation::query()->findOrFail($result['operation_id']);
    expect($operation->payload['pipeline_id'])->toBe('pipeline-1');
});

it('uses the RD v2 fixtures for moving a deal and creating a deal note', function (): void {
    Http::fake(function (HttpRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ([$request->method(), $path]) {
            ['GET', '/crm/v2/deals'] => Http::response(crmStageNoteFixture('open-deals')),
            ['GET', '/crm/v2/deals/65b000000000000000000001'] => Http::response(crmStageNoteFixture('deal-updated')),
            ['PUT', '/crm/v2/deals/65b000000000000000000001'] => Http::response(crmStageNoteFixture('deal-stage-moved')),
            ['POST', '/crm/v2/deals/65b000000000000000000001/notes'] => Http::response(crmStageNoteFixture('note-created'), 201),
            default => throw new RuntimeException('Unexpected RD Station request: '.$request->url()),
        };
    });
    Http::preventStrayRequests();
    $scenario = crmStageNoteScenario();
    $scenario['integration']->forceFill(['crm_metadata' => ['pipelines' => [[
        'id' => '65a000000000000000000001',
        'name' => 'Vendas RD',
        'stages' => [
            ['id' => '65a000000000000000000002', 'name' => 'Entrada', 'status' => null, 'position' => 1],
            ['id' => '65a000000000000000000003', 'name' => 'Qualificação', 'status' => null, 'position' => 2],
        ],
    ]]]])->save();
    $scenario['agent']->forceFill([
        'crm_pipeline_id' => '65a000000000000000000001',
        'crm_allowed_stages' => [['stage_id' => '65a000000000000000000003', 'description' => 'Critério atendido.']],
    ])->save();
    CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => '67c000000000000000000002',
    ]);
    $adapter = new RdStationCrmAdapter;
    $dispatcher = new CrmOperationDispatcher($adapter);
    $moveTool = new MoveCrmDealStage(
        $scenario['team'],
        $scenario['agent'],
        $scenario['contact'],
        $dispatcher,
        '65a000000000000000000001',
        [['stage_id' => '65a000000000000000000003', 'name' => 'Qualificação', 'description' => 'Critério atendido.']],
    );
    $moveResult = json_decode($moveTool->handle(new ToolRequest(['stage_id' => '65a000000000000000000003'])), true, flags: JSON_THROW_ON_ERROR);
    processCrmStageNoteOperation(CrmOperation::query()->findOrFail($moveResult['operation_id']), $adapter);

    $noteTool = new RegisterCrmNote($scenario['team'], $scenario['agent'], $scenario['contact'], $dispatcher, '65a000000000000000000001');
    $noteResult = json_decode($noteTool->handle(new ToolRequest(['body' => 'Cliente pediu uma proposta anual.'])), true, flags: JSON_THROW_ON_ERROR);
    processCrmStageNoteOperation(CrmOperation::query()->findOrFail($noteResult['operation_id']), $adapter);

    $moveRequest = Http::recorded()->first(fn (array $record): bool => $record[0]->method() === 'PUT' && parse_url($record[0]->url(), PHP_URL_PATH) === '/crm/v2/deals/65b000000000000000000001')[0];
    $noteRequest = Http::recorded()->first(fn (array $record): bool => $record[0]->method() === 'POST' && parse_url($record[0]->url(), PHP_URL_PATH) === '/crm/v2/deals/65b000000000000000000001/notes')[0];
    expect($moveRequest->data()['data'])->toBe(['stage_id' => '65a000000000000000000003'])
        ->and($noteRequest->data()['data'])->toBe(['description' => 'Cliente pediu uma proposta anual.'])
        ->and(CrmOperation::query()->where('type', 'move_deal')->firstOrFail()->status)->toBe(CrmOperationStatus::Completed)
        ->and(CrmOperation::query()->where('type', 'register_note')->firstOrFail()->summary['note_registered'])->toBeTrue();
});
