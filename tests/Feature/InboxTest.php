<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\FlowExecutionStatus;
use App\Enums\MessageDirection;
use App\Events\HumanConversationUpdated;
use App\Events\InboxMessageCreated;
use App\Inbox\AgentConversationService;
use App\Jobs\RespondToWhatsAppMessage;
use App\Jobs\RunFlowTurn;
use App\Models\Agent;
use App\Models\AgentConversationAudit;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\FlowVersion;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppPhoneNumber;
use App\WhatsApp\IncomingMessageResponseDispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function humanWaitingExecution(User $user, ?Contact $contact = null, ?Channel $channel = null): FlowExecution
{
    $team = $user->company();
    $channel ??= Channel::factory()->for($team)->connected()->create();
    $contact ??= Contact::factory()->for($team)->create([
        'human_waiting' => true,
        'human_waiting_at' => now(),
    ]);
    $flow = Flow::factory()->for($team)->create();
    $version = FlowVersion::factory()->for($flow)->create();

    return FlowExecution::factory()
        ->for($version)
        ->for($contact)
        ->create([
            'team_id' => $team->id,
            'channel_id' => $channel->id,
            'status' => FlowExecutionStatus::Paused,
            'waiting_for' => 'human',
            'human_transfer_reason' => 'contact_requested',
            'wake_at' => now()->addDay(),
        ]);
}

/** @return array{contact: int, channel: string} */
function inboxConversationParams(FlowExecution $execution): array
{
    return [
        'contact' => $execution->contact_id,
        'channel' => $execution->channel_id,
    ];
}

it('lists two channel conversations for one contact as separate latest-message rows', function () {
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $firstExecution = humanWaitingExecution($user, $contact);
    $secondExecution = humanWaitingExecution($user, $contact);
    $message = Message::factory()
        ->for($contact)
        ->for($firstExecution->channel)
        ->create([
            'content' => 'Mensagem antiga no primeiro Canal.',
            'created_at' => now()->subMinutes(5),
        ]);
    Message::factory()
        ->for($contact)
        ->for($secondExecution->channel)
        ->create([
            'content' => 'Mensagem recente no segundo Canal.',
            'created_at' => now()->subMinute(),
        ]);

    $response = $this->actingAs($user)->get(route('inbox.index'));

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('inbox/index')
        ->where('conversations.total', 2)
        ->where('conversations.data.0.contactId', $contact->id)
        ->where('conversations.data.0.channel.id', $secondExecution->channel_id)
        ->where('conversations.data.0.excerpt', 'Mensagem recente no segundo Canal.')
        ->where('conversations.data.1.contactId', $contact->id)
        ->where('conversations.data.1.channel.id', $firstExecution->channel_id)
        ->where('conversations.data.1.excerpt', $message->content));
});

it('lists message-backed WhatsApp conversations once and opens Agent conversations read-only', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $team = $user->company();
    $contact = Contact::factory()->for($team)->create();
    $officialChannel = Channel::factory()->for($team)->create();
    $testChannel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    $websiteChannel = Channel::factory()->for($team)->create(['type' => ChannelType::WebsiteChat]);
    $emptyChannel = Channel::factory()->for($team)->create();
    $execution = humanWaitingExecution($user, $contact, $testChannel);
    $timestamp = now();
    $agentMessage = Message::factory()
        ->for($contact)
        ->for($officialChannel)
        ->create(['content' => 'Atendimento direto do Agente.', 'created_at' => $timestamp]);
    $flowMessage = Message::factory()
        ->for($contact)
        ->for($testChannel)
        ->create(['content' => 'Transferência de Fluxo.', 'created_at' => $timestamp]);
    Message::factory()
        ->for($contact)
        ->for($websiteChannel)
        ->create(['content' => 'Canal fora do escopo.']);

    $this->actingAs($user)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.total', 2)
            ->where('conversations.data.0.channel.id', $testChannel->id)
            ->where('conversations.data.0.state', 'flow')
            ->where('conversations.data.0.excerpt', $flowMessage->content)
            ->where('conversations.data.1.channel.id', $officialChannel->id)
            ->where('conversations.data.1.state', 'agent')
            ->where('conversations.data.1.excerpt', $agentMessage->content));

    $this->actingAs($user)
        ->get(route('inbox.show', ['contact' => $contact, 'channel' => $officialChannel]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.state', 'agent')
            ->where('selected.execution', null)
            ->where('selected.canWrite', false)
            ->where('selected.messages.data.0.id', $agentMessage->id));

    $this->actingAs($user)
        ->get(route('inbox.show', ['contact' => $contact, 'channel' => $emptyChannel]))
        ->assertNotFound();
});

