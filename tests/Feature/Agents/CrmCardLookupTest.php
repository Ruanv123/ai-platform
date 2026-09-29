<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Ai\AgentToolFactory;
use App\Ai\AgentToolMode;
use App\Ai\Tools\ConsultCrmCard;
use App\Crm\Adapters\RdStationCrmAdapter;
use App\Crm\CrmCardLookup;
use App\Ecommerce\ProductCatalogSearch;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Enums\TeamRole;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request as ToolRequest;
use Tests\Support\Crm\Fakes\FakeCrmProviderAdapter;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
});

function crmCardIntegration(Team $team, array $overrides = []): Integration
{
    return Integration::factory()->for($team)->create(array_merge([
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
                'id' => '65a000000000000000000001',
                'name' => 'Novos negócios',
                'stages' => [['id' => '65a000000000000000000002', 'name' => 'Qualificação', 'status' => null, 'position' => 1]],
            ]],
            'fields' => [
                ['id' => 'field-visible', 'name' => 'Interesse', 'slug' => 'interest', 'entity' => 'contact', 'type' => 'text', 'options' => []],
                ['id' => 'field-private', 'name' => 'Anotação interna', 'slug' => 'internal_note', 'entity' => 'contact', 'type' => 'text', 'options' => []],
                ['id' => 'field-deal', 'name' => 'Observação do negócio', 'slug' => 'deal_note', 'entity' => 'deal', 'type' => 'text', 'options' => []],
            ],
            'owners' => [
                ['id' => '65a000000000000000000003', 'name' => 'Maria Vendedora', 'email' => null],
                ['id' => '65a000000000000000000004', 'name' => 'João Vendedor', 'email' => null],
            ],
        ],
        'crm_settings' => ['agent_visible_field_ids' => ['field-visible']],
        'crm_metadata_loaded_at' => now(),
    ], $overrides));
}

function crmCardFakePerson(string $id, string $updatedAt, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'name' => 'Pessoa '.$id,
        'phone' => '+5511999998888',
        'email' => null,
        'organization_id' => null,
        'owner_id' => '65a000000000000000000003',
        'fields' => ['interest' => 'Interesse '.$id, 'internal_note' => 'Dado privado'],
        'updated_at' => $updatedAt,
    ], $overrides);
}

function crmCardToolResult(ConsultCrmCard $tool): array
{
    return json_decode($tool->handle(new ToolRequest([])), true, flags: JSON_THROW_ON_ERROR);
}

function crmCardPlaygroundPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Lia',
        'objective' => AgentObjective::Sales->value,
        'crm_lookup_enabled' => true,
        'crm_lookup_phone' => '11 99999-8888',
        'tone' => AgentTone::Friendly->value,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short->value,
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => [],
        'rules' => [],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
        'history' => [],
        'message' => 'Consulte minha ficha.',
    ], $overrides);
}

it('normalizes the search phone, chooses the newest CRM duplicate, and creates only a contact link', function () {
    $team = Team::factory()->create();
    crmCardIntegration($team);
    $contact = Contact::factory()->for($team)->create(['phone' => '+5511999998888']);
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [
        crmCardFakePerson('67c000000000000000000001', '2025-01-01T10:00:00Z'),
        crmCardFakePerson('67c000000000000000000002', '2025-02-01T10:00:00Z', ['fields' => ['interest' => 'Consulta recente', 'internal_note' => 'não mostrar']]),
    ];

    $result = crmCardToolResult(new ConsultCrmCard($team, $contact, new CrmCardLookup($adapter)));

    expect($result['ok'])->toBeTrue()
        ->and($result['fields'])->toBe([['name' => 'Interesse', 'value' => 'Consulta recente']])
        ->and($adapter->phoneSearches)->toBe(['+5511999998888'])
        ->and($adapter->lookupBudgets[0])->toBeLessThanOrEqual(5)
        ->and(CrmContactLink::query()->where('contact_id', $contact->id)->value('external_person_id'))->toBe('67c000000000000000000002')
        ->and(CrmContactLink::query()->count())->toBe(1);
});

it('does not create a CRM person or link when the phone search has no match', function () {
    $team = Team::factory()->create();
    crmCardIntegration($team);
    $contact = Contact::factory()->for($team)->create(['phone' => '+5511999998888']);
    $adapter = new FakeCrmProviderAdapter;

    $result = crmCardToolResult(new ConsultCrmCard($team, $contact, new CrmCardLookup($adapter)));

    expect($result)->toBe(['ok' => true, 'found' => false, 'message' => 'Nenhuma ficha encontrada no CRM.'])
        ->and($adapter->phoneSearches)->toBe(['+5511999998888'])
        ->and(CrmContactLink::query()->count())->toBe(0);
});

