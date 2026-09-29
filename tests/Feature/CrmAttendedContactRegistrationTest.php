<?php

use App\Ai\Agents\PlaygroundAgent;
use App\Ai\Agents\WhatsAppAgent;
use App\Crm\Contracts\CrmProviderAdapter;
use App\Crm\CrmOperationProcessor;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Enums\ChannelType;
use App\Enums\CrmOperationStatus;
use App\Enums\TeamRole;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmOperation;
use App\Models\Integration;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTestConnection;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Crm\Fakes\FakeCrmProviderAdapter;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    $this->withoutVite();
});

function attendedContactCrmScenario(bool $enabled = true, int $minimumMessages = 2, int $existingMessages = 1): array
{
    $team = Team::factory()->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->create([
        'type' => ChannelType::WhatsApp,
        'agent_id' => $agent->getKey(),
    ]);
    $account = WhatsAppAccount::factory()->for($team, 'team')->create([
        'access_token' => 'official-access-token',
        'app_secret' => 'official-app-secret',
        'waba_id' => 'waba-fixture',
    ]);
    WhatsAppPhoneNumber::factory()->for($channel)->create([
        'phone_number_id' => 'official-phone-number-id',
        'whatsapp_account_id' => $account->getKey(),
    ]);
    $contact = Contact::factory()->for($team)->create([
        'name' => 'Ana Silva',
        'phone' => '5511999999999',
    ]);
    $integration = Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'credentials' => [
            'access_token' => 'rd-access-token',
            'refresh_token' => 'rd-refresh-token',
            'expires_at' => now()->addHour()->timestamp,
        ],
        'crm_metadata_loaded_at' => now(),
        'crm_settings' => [
            'register_attended_contacts' => $enabled,
            'minimum_attended_contact_messages' => $minimumMessages,
            'default_owner_id' => 'owner-default',
        ],
    ]);

    for ($index = 1; $index <= $existingMessages; $index++) {
        Message::factory()->for($contact)->for($channel)->incoming()->responded()->create([
            'content' => 'Mensagem anterior '.$index,
        ]);
    }

    return compact('team', 'agent', 'channel', 'contact', 'integration');
}

function postAttendedContactOfficialMessage(): void
{
    $payload = json_decode(
        file_get_contents(base_path('tests/Fixtures/whatsapp/oficial/message-text.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $payload['entry'][0]['changes'][0]['value']['messages'][0]['timestamp'] = (string) now()->timestamp;
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'official-app-secret');

    test()->call('POST', route('webhooks.whatsapp.app-proprio'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body)->assertOk();
}

function fakeOfficialWhatsAppSend(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://graph.facebook.com/v23.0/official-phone-number-id/messages' => Http::response([
            'messages' => [['id' => 'attended-contact-outgoing-message']],
        ]),
    ]);
}

it('registers and links an attended WhatsApp contact once the message threshold is reached', function () {
    $scenario = attendedContactCrmScenario();
    $adapter = new FakeCrmProviderAdapter;
    app()->instance(CrmProviderAdapter::class, $adapter);
    WhatsAppAgent::fake(['Resposta oficial do atendimento.']);
    fakeOfficialWhatsAppSend();

    postAttendedContactOfficialMessage();

    $operation = CrmOperation::query()->where('contact_id', $scenario['contact']->getKey())->firstOrFail();
    expect($operation->origin)->toBe('automatic')
        ->and($operation->type)->toBe('ensure_person')
        ->and($operation->payload['default_owner_id'])->toBe('owner-default');

    if ($operation->fresh()->status === CrmOperationStatus::Pending) {
        (new ProcessCrmOperation((int) $operation->getKey()))
            ->handle(new CrmOperationProcessor($adapter), $adapter);
    }

    expect($operation->fresh()->status)->toBe(CrmOperationStatus::Completed)
        ->and($operation->fresh()->summary['person_linked'])->toBeTrue()
        ->and(CrmContactLink::query()->where('contact_id', $scenario['contact']->getKey())->value('external_person_id'))->toBe('person-1')
        ->and($adapter->phoneSearches)->toBe(['+5511999999999'])
        ->and($adapter->createPersonCalls[0]['owner_id'])->toBe('owner-default');
});

it('links to the newest existing person with the matching phone without creating another person', function () {
    $scenario = attendedContactCrmScenario();
    $adapter = new FakeCrmProviderAdapter;
    $adapter->people = [[
        'id' => 'existing-person',
        'name' => 'Ana Silva',
        'phone' => '+5511999999999',
        'email' => null,
        'organization_id' => null,
        'owner_id' => 'owner-existing',
        'fields' => [],
        'updated_at' => now()->toIso8601String(),
    ]];
    app()->instance(CrmProviderAdapter::class, $adapter);
    WhatsAppAgent::fake(['Resposta oficial do atendimento.']);
    fakeOfficialWhatsAppSend();

    postAttendedContactOfficialMessage();
    $operation = CrmOperation::query()->where('contact_id', $scenario['contact']->getKey())->firstOrFail();
    (new ProcessCrmOperation((int) $operation->getKey()))
        ->handle(new CrmOperationProcessor($adapter), $adapter);

    expect(CrmContactLink::query()->where('contact_id', $scenario['contact']->getKey())->value('external_person_id'))->toBe('existing-person')
        ->and($adapter->createPersonCalls)->toBe([]);
});