it('pauses a direct Agent conversation and records an explicit takeover', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $replacement = User::factory()->create();
    $team->members()->attach($replacement, ['role' => 'member']);
    $contact = Contact::factory()->for($team)->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create(['agent_id' => $agent->id]);
    Message::factory()->for($contact)->for($channel)->create(['content' => 'Preciso de ajuda.']);

    $this->actingAs($user)
        ->post(route('inbox.agent.pause', ['contact' => $contact, 'channel' => $channel]))
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    $state = AgentConversationState::withoutGlobalScopes()->firstOrFail();
    expect($state->is_paused)->toBeTrue()
        ->and($state->assigned_user_id)->toBe($user->id)
        ->and($state->pause_duration_minutes)->toBe(30)
        ->and($state->expires_at->equalTo($state->paused_at->addMinutes(30)))->toBeTrue();

    $this->actingAs($user)
        ->get(route('inbox.show', ['contact' => $contact, 'channel' => $channel]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.agentPaused', true)
            ->where('selected.canPause', false)
            ->where('selected.canTakeOver', false)
            ->where('selected.assignee.id', $user->id));

    $this->actingAs($replacement)
        ->post(route('inbox.agent.pause', ['contact' => $contact, 'channel' => $channel]))
        ->assertStatus(409);

    $this->actingAs($replacement)
        ->post(route('inbox.agent.take-over', ['contact' => $contact, 'channel' => $channel]))
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    expect(AgentConversationState::withoutGlobalScopes()->firstOrFail()->assigned_user_id)->toBe($replacement->id)
        ->and(AgentConversationAudit::withoutGlobalScopes()->orderBy('id')->pluck('action')->all())->toBe(['paused', 'taken_over'])
        ->and(AgentConversationAudit::withoutGlobalScopes()->orderBy('id')->pluck('previous_user_id')->all())->toBe([null, $user->id])
        ->and(AgentConversationAudit::withoutGlobalScopes()->orderBy('id')->pluck('new_user_id')->all())->toBe([$user->id, $replacement->id]);
});

it('keeps paused incoming messages pending and serializes a generated response against the pause', function () {
    Queue::fake();
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $contact = Contact::factory()->for($team)->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create(['agent_id' => $agent->id]);
    $message = Message::factory()->for($contact)->for($channel)->create([
        'direction' => MessageDirection::Incoming,
        'is_responded' => false,
        'content' => 'Ainda preciso de ajuda.',
    ]);
    $agentConversations = app(AgentConversationService::class);
    $agentConversations->pauseAndAssign($contact, $channel, $user);

    app(IncomingMessageResponseDispatcher::class)->dispatch($channel, $message);
    Queue::assertNotPushed(RespondToWhatsAppMessage::class);
    Queue::assertNotPushed(RunFlowTurn::class);

    $ranResponse = false;
    expect($agentConversations->runIfUnpaused($channel, $contact, function () use (&$ranResponse): void {
        $ranResponse = true;
    }))->toBeFalse()
        ->and($ranResponse)->toBeFalse()
        ->and($message->fresh()->is_responded)->toBeFalse();
});

