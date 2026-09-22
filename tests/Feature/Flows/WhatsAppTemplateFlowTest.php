<?php

use App\Consent\ContactConsentService;
use App\Enums\ChannelType;
use App\Enums\ConsentPurpose;
use App\Enums\FlowCategory;
use App\Enums\FlowExecutionStatus;
use App\Enums\FlowStatus;
use App\Enums\FlowTriggerType;
use App\Flows\FlowEngine;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\FlowVersion;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Http;

function whatsappTemplateFlowGraph(array $nodes, array $edges = []): array
{
    return ['version' => 1, 'nodes' => $nodes, 'edges' => $edges];
}

function createWhatsAppTemplateFlow(User $user, string $purpose): array
{
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);
    $phone = WhatsAppPhoneNumber::factory()->for($channel)->create(['phone_number_id' => uniqid('flow-phone-', true)]);
    $account = WhatsAppAccount::factory()->for($phone, 'phoneNumber')->create();
    $template = WhatsAppTemplate::factory()->for($account, 'account')->create([
        'requested_category' => $purpose,
        'assigned_category' => $purpose,
        'variables' => [['position' => 1, 'component' => 'body']],
    ]);
    $contact = Contact::factory()->for($user->company())->create(['phone' => '55'.random_int(10000000000, 99999999999)]);
    Message::factory()->for($contact)->for($channel)->incoming()->create([
        'content' => 'Olá',
        'created_at' => now()->subHours(25),
    ]);
    $dsl = whatsappTemplateFlowGraph([
        ['id' => 'template', 'type' => 'send_template', 'config' => [
            'template_id' => $template->getKey(),
            'purpose' => $purpose,
            'variables' => [['source' => 'contact_field', 'path' => 'name']],
        ]],
        ['id' => 'end', 'type' => 'end', 'config' => []],
    ], [
        ['source' => 'template', 'target' => 'end'],
    ]);
    $flow = Flow::factory()->for($user->company())->create([
        'channel_id' => $channel->getKey(),
        'status' => FlowStatus::Published,
        'trigger_type' => FlowTriggerType::Webhook,
        'trigger_event' => 'template.send',
        'draft_dsl' => $dsl,
    ]);
    $version = FlowVersion::factory()->for($flow)->create([
        'team_id' => $user->company()->getKey(),
        'dsl' => $dsl,
        'category' => FlowCategory::Reaction,
    ]);

    return [$channel, $contact, $flow, $version, $template];
}

it('requires consent for every template purpose outside the customer care window', function (string $purpose) {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'template-sent']]])]);
    $user = User::factory()->withTeam()->create();
    [$channel, $contact, $flow] = createWhatsAppTemplateFlow($user, $purpose);

    app(FlowEngine::class)->handleWebhook($flow, $channel, ['event' => 'template.send'], $contact);

    $execution = FlowExecution::withoutGlobalScopes()->latest('created_at')->latest('id')->firstOrFail();
    expect($execution->status)->toBe(FlowExecutionStatus::Failed)
        ->and($execution->final_reason)->toBe('consent_required_'.$purpose);
    expect($execution->steps()->firstOrFail()->error)->toContain('consent_required_'.$purpose);
    $this->assertDatabaseMissing('messages', ['contact_id' => $contact->id, 'payload_type' => 'template']);
})->with([
    'marketing',
    'utility',
    'authentication',
]);

it('allows a template after consent is granted and rejects it after that purpose is revoked', function (string $purpose) {
    Http::fake(['*' => Http::response(['messages' => [['id' => 'template-sent']]])]);
    $user = User::factory()->withTeam()->create();
    [$channel, $contact, $flow] = createWhatsAppTemplateFlow($user, $purpose);
    $consents = app(ContactConsentService::class);
    $consents->grant($contact, ConsentPurpose::from($purpose), 'test');

    app(FlowEngine::class)->handleWebhook($flow, $channel, ['event' => 'template.send'], $contact);

    expect(FlowExecution::withoutGlobalScopes()->latest('created_at')->latest('id')->firstOrFail()->status)
        ->toBe(FlowExecutionStatus::Completed);
    $this->assertDatabaseHas('messages', ['contact_id' => $contact->id, 'payload_type' => 'template']);

    [$secondChannel, $secondContact, $secondFlow] = createWhatsAppTemplateFlow($user, $purpose);
    $consents->grant($secondContact, ConsentPurpose::from($purpose), 'test');
    $consents->revoke($secondContact, ConsentPurpose::from($purpose), 'test');
    app(FlowEngine::class)->handleWebhook($secondFlow, $secondChannel, ['event' => 'template.send'], $secondContact);

    expect(FlowExecution::withoutGlobalScopes()->latest('created_at')->latest('id')->firstOrFail()->final_reason)
        ->toBe('consent_required_'.$purpose);
})->with([
    'marketing',
    'utility',
    'authentication',
]);