it('returns only visible contact fields, open deals, and owner names without activities', function () {
    $team = Team::factory()->create();
    crmCardIntegration($team);
    $contact = Contact::factory()->for($team)->create(['phone' => '+5511999998888']);
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [crmCardFakePerson('67c000000000000000000002', '2025-02-01T10:00:00Z', [
        'fields' => ['interest' => 'Avalia o plano premium', 'internal_note' => 'Nunca enviar', 'deal_note' => 'interno'],
    ])];
    $adapter->openDeals = [[
        'id' => '65b000000000000000000001',
        'person_id' => '67c000000000000000000002',
        'organization_id' => null,
        'pipeline_id' => '65a000000000000000000001',
        'stage_id' => '65a000000000000000000002',
        'status' => 'open',
        'loss_reason_id' => null,
        'value' => 1499.5,
        'owner_id' => '65a000000000000000000004',
        'updated_at' => '2025-02-01T11:00:00Z',
    ]];

    $result = crmCardToolResult(new ConsultCrmCard($team, $contact, new CrmCardLookup($adapter)));

    expect($result)->toBe([
        'ok' => true,
        'found' => true,
        'fields' => [['name' => 'Interesse', 'value' => 'Avalia o plano premium']],
        'open_deals' => [[
            'pipeline' => 'Novos negócios',
            'stage' => 'Qualificação',
            'value' => 1499.5,
            'owner_name' => 'João Vendedor',
        ]],
        'owner_name' => 'Maria Vendedora',
    ])
        ->and(array_key_exists('activities', $result))->toBeFalse()
        ->and($adapter->dealSearches)->toBe(['67c000000000000000000002']);
});

it('uses an existing link by external person ID even when the local phone is invalid', function () {
    $team = Team::factory()->create();
    $integration = crmCardIntegration($team);
    $contact = Contact::factory()->for($team)->create(['phone' => 'telefone inválido']);
    CrmContactLink::query()->create([
        'team_id' => $team->id,
        'contact_id' => $contact->id,
        'integration_id' => $integration->id,
        'external_person_id' => 'person-linked',
    ]);
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [crmCardFakePerson('person-linked', '2025-02-01T10:00:00Z')];

    $result = crmCardToolResult(new ConsultCrmCard($team, $contact, new CrmCardLookup($adapter)));

    expect($result['found'])->toBeTrue()
        ->and($adapter->phoneSearches)->toBe([])
        ->and($adapter->personReads)->toBe(['person-linked']);
});

it('returns ficha indisponível for CRM failures and keeps the lookup within its shared time budget', function () {
    $team = Team::factory()->create();
    crmCardIntegration($team);
    $contact = Contact::factory()->for($team)->create(['phone' => '+5511999998888']);
    $adapter = new FakeCrmProviderAdapter;
    $adapter->lookupFailure = new RuntimeException('CRM timeout');

    $result = crmCardToolResult(new ConsultCrmCard($team, $contact, new CrmCardLookup($adapter)));

    expect($result)->toBe(['ok' => false, 'message' => 'Ficha indisponível'])
        ->and($adapter->lookupBudgets)->toHaveCount(1)
        ->and($adapter->lookupBudgets[0])->toBeLessThanOrEqual(5)
        ->and(CrmContactLink::query()->count())->toBe(0);
});

it('gates the CRM action on connection and the Agent setting, defaulting Sales and Qualification Agents on', function () {
    $team = Team::factory()->create();
    crmCardIntegration($team);
    $adapter = new FakeCrmProviderAdapter;
    $factory = new AgentToolFactory(
        app(KnowledgeBaseSearch::class),
        app(ProductCatalogSearch::class),
        crmCardLookup: new CrmCardLookup($adapter),
    );
    $agent = Agent::factory()->for($team)->create([
        'objective' => AgentObjective::Sales,
        'crm_lookup_enabled' => null,
    ]);

    $tools = $factory->make($agent, null, AgentToolMode::Simulated, []);
    expect(collect($tools)->contains(fn ($tool): bool => $tool instanceof ConsultCrmCard))->toBeTrue();

    $agent->forceFill(['crm_lookup_enabled' => false])->save();
    $tools = $factory->make($agent->fresh(), null, AgentToolMode::Simulated, []);
    expect(collect($tools)->contains(fn ($tool): bool => $tool instanceof ConsultCrmCard))->toBeFalse();

    $agent->forceFill(['crm_lookup_enabled' => null, 'objective' => AgentObjective::Qualification])->save();
    $tools = $factory->make($agent->fresh(), null, AgentToolMode::Simulated, []);
    expect(collect($tools)->contains(fn ($tool): bool => $tool instanceof ConsultCrmCard))->toBeTrue();

    Integration::query()->where('type', 'crm')->update(['status' => 'error']);
    $tools = $factory->make($agent->fresh(), null, AgentToolMode::Simulated, []);
    expect(collect($tools)->contains(fn ($tool): bool => $tool instanceof ConsultCrmCard))->toBeFalse();
});