it('filters conversations by state, responsible person, channel, and contact search', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $flowContact = Contact::factory()->for($team)->create(['name' => 'Ana Flow']);
    $agentContact = Contact::factory()->for($team)->create(['name' => 'Bruno Agent']);
    $flowChannel = Channel::factory()->for($team)->connected()->create();
    $agentChannel = Channel::factory()->for($team)->connected()->create();
    $flowExecution = humanWaitingExecution($user, $flowContact, $flowChannel);
    $flowExecution->forceFill(['human_assigned_user_id' => $user->id])->save();
    Message::factory()->for($flowContact)->for($flowChannel)->create(['content' => 'Fluxo']);
    Message::factory()->for($agentContact)->for($agentChannel)->create(['content' => 'Agente']);

    $this->actingAs($user)
        ->get(route('inbox.index', [
            'state' => 'agent',
            'search' => 'Bruno',
            'channel' => $agentChannel->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.state', 'agent')
            ->where('conversations.total', 1)
            ->where('conversations.data.0.contactId', $agentContact->id));

    $this->actingAs($user)
        ->get(route('inbox.index', ['state' => 'flow', 'assigned_to' => $user->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.total', 1)
            ->where('conversations.data.0.contactId', $flowContact->id));
});

it('keeps unavailable channel history visible and blocks sending without calling the provider', function () {
    Http::fake();
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->error()->create();
    $execution = humanWaitingExecution($user, $contact, $channel);
    $execution->forceFill(['human_assigned_user_id' => $user->id])->save();
    Message::factory()->for($contact)->for($channel)->create(['content' => 'Histórico continua disponível.']);

    $this->actingAs($user)
        ->get(route('inbox.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.total', 1)
            ->where('conversations.data.0.channel.status', ChannelStatus::Error->value)
            ->where('conversations.data.0.channel.statusLabel', ChannelStatus::Error->label()));

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($execution)))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.channel.available', false)
            ->where('selected.canSend', false));

    $this->actingAs($user)
        ->post(route('inbox.messages.store', inboxConversationParams($execution)), ['content' => 'Resposta bloqueada.'])
        ->assertStatus(409);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'graph.facebook.com'));
});

it('renders only the selected channel messages, history, and Flow execution with pagination', function () {
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $firstExecution = humanWaitingExecution($user, $contact);
    $selectedExecution = humanWaitingExecution($user, $contact);

    $otherChannelMessage = Message::factory()
        ->for($contact)
        ->for($firstExecution->channel)
        ->create(['content' => 'Somente no primeiro Canal.']);
    $selectedMessages = collect(range(1, 101))->map(fn (int $number): Message => Message::factory()
        ->for($contact)
        ->for($selectedExecution->channel)
        ->create(['content' => 'Mensagem '.$number.' no Canal selecionado.']));

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($selectedExecution)))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inbox/index')
            ->where('selected.contact.id', $contact->id)
            ->where('selected.channel.id', $selectedExecution->channel_id)
            ->where('selected.execution.id', $selectedExecution->id)
            ->where('selected.messages.lastPage', 2)
            ->where('selected.history.lastPage', 3)
            ->where('selected.messages.data.0.id', $selectedMessages->get(1)->id)
            ->where('selected.history.data.0.id', $selectedMessages->last()->id)
            ->where('selected.messages.data', fn ($messages): bool => $messages->every(
                fn (array $message): bool => $message['id'] !== $otherChannelMessage->id,
            ))
            ->where('selected.history.data', fn ($messages): bool => $messages->every(
                fn (array $message): bool => $message['id'] !== $otherChannelMessage->id,
            )));
});

it('authorizes the inbox broadcast channel only for members of the Empresa', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    require base_path('routes/channels.php');

    $user = User::factory()->withTeam()->create();
    $outsider = User::factory()->withTeam()->create();
    $channel = 'private-team.'.$user->company()->id.'.inbox';
    $this->actingAs($user)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => $channel,
        ])
        ->assertOk();

    $this->actingAs($outsider)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => $channel,
        ])
        ->assertForbidden();
});

