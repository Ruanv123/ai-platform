<?php

use App\Enums\ChannelType;
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
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsAppTestConnection;
use Illuminate\Support\Facades\Http;

function flowGraph(array $nodes, array $edges = []): array
{
    return ['version' => 1, 'nodes' => $nodes, 'edges' => $edges];
}

it('publishes an immutable version and derives unpublished changes', function () {
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsApp]);
    $flow = Flow::factory()->for($user->company())->create([
        'channel_id' => $channel->getKey(),
        'draft_dsl' => flowGraph([
            ['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Olá']],
        ]),
    ]);

    $this->actingAs($user)->post(route('flows.publish', $flow))->assertRedirect();

    expect($flow->fresh()->status)->toBe(FlowStatus::Published)
        ->and($flow->versions()->count())->toBe(1)
        ->and($flow->versions()->first()->category)->toBe(FlowCategory::Reaction);

    $version = FlowVersion::withoutGlobalScopes()->firstOrFail();
    expect(fn () => $version->update(['number' => 2]))->toThrow(LogicException::class);

    $this->actingAs($user)->patch(route('flows.update', $flow), [
        'dsl' => flowGraph([
            ['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Mudou']],
        ]),
    ])->assertRedirect();

    expect($flow->fresh()->hasUnpublishedChanges())->toBeTrue();
});

it('runs a published message flow through the channel webhook', function () {
    $team = Team::factory()->create();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    WhatsAppTestConnection::factory()->for($channel)->create([
        'service_url' => 'https://evolution.test',
        'instance_name' => 'flow-test',
    ]);
    $contact = Contact::factory()->for($team)->create(['phone' => '5511999999999']);
    $flow = Flow::factory()->for($team)->create([
        'channel_id' => $channel->getKey(),
        'status' => FlowStatus::Published,
        'draft_dsl' => flowGraph([
            ['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Resposta do Fluxo']],
        ]),
    ]);
    FlowVersion::factory()->for($flow)->create([
        'team_id' => $team->getKey(),
        'dsl' => $flow->draft_dsl,
        'category' => FlowCategory::Reaction,
    ]);
    Http::fake([
        'https://evolution.test/message/markread' => Http::response([]),
        'https://evolution.test/message/presence' => Http::response([]),
        'https://evolution.test/send/text' => Http::response(['data' => ['Info' => ['ID' => 'flow-outgoing']]]),
    ]);

    $message = Message::factory()->for($contact)->for($channel)->incoming()->create([
        'content' => 'Olá',
        'provider_message_id' => 'flow-incoming',
    ]);
    app(FlowEngine::class)->handleMessage($channel, $message);

    expect($message->fresh()->is_responded)->toBeTrue();
    $this->assertDatabaseHas('messages', ['provider_message_id' => 'flow-outgoing', 'content' => 'Resposta do Fluxo']);
    $this->assertDatabaseHas('flow_executions', ['status' => 'completed', 'final_reason' => 'completed']);
});

it('exposes an incoming button payload to message flow variables', function () {
    $team = Team::factory()->create();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsApp]);
    $contact = Contact::factory()->for($team)->create(['phone' => '5511999999999']);
    $dsl = flowGraph([
        ['id' => 'save-button-code', 'type' => 'define_variable', 'config' => [
            'name' => 'clicked_button',
            'value' => '{{ variables.button_payload }}',
        ]],
        ['id' => 'finish', 'type' => 'end', 'config' => []],
    ], [
        ['source' => 'save-button-code', 'target' => 'finish'],
    ]);
    $flow = Flow::factory()->for($team)->create([
        'channel_id' => $channel->getKey(),
        'status' => FlowStatus::Published,
        'draft_dsl' => $dsl,
    ]);
    FlowVersion::factory()->for($flow)->create([
        'team_id' => $team->getKey(),
        'dsl' => $dsl,
        'category' => FlowCategory::Reaction,
    ]);
    $message = Message::factory()->for($contact)->for($channel)->incoming()->create([
        'content' => 'Confirmar consulta',
        'payload_type' => 'text',
        'button_payload' => 'confirm-appointment-v2',
    ]);

    app(FlowEngine::class)->handleMessage($channel, $message);

    $execution = FlowExecution::withoutGlobalScopes()->sole();
    expect($execution->variables['button_payload'])->toBe('confirm-appointment-v2')
        ->and($execution->variables['clicked_button'])->toBe('confirm-appointment-v2');
});

