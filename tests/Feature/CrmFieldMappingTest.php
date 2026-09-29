<?php

use App\Crm\Adapters\RdStationCrmAdapter;
use App\Crm\CrmFieldMappingService;
use App\Crm\CrmOperationProcessor;
use App\Crm\CrmWebhookProcessor;
use App\Enums\CrmOperationStatus;
use App\Enums\TeamRole;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Jobs\ProcessCrmWebhook;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\CrmContactLink;
use App\Models\CrmFieldMapping;
use App\Models\CrmOperation;
use App\Models\Integration;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    Queue::fake();
});

function crmFieldMappingScenario(): array
{
    $team = Team::factory()->create();
    $owner = User::factory()->withTeam($team)->create();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'crm-access-token',
            'refresh_token' => 'crm-refresh-token',
            'expires_at' => now()->addHour()->timestamp,
            'webhook_secret_hash' => hash('sha256', 'crm-webhook-secret'),
        ],
        'crm_metadata_loaded_at' => now(),
        'crm_metadata' => [
            'account' => ['teams' => []],
            'pipelines' => [],
            'fields' => [
                [
                    'id' => 'crm-text-1',
                    'name' => 'Nome interno',
                    'slug' => 'internal_name',
                    'type' => 'text',
                    'entity' => 'contact',
                    'options' => [],
                ],
                [
                    'id' => 'crm-number-1',
                    'name' => 'Pontuação',
                    'slug' => 'score',
                    'type' => 'number',
                    'entity' => 'contact',
                    'options' => [],
                ],
                [
                    'id' => 'crm-choice-1',
                    'name' => 'Origem',
                    'slug' => 'origin',
                    'type' => 'option',
                    'entity' => 'contact',
                    'options' => ['Site', 'Indicação'],
                ],
                [
                    'id' => 'crm-deal-field',
                    'name' => 'Campo do negócio',
                    'slug' => 'deal_field',
                    'type' => 'text',
                    'entity' => 'deal',
                    'options' => [],
                ],
            ],
            'owners' => [],
            'lost_reasons' => [],
        ],
        'crm_settings' => [],
    ]);
    $contact = Contact::factory()->for($team)->create([
        'name' => 'Ana Silva',
        'phone' => '+5511999998888',
    ]);

    return compact('team', 'owner', 'integration', 'contact');
}