it('broadcasts conversation pair identifiers without message content or media details', function () {
    $messageEvent = new InboxMessageCreated(12, 34, 'channel-56', 78);
    $stateEvent = new HumanConversationUpdated(12, 34, 'channel-56', 'assigned');

    expect($messageEvent->broadcastOn()[0]->name)->toBe('private-team.12.inbox')
        ->and($messageEvent->broadcastWith())->toBe([
            'teamId' => 12,
            'contactId' => 34,
            'channelId' => 'channel-56',
            'messageId' => 78,
        ])
        ->and($stateEvent->broadcastWith())->toBe([
            'teamId' => 12,
            'contactId' => 34,
            'channelId' => 'channel-56',
            'action' => 'assigned',
        ]);
});

it('does not reveal a transferred conversation from another Empresa', function () {
    $user = User::factory()->withTeam()->create();
    $otherUser = User::factory()->withTeam()->create();
    $execution = humanWaitingExecution($otherUser);

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($execution)))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('inbox.take', inboxConversationParams($execution)))
        ->assertNotFound();
});

it('returns 404 for a legacy contact-only conversation URL', function () {
    $user = User::factory()->withTeam()->create();
    $execution = humanWaitingExecution($user);

    $this->actingAs($user)
        ->get('/atendimento/'.$execution->contact_id)
        ->assertNotFound();
});

it('allows one person to assume a conversation and rejects a competing assignee', function () {
    $user = User::factory()->withTeam()->create();
    $otherUser = User::factory()->create();
    $user->company()->members()->attach($otherUser, ['role' => 'member']);
    $contact = Contact::factory()->for($user->company())->create();
    $otherChannelExecution = humanWaitingExecution($user, $contact);
    $execution = humanWaitingExecution($user, $contact);

    $this->actingAs($user)
        ->post(route('inbox.take', inboxConversationParams($execution)))
        ->assertRedirect(route('inbox.show', inboxConversationParams($execution)));

    $this->actingAs($otherUser)
        ->post(route('inbox.take', inboxConversationParams($execution)))
        ->assertStatus(409);

    expect($execution->fresh()->human_assigned_user_id)->toBe($user->id)
        ->and($otherChannelExecution->fresh()->human_assigned_user_id)->toBeNull();
});

it('sends a human message through the conversation channel and records its sender', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'human-message-1']]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    humanWaitingExecution($user, $contact);
    $execution = humanWaitingExecution($user, $contact);
    WhatsAppPhoneNumber::factory()->for($execution->channel)->withAccount()->create();
    Message::factory()->for($execution->contact)->for($execution->channel)->create([
        'direction' => MessageDirection::Incoming,
        'created_at' => now(),
    ]);

    $this->actingAs($user)->post(route('inbox.take', inboxConversationParams($execution)));
    $this->actingAs($user)
        ->post(route('inbox.messages.store', inboxConversationParams($execution)), ['content' => 'Vou ajudar você.'])
        ->assertRedirect(route('inbox.show', inboxConversationParams($execution)));

    Http::assertSent(fn (Request $request): bool => $request->data()['text']['body'] === 'Vou ajudar você.');
    $this->assertDatabaseHas('messages', [
        'contact_id' => $execution->contact_id,
        'channel_id' => $execution->channel_id,
        'content' => 'Vou ajudar você.',
        'sent_by_user_id' => $user->id,
    ]);
});

it('marks an expired execution as failed when a person returns it to the bot', function () {
    $user = User::factory()->withTeam()->create();
    $execution = humanWaitingExecution($user);
    $execution->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->actingAs($user)
        ->post(route('inbox.return', inboxConversationParams($execution)))
        ->assertRedirect(route('inbox.index'));

    expect($execution->fresh()->status)->toBe(FlowExecutionStatus::Failed)
        ->and($execution->fresh()->final_reason)->toBe('execution_ttl_exceeded')
        ->and($execution->contact->fresh()->human_waiting)->toBeFalse();
});