it('loads contact, owner, and deal metadata from RD and rejects unexpected HTTP requests', function () {
    Http::fake(function (HttpRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ($path) {
            '/crm/v2/contacts' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/Crm/RdStation/contacts-search.json')), true, flags: JSON_THROW_ON_ERROR)),
            '/crm/v2/contacts/67c000000000000000000002' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/Crm/RdStation/contact.json')), true, flags: JSON_THROW_ON_ERROR)),
            '/crm/v2/deals' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/Crm/RdStation/open-deals.json')), true, flags: JSON_THROW_ON_ERROR)),
            default => throw new RuntimeException('Unexpected RD Station request: '.$request->url()),
        };
    });
    Http::preventStrayRequests();
    $team = Team::factory()->create();
    $integration = crmCardIntegration($team);
    $adapter = app(RdStationCrmAdapter::class);

    $people = $adapter->findPeopleByPhone($integration, '+5511999998888', 5);
    $person = $adapter->findPerson($integration, '67c000000000000000000002', 5);
    $deals = $adapter->findOpenDeals($integration, '67c000000000000000000002', 5);

    expect($people)->toHaveCount(2)
        ->and($people[0]['id'])->toBe('67c000000000000000000002')
        ->and($people[0]['phone'])->toBe('+5511999998888')
        ->and($person['fields'])->toBe(['origin' => 'Indicação', 'internal_note' => 'confidencial'])
        ->and($deals)->toHaveCount(2)
        ->and(array_column($deals, 'status'))->toBe(['open', 'open'])
        ->and($deals[0])->toMatchArray(['person_id' => '67c000000000000000000002', 'pipeline_id' => '65a000000000000000000001', 'stage_id' => '65a000000000000000000002', 'value' => 1999.9, 'owner_id' => '65a000000000000000000003'])
        ->and($deals[1])->toMatchArray(['status' => 'open', 'owner_id' => '65a000000000000000000003']);

    Http::assertSent(function (HttpRequest $request): bool {
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return parse_url($request->url(), PHP_URL_PATH) === '/crm/v2/contacts'
            && str_contains((string) ($query['filter'] ?? ''), 'phone:"(11) 9999-8888"')
            && str_contains((string) ($query['filter'] ?? ''), ' or ');
    });
    Http::assertSent(function (HttpRequest $request): bool {
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return parse_url($request->url(), PHP_URL_PATH) === '/crm/v2/deals'
            && ($query['filter'] ?? null) === 'contact_id:"67c000000000000000000002" status:(ongoing,paused)';
    });
    Http::assertSentCount(3);
});

it('allows Owners and Admins to configure visible contact fields but denies Members', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $integration = crmCardIntegration($team);
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($owner)
        ->put(route('integrations.crm.fields.visible-to-agent.update'), ['visible_field_ids' => ['field-visible']])
        ->assertRedirect(route('integrations.show', 'crm'));

    $admin->switchTeam($team);
    $this->actingAs($admin)
        ->put(route('integrations.crm.fields.visible-to-agent.update'), ['visible_field_ids' => []])
        ->assertRedirect(route('integrations.show', 'crm'));

    $member->switchTeam($team);
    $this->actingAs($member)
        ->put(route('integrations.crm.fields.visible-to-agent.update'), ['visible_field_ids' => ['field-visible']])
        ->assertForbidden();

    expect($integration->fresh()->crm_settings['agent_visible_field_ids'])->toBe([]);
});

it('uses the real RD Station CRM lookup from the Playground', function () {
    Http::fake(function (HttpRequest $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return match ($path) {
            '/crm/v2/contacts' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/Crm/RdStation/contacts-search.json')), true, flags: JSON_THROW_ON_ERROR)),
            '/crm/v2/deals' => Http::response(json_decode(file_get_contents(base_path('tests/Fixtures/Crm/RdStation/open-deals.json')), true, flags: JSON_THROW_ON_ERROR)),
            default => throw new RuntimeException('Unexpected RD Station request: '.$request->url()),
        };
    });
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    crmCardIntegration($user->company());
    PlaygroundAgent::fake([
        new ToolCall((string) Str::uuid7(), 'consultar_ficha_crm', []),
        'Encontrei sua ficha.',
    ]);

    $response = $this->actingAs($user)->postJson(route('agents.playground.stream'), crmCardPlaygroundPayload());
    $content = $response->streamedContent();

    $response->assertOk();
    expect($content)->toContain('"tool_name":"consultar_ficha_crm"')
        ->and($content)->toContain('1999.9')
        ->and(CrmContactLink::query()->count())->toBe(0);
    Http::assertSentCount(2);
});