it('shows CRM and contact fields and supports creating updating and deleting mappings', function (): void {
    $scenario = crmFieldMappingScenario();
    $contactField = ContactField::factory()->for($scenario['team'])->create([
        'name' => 'Nome interno',
        'type' => 'text',
        'options' => null,
    ]);

    $this->actingAs($scenario['owner'])
        ->get(route('integrations.show', 'crm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('integrations/show')
            ->has('crmContactFields', 3)
            ->has('contactFields', 1)
            ->where('contactFields.0.id', (string) $contactField->getKey())
            ->has('crmFieldMappings', 0));

    $this->actingAs($scenario['owner'])
        ->post(route('integrations.crm.field-mappings.store'), [
            'contact_field_id' => $contactField->getKey(),
            'crm_field_id' => 'crm-text-1',
            'direction' => 'to_crm',
        ])
        ->assertRedirect(route('integrations.show', 'crm'));

    $mapping = CrmFieldMapping::query()->firstOrFail();
    expect($mapping->direction)->toBe('to_crm');

    $this->actingAs($scenario['owner'])
        ->put(route('integrations.crm.field-mappings.update', $mapping), [
            'contact_field_id' => $contactField->getKey(),
            'crm_field_id' => 'crm-text-1',
            'direction' => 'from_crm',
        ])
        ->assertRedirect(route('integrations.show', 'crm'));

    expect($mapping->fresh()->direction)->toBe('from_crm');

    $this->actingAs($scenario['owner'])
        ->delete(route('integrations.crm.field-mappings.destroy', $mapping))
        ->assertRedirect(route('integrations.show', 'crm'));

    $this->assertDatabaseMissing('crm_field_mappings', ['id' => $mapping->getKey()]);
});

it('rejects incompatible field types and incomplete choice mappings', function (): void {
    $scenario = crmFieldMappingScenario();
    $dateField = ContactField::factory()->for($scenario['team'])->create([
        'name' => 'Data de renovação',
        'type' => 'date',
    ]);
    $choiceField = ContactField::factory()->for($scenario['team'])->create([
        'name' => 'Origem local',
        'type' => 'choice',
        'options' => ['Indicação', 'Site'],
    ]);

    $this->actingAs($scenario['owner'])
        ->from(route('integrations.show', 'crm'))
        ->post(route('integrations.crm.field-mappings.store'), [
            'contact_field_id' => $dateField->getKey(),
            'crm_field_id' => 'crm-text-1',
            'direction' => 'to_crm',
        ])
        ->assertSessionHasErrors('crm_field_id');

    $this->actingAs($scenario['owner'])
        ->from(route('integrations.show', 'crm'))
        ->post(route('integrations.crm.field-mappings.store'), [
            'contact_field_id' => $choiceField->getKey(),
            'crm_field_id' => 'crm-choice-1',
            'direction' => 'to_crm',
            'option_mappings' => ['Indicação' => 'Indicação'],
        ])
        ->assertSessionHasErrors('option_mappings');

    $this->actingAs($scenario['owner'])
        ->post(route('integrations.crm.field-mappings.store'), [
            'contact_field_id' => $choiceField->getKey(),
            'crm_field_id' => 'crm-choice-1',
            'direction' => 'to_crm',
            'option_mappings' => ['Indicação' => 'Indicação', 'Site' => 'Site'],
        ])
        ->assertRedirect(route('integrations.show', 'crm'));

    expect(CrmFieldMapping::query()->where('contact_field_id', $choiceField->getKey())->value('option_mappings'))
        ->toBe(['Indicação' => 'Indicação', 'Site' => 'Site']);
});

it('limits field mapping management to owners and admins', function (): void {
    $scenario = crmFieldMappingScenario();
    $member = User::factory()->create();
    $scenario['team']->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($scenario['team']);
    $contactField = ContactField::factory()->for($scenario['team'])->create(['type' => 'text']);

    $this->actingAs($member)
        ->post(route('integrations.crm.field-mappings.store'), [
            'contact_field_id' => $contactField->getKey(),
            'crm_field_id' => 'crm-text-1',
            'direction' => 'to_crm',
        ])
        ->assertForbidden();

    $admin = User::factory()->create();
    $scenario['team']->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($scenario['team']);

    $this->actingAs($admin)
        ->post(route('integrations.crm.field-mappings.store'), [
            'contact_field_id' => $contactField->getKey(),
            'crm_field_id' => 'crm-text-1',
            'direction' => 'to_crm',
        ])
        ->assertRedirect(route('integrations.show', 'crm'));
});

it('queues writes only for linked contacts and masks sensitive values in operation summaries', function (): void {
    $scenario = crmFieldMappingScenario();
    $contactField = ContactField::factory()->for($scenario['team'])->create([
        'name' => 'Identificador privado',
        'type' => 'text',
        'is_sensitive' => true,
    ]);
    CrmFieldMapping::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'contact_field_id' => $contactField->getKey(),
        'crm_field_id' => 'crm-text-1',
        'direction' => 'to_crm',
    ]);

    $value = $scenario['contact']->fieldValues()->create([
        'contact_field_id' => $contactField->getKey(),
        'value' => 'segredo-123',
    ]);
    expect(CrmOperation::query()->count())->toBe(0);

    CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => 'person-linked',
    ]);
    $value->update(['value' => 'segredo-456']);

    $operation = CrmOperation::query()->firstOrFail();
    expect($operation->type)->toBe('update_person_fields')
        ->and($operation->payload['fields'])->toBe(['internal_name' => 'segredo-456'])
        ->and($operation->summary['contact_fields'][(string) $contactField->getKey()])->toBe('***')
        ->and($operation->summary)->not->toContain('segredo-456');

    Http::fake([
        '*crm/v2/contacts/person-linked' => Http::response(crmFieldMappingFixture('person-fields-updated')),
    ]);
    Http::preventStrayRequests();
    $adapter = new RdStationCrmAdapter;
    (new ProcessCrmOperation((int) $operation->getKey()))
        ->handle(new CrmOperationProcessor($adapter, app(CrmFieldMappingService::class)), $adapter);
    Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/crm/v2/contacts/person-linked')
        && data_get($request->data(), 'data.custom_fields') === ['internal_name' => 'segredo-456']);

    expect($operation->fresh()->status)->toBe(CrmOperationStatus::Completed)
        ->and($operation->fresh()->summary['fields_updated'])->toBeTrue();
});