it('returns only the selected channel execution to the flow', function () {
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $otherChannelExecution = humanWaitingExecution($user, $contact);
    $execution = humanWaitingExecution($user, $contact);

    $this->actingAs($user)
        ->post(route('inbox.return', inboxConversationParams($execution)))
        ->assertRedirect(route('inbox.index'));

    expect($execution->fresh()->status)->toBe(FlowExecutionStatus::Completed)
        ->and($otherChannelExecution->fresh()->status)->toBe(FlowExecutionStatus::Paused)
        ->and($execution->contact->fresh()->human_waiting)->toBeFalse();
});

it('returns an unattended conversation to the flow after its wake time', function () {
    $user = User::factory()->withTeam()->create();
    $execution = humanWaitingExecution($user);
    $execution->forceFill([
        'wake_at' => now()->subMinute(),
        'expires_at' => now()->addDay(),
    ])->save();

    $this->artisan('flows:reconcile')
        ->assertSuccessful();

    expect($execution->fresh()->status)->toBe(FlowExecutionStatus::Completed)
        ->and($execution->fresh()->waiting_for)->toBeNull()
        ->and($execution->contact->fresh()->human_waiting)->toBeFalse();
});

it('releases an assignment that has been inactive longer than the configured timeout', function () {
    config(['flows.human_assignment_idle_seconds' => 60]);
    $user = User::factory()->withTeam()->create();
    $execution = humanWaitingExecution($user);
    $execution->forceFill([
        'human_assigned_user_id' => $user->id,
        'human_assigned_at' => now()->subMinutes(5),
        'human_last_activity_at' => now()->subMinutes(5),
    ])->save();

    $this->artisan('inbox:release-stale')
        ->assertSuccessful();

    expect($execution->fresh()->human_assigned_user_id)->toBeNull();
});

it('reopens the customer-care window when a new contact message arrives', function () {
    $user = User::factory()->withTeam()->create();
    $execution = humanWaitingExecution($user);
    Message::factory()->for($execution->contact)->for($execution->channel)->create([
        'direction' => MessageDirection::Incoming,
        'created_at' => now()->subDay()->subMinute(),
    ]);

    $closed = $this->actingAs($user)->get(route('inbox.show', inboxConversationParams($execution)));
    $closed->assertInertia(fn ($page) => $page->where('selected.window.open', false));

    Message::factory()->for($execution->contact)->for($execution->channel)->create([
        'direction' => MessageDirection::Incoming,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($execution)))
        ->assertInertia(fn ($page) => $page->where('selected.window.open', true));
});

it('allows a Template after the WhatsApp customer-care window', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'human-template-1']]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $execution = humanWaitingExecution($user);
    WhatsAppPhoneNumber::factory()->for($execution->channel)->withAccount()->create();
    Message::factory()->for($execution->contact)->for($execution->channel)->create([
        'direction' => MessageDirection::Incoming,
        'created_at' => now()->subDay()->subMinute(),
    ]);

    $this->actingAs($user)->post(route('inbox.take', inboxConversationParams($execution)));
    $this->actingAs($user)
        ->post(route('inbox.messages.store', inboxConversationParams($execution)), [
            'type' => 'template',
            'template' => 'reengagement',
            'parameters' => ['Maria'],
        ])
        ->assertRedirect(route('inbox.show', inboxConversationParams($execution)));

    Http::assertSent(fn (Request $request): bool => $request->data()['type'] === 'template'
        && $request->data()['template']['name'] === 'reengagement');
});

it('blocks a free-form message after the WhatsApp customer-care window', function () {
    Http::fake();
    $user = User::factory()->withTeam()->create();
    $execution = humanWaitingExecution($user);
    WhatsAppPhoneNumber::factory()->for($execution->channel)->withAccount()->create();
    Message::factory()->for($execution->contact)->for($execution->channel)->create([
        'direction' => MessageDirection::Incoming,
        'created_at' => now()->subDay()->subMinute(),
    ]);

    $this->actingAs($user)->post(route('inbox.take', inboxConversationParams($execution)));
    $this->actingAs($user)
        ->post(route('inbox.messages.store', inboxConversationParams($execution)), ['content' => 'Ainda posso responder?'])
        ->assertStatus(409);

    Http::assertNothingSent();
});