it('does not register a contact when the option is off or the threshold is not reached', function (bool $enabled, int $minimumMessages, int $existingMessages) {
    $scenario = attendedContactCrmScenario($enabled, $minimumMessages, $existingMessages);
    WhatsAppAgent::fake(['Resposta oficial do atendimento.']);
    fakeOfficialWhatsAppSend();

    postAttendedContactOfficialMessage();

    expect(CrmOperation::query()->where('contact_id', $scenario['contact']->getKey())->count())->toBe(0)
        ->and(CrmContactLink::query()->where('contact_id', $scenario['contact']->getKey())->count())->toBe(0);
})->with([
    'disabled despite reaching the threshold' => [false, 2, 3],
    'below the threshold' => [true, 3, 1],
]);

it('does not create another operation for an already linked contact', function () {
    $scenario = attendedContactCrmScenario(enabled: true, minimumMessages: 1, existingMessages: 1);
    CrmContactLink::query()->create([
        'team_id' => $scenario['team']->getKey(),
        'contact_id' => $scenario['contact']->getKey(),
        'integration_id' => $scenario['integration']->getKey(),
        'external_person_id' => 'already-linked-person',
    ]);
    WhatsAppAgent::fake(['Resposta oficial do atendimento.']);
    fakeOfficialWhatsAppSend();

    postAttendedContactOfficialMessage();

    expect(CrmOperation::query()->where('contact_id', $scenario['contact']->getKey())->count())->toBe(0);
});

it('requires the agent to have responded to enough incoming messages', function () {
    $scenario = attendedContactCrmScenario(enabled: true, minimumMessages: 2, existingMessages: 0);
    $unansweredChannel = Channel::factory()->for($scenario['team'])->create([
        'type' => ChannelType::WebsiteChat,
    ]);
    Message::factory()->for($scenario['contact'])->for($unansweredChannel)->incoming()->create();
    WhatsAppAgent::fake(['Resposta oficial do atendimento.']);
    fakeOfficialWhatsAppSend();

    postAttendedContactOfficialMessage();

    expect(CrmOperation::query()->where('contact_id', $scenario['contact']->getKey())->count())->toBe(0);
});

it('does not count answered messages from WhatsApp test channels toward the threshold', function () {
    $scenario = attendedContactCrmScenario(enabled: true, minimumMessages: 2, existingMessages: 0);
    $testChannel = Channel::factory()->for($scenario['team'])->create([
        'type' => ChannelType::WhatsAppTest,
    ]);
    Message::factory()->for($scenario['contact'])->for($testChannel)->incoming()->responded()->create();
    WhatsAppAgent::fake(['Resposta oficial do atendimento.']);
    fakeOfficialWhatsAppSend();

    postAttendedContactOfficialMessage();

    expect(CrmOperation::query()->where('contact_id', $scenario['contact']->getKey())->count())->toBe(0);
});

it('does not register responses from the WhatsApp test channel', function () {
    config(['services.evolution.test_allowed_phones' => ['5511999999999']]);
    $team = Team::factory()->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->create([
        'type' => ChannelType::WhatsAppTest,
        'agent_id' => $agent->getKey(),
    ]);
    WhatsAppTestConnection::factory()->for($channel)->create([
        'service_url' => 'https://evolution.test',
        'instance_name' => 'local-development',
    ]);
    Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'crm_settings' => [
            'register_attended_contacts' => true,
            'minimum_attended_contact_messages' => 1,
        ],
    ]);
    WhatsAppAgent::fake(['Resposta de teste.']);
    Http::preventStrayRequests();
    Http::fake([
        'https://evolution.test/*' => Http::response([
            'data' => ['Info' => ['ID' => 'attended-contact-test-outgoing']],
        ]),
    ]);

    $payload = json_decode(
        file_get_contents(base_path('tests/Fixtures/whatsapp/testes/message-text.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    expect(CrmOperation::query()->count())->toBe(0)
        ->and(CrmContactLink::query()->count())->toBe(0);
});

it('does not register playground responses in the CRM', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'crm_metadata_loaded_at' => now(),
        'crm_settings' => [
            'register_attended_contacts' => true,
            'minimum_attended_contact_messages' => 1,
        ],
    ]);
    PlaygroundAgent::fake(['Resposta de teste.']);

    $this->actingAs($user)->postJson(route('agents.playground.stream'), [
        'name' => 'Lia',
        'objective' => AgentObjective::Sales->value,
        'tone' => AgentTone::Friendly->value,
        'uses_emojis' => false,
        'response_length' => AgentResponseLength::Short->value,
        'welcome_message' => 'Olá! Como posso ajudar?',
        'instructions' => [],
        'rules' => [],
        'collection_ids' => [],
        'schedulable_calendar_ids' => [],
        'history' => [],
        'message' => 'Quero saber mais.',
    ])->assertOk();

    expect(CrmOperation::query()->count())->toBe(0)
        ->and(CrmContactLink::query()->count())->toBe(0);
});

it('shows defaults and lets only an owner or admin configure automatic registration', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $integration = Integration::factory()->for($team)->create([
        'type' => 'crm',
        'provider' => 'rd_station',
        'status' => 'connected',
        'crm_metadata_loaded_at' => now(),
        'crm_settings' => [],
    ]);

    $this->actingAs($user)
        ->get(route('integrations.show', 'crm'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('registerAttendedContacts', false)
            ->where('minimumAttendedContactMessages', 2),
        );

    $this->actingAs($user)
        ->put(route('integrations.crm.attended-contact-registration.update'), [
            'enabled' => true,
            'minimum_messages' => 4,
        ])
        ->assertRedirect(route('integrations.show', 'crm'));

    expect($integration->fresh()->crm_settings)->toMatchArray([
        'register_attended_contacts' => true,
        'minimum_attended_contact_messages' => 4,
    ]);

    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    $this->actingAs($member)
        ->put(route('integrations.crm.attended-contact-registration.update'), [
            'enabled' => false,
            'minimum_messages' => 2,
        ])
        ->assertForbidden();
});