it('starts a published flow from the dedicated webhook trigger', function () {
    $team = Team::factory()->create();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    WhatsAppTestConnection::factory()->for($channel)->create([
        'service_url' => 'https://evolution.test',
        'instance_name' => 'flow-webhook',
    ]);
    $contact = Contact::factory()->for($team)->create(['phone' => '5511999999999']);
    $dsl = flowGraph([
        ['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Olá {{ payload.name }}']],
    ]);
    $flow = Flow::factory()->for($team)->create([
        'channel_id' => $channel->getKey(),
        'status' => FlowStatus::Published,
        'trigger_type' => FlowTriggerType::Webhook,
        'trigger_event' => 'appointment.created',
        'draft_dsl' => $dsl,
    ]);
    FlowVersion::factory()->for($flow)->create(['team_id' => $team->getKey(), 'dsl' => $dsl, 'category' => FlowCategory::Reaction]);
    Http::fake([
        'https://evolution.test/send/text' => Http::response(['data' => ['Info' => ['ID' => 'webhook-outgoing']]]),
    ]);

    $this->postJson(route('webhooks.flows', [$channel->getKey(), 'appointment.created']), [
        'phone' => $contact->phone,
        'name' => 'Ana',
    ])->assertOk()->assertJson(['accepted' => true]);

    $this->assertDatabaseHas('messages', ['provider_message_id' => 'webhook-outgoing', 'content' => 'Olá Ana']);
    $this->assertDatabaseHas('flow_executions', ['status' => 'completed']);
});

it('resumes a conversation flow at a capture node on the next turn', function () {
    $team = Team::factory()->create();
    $channel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    WhatsAppTestConnection::factory()->for($channel)->create([
        'service_url' => 'https://evolution.test',
        'instance_name' => 'flow-capture',
    ]);
    $contact = Contact::factory()->for($team)->create(['phone' => '5511999999999']);
    $dsl = flowGraph([
        ['id' => 'ask', 'type' => 'message', 'config' => ['text' => 'Qual seu nome?']],
        ['id' => 'capture', 'type' => 'capture_response', 'config' => ['variable' => 'name']],
        ['id' => 'done', 'type' => 'message', 'config' => ['text' => 'Obrigado, {{ name }}.']],
    ], [
        ['source' => 'ask', 'target' => 'capture'],
        ['source' => 'capture', 'target' => 'done'],
    ]);
    $flow = Flow::factory()->for($team)->create([
        'channel_id' => $channel->getKey(),
        'status' => FlowStatus::Published,
        'draft_dsl' => $dsl,
    ]);
    FlowVersion::factory()->for($flow)->create(['team_id' => $team->getKey(), 'dsl' => $dsl, 'category' => FlowCategory::Conversation]);
    Http::fake([
        'https://evolution.test/send/text' => Http::sequence()
            ->push(['data' => ['Info' => ['ID' => 'ask-sent']]])
            ->push(['data' => ['Info' => ['ID' => 'done-sent']]]),
    ]);

    $first = Message::factory()->for($contact)->for($channel)->incoming()->create(['content' => 'Oi', 'provider_message_id' => 'capture-first']);
    app(FlowEngine::class)->handleMessage($channel, $first);
    expect(FlowExecution::withoutGlobalScopes()->first()->status)->toBe(FlowExecutionStatus::Waiting);

    $second = Message::factory()->for($contact)->for($channel)->incoming()->create(['content' => 'Ana', 'provider_message_id' => 'capture-second']);
    app(FlowEngine::class)->handleMessage($channel, $second);

    expect(FlowExecution::withoutGlobalScopes()->first()->status)->toBe(FlowExecutionStatus::Completed);
    $this->assertDatabaseHas('messages', ['content' => 'Obrigado, Ana.']);
});

it('rejects a broken graph and prevents a Member from publishing', function () {
    $owner = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($owner->company())->create(['type' => ChannelType::WhatsApp]);
    $flow = Flow::factory()->for($owner->company())->create([
        'channel_id' => $channel->getKey(),
        'draft_dsl' => flowGraph([
            ['id' => 'start', 'type' => 'message', 'config' => []],
            ['id' => 'orphan', 'type' => 'message', 'config' => []],
        ]),
    ]);
    $member = User::factory()->create();
    $flow->team->members()->attach($member, ['role' => 'member']);
    $member->switchTeam($flow->team);

    $this->actingAs($owner)->post(route('flows.publish', $flow))
        ->assertSessionHasErrors('dsl');
    $this->actingAs($member)->post(route('flows.publish', $flow))->assertForbidden();
});