it('writes all mapped values when a CRM webhook first links an existing contact', function (): void {
    $scenario = crmFieldMappingScenario();
    $nameField = ContactField::factory()->for($scenario['team'])->create([
        'name' => 'Nome interno',
        'type' => 'text',
    ]);
    $scoreField = ContactField::factory()->for($scenario['team'])->create([
        'name' => 'Pontuação',
        'type' => 'number',
    ]);
    CrmFieldMapping::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'contact_field_id' => $nameField->getKey(),
        'crm_field_id' => 'crm-text-1',
        'direction' => 'to_crm',
    ]);
    CrmFieldMapping::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'contact_field_id' => $scoreField->getKey(),
        'crm_field_id' => 'crm-number-1',
        'direction' => 'to_crm',
    ]);
    $scenario['contact']->fieldValues()->createMany([
        ['contact_field_id' => $nameField->getKey(), 'value' => 'Ana Silva'],
        ['contact_field_id' => $scoreField->getKey(), 'value' => '8'],
    ]);
    Http::fake([
        '*crm/v2/deals/65b000000000000000000001' => Http::response([
            'data' => [
                'id' => '65b000000000000000000001',
                'contact_ids' => ['67c000000000000000000002'],
                'pipeline_id' => 'pipeline-1',
                'stage_id' => 'stage-1',
                'status' => 'ongoing',
                'total_price' => 2800,
                'updated_at' => '2026-09-29T10:02:00Z',
            ],
        ]),
        '*crm/v2/contacts/67c000000000000000000002' => Http::response([
            'data' => [
                'id' => '67c000000000000000000002',
                'name' => 'Ana Silva',
                'phones' => [['phone' => '+55 (11) 99999-8888']],
                'custom_fields' => [],
                'updated_at' => '2026-09-29T10:02:00Z',
            ],
        ]),
    ]);
    Http::preventStrayRequests();

    $this->postJson(route('webhooks.crm.rd-station'), crmFieldMappingDealWebhook(), [
        'X-RDStation-CRM-Webhook-Key' => 'crm-webhook-secret',
    ])->assertOk();

    (new ProcessCrmWebhook(
        (string) $scenario['integration']->getKey(),
        $scenario['team']->getKey(),
        'deal',
        '65b000000000000000000001',
        false,
    ))->handle(app(CrmWebhookProcessor::class));

    $operation = CrmOperation::query()->where('contact_id', $scenario['contact']->getKey())->firstOrFail();
    expect($operation->type)->toBe('update_person_fields')
        ->and($operation->payload['fields'])->toMatchArray([
            'internal_name' => 'Ana Silva',
            'score' => '8',
        ])
        ->and(CrmContactLink::query()->where('contact_id', $scenario['contact']->getKey())->value('external_person_id'))
        ->toBe('67c000000000000000000002');
});

it('applies CRM updates from the webhook without queuing an echo operation', function (): void {
    $scenario = crmFieldMappingScenario();
    $contactField = ContactField::factory()->for($scenario['team'])->create([
        'name' => 'Origem local',
        'type' => 'choice',
        'options' => ['Referral', 'Website'],
    ]);
    CrmFieldMapping::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'contact_field_id' => $contactField->getKey(),
        'crm_field_id' => 'crm-choice-1',
        'direction' => 'from_crm',
        'option_mappings' => ['Referral' => 'Indicação', 'Website' => 'Site'],
    ]);
    CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => '67c000000000000000000002',
    ]);
    Http::fake([
        '*crm/v2/contacts/67c000000000000000000002' => Http::response(
            crmFieldMappingContactResponse(),
        ),
    ]);
    Http::preventStrayRequests();

    $this->postJson(route('webhooks.crm.rd-station'), crmFieldMappingPersonWebhook(), [
        'X-RDStation-CRM-Webhook-Key' => 'crm-webhook-secret',
    ])->assertOk();

    (new ProcessCrmWebhook(
        (string) $scenario['integration']->getKey(),
        $scenario['team']->getKey(),
        'person',
        '67c000000000000000000002',
        false,
    ))->handle(app(CrmWebhookProcessor::class));

    expect($scenario['contact']->fieldValues()->where('contact_field_id', $contactField->getKey())->value('value'))
        ->toBe('Referral')
        ->and(CrmOperation::query()->count())->toBe(0);
});

function crmFieldMappingDealWebhook(): array
{
    return [
        'event_name' => 'crm_deal_updated',
        'event_timestamp' => now()->toIso8601String(),
        'transaction_uuid' => '00000000-0052-4400-B753-746174696F6E',
        'document' => ['id' => '65b000000000000000000001'],
    ];
}

function crmFieldMappingPersonWebhook(): array
{
    return [
        'event_name' => 'crm_contact_updated',
        'event_timestamp' => now()->toIso8601String(),
        'transaction_uuid' => '00000000-0052-4400-B753-746174696F6E',
        'document' => ['id' => '67c000000000000000000002'],
    ];
}

function crmFieldMappingContactResponse(): array
{
    return [
        'data' => [
            'id' => '67c000000000000000000002',
            'name' => 'Pessoa atualizada',
            'phones' => [['phone' => '+55 (11) 99999-8888']],
            'custom_fields' => ['origin' => 'Indicação'],
            'updated_at' => '2025-02-01T10:00:00Z',
        ],
    ];
}

function crmFieldMappingFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path('tests/Fixtures/Crm/RdStation/'.$name.'.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}
