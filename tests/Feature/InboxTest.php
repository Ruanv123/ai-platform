<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\MessageDirection;
use App\Events\HumanConversationUpdated;
use App\Events\InboxMessageCreated;
use App\Inbox\AgentConversationService;
use App\Jobs\RespondToWhatsAppMessage;
use App\Models\Agent;
use App\Models\AgentConversationAudit;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ConversationRead;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppPhoneNumber;
use App\WhatsApp\IncomingMessageResponseDispatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('x', 32))]);
    $this->withoutVite();
});

function pausedAgentConversation(
    User $user,
    ?Contact $contact = null,
    ?Channel $channel = null,
    ChannelType $channelType = ChannelType::WhatsApp,
): AgentConversationState {
    $team = $user->company();
    if ($channel === null) {
        $agent = Agent::factory()->for($team)->create();
        $channel = Channel::factory()->for($team)->connected()->create([
            'type' => $channelType,
            'agent_id' => $agent->getKey(),
        ]);
    } elseif ($channel->agent_id === null) {
        $agent = Agent::factory()->for($team)->create();
        $channel->forceFill(['agent_id' => $agent->getKey()])->save();
    }

    $contact ??= Contact::factory()->for($team)->create();

    return AgentConversationState::factory()
        ->for($team)
        ->for($contact)
        ->for($channel)
        ->create([
            'team_id' => $team->getKey(),
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'is_paused' => true,
            'paused_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'pause_duration_minutes' => 30,
            'last_activity_at' => now(),
        ]);
}

/** @return array{contact: int, channel: string} */
function inboxConversationParams(AgentConversationState $execution): array
{
    return [
        'contact' => $execution->contact_id,
        'channel' => $execution->channel_id,
    ];
}

it('lists two channel conversations for one contact as separate latest-message rows', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $contact = Contact::factory()->for($user->company())->create();
    $firstExecution = pausedAgentConversation($user, $contact);
    $secondExecution = pausedAgentConversation($user, $contact, channelType: ChannelType::WhatsAppTest);
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

it('lists message-backed WhatsApp conversations and opens active Agent conversations read-only', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $team = $user->company();
    $contact = Contact::factory()->for($team)->create();
    $officialChannel = Channel::factory()->for($team)->create();
    $testChannel = Channel::factory()->for($team)->create(['type' => ChannelType::WhatsAppTest]);
    $websiteChannel = Channel::factory()->for($team)->create(['type' => ChannelType::WebsiteChat]);
    $emptyChannel = Channel::factory()->for($team)->create(['type' => ChannelType::InstagramDirect]);
    $execution = pausedAgentConversation($user, $contact, $testChannel);
    $timestamp = now();
    $agentMessage = Message::factory()
        ->for($contact)
        ->for($officialChannel)
        ->create(['content' => 'Atendimento direto do Agente.', 'created_at' => $timestamp]);
    $pausedMessage = Message::factory()
        ->for($contact)
        ->for($testChannel)
        ->create(['content' => 'Atendimento pausado.', 'created_at' => $timestamp]);
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
            ->where('conversations.data.0.agentPaused', true)
            ->where('conversations.data.0.excerpt', $pausedMessage->content)
            ->where('conversations.data.1.channel.id', $officialChannel->id)
            ->where('conversations.data.1.agentPaused', false)
            ->where('conversations.data.1.excerpt', $agentMessage->content));

    $this->actingAs($user)
        ->get(route('inbox.show', ['contact' => $contact, 'channel' => $officialChannel]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.agentPaused', false)
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

    $this->actingAs($user)
        ->get(route('inbox.index', ['state' => 'paused', 'assigned_to' => $user->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.total', 1)
            ->where('conversations.data.0.agentPaused', true));

    $this->actingAs($replacement)
        ->get(route('inbox.show', ['contact' => $contact, 'channel' => $channel]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('selected.canTakeOver', true));

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
    $agentConversations = app(AgentConversationService::class);
    $state = $agentConversations->pauseAndAssign($contact, $channel, $user);
    $team->update(['agent_pause_duration_minutes' => 15]);
    $this->travel(5)->minutes();
    $message = Message::factory()->for($contact)->for($channel)->create([
        'direction' => MessageDirection::Incoming,
        'is_responded' => false,
        'content' => 'Ainda preciso de ajuda.',
    ]);

    app(IncomingMessageResponseDispatcher::class)->dispatch($channel, $message);
    Queue::assertNotPushed(RespondToWhatsAppMessage::class);

    $state->refresh();
    expect($state->last_activity_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($state->expires_at->toDateTimeString())->toBe(now()->addMinutes(30)->toDateTimeString());

    $ranResponse = false;
    expect($agentConversations->runIfUnpaused($channel, $contact, function () use (&$ranResponse): void {
        $ranResponse = true;
    }))->toBeFalse()
        ->and($ranResponse)->toBeFalse()
        ->and($message->fresh()->is_responded)->toBeFalse();
});

it('filters conversations by state, responsible person, channel, and contact search', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $team = $user->company();
    $pausedContact = Contact::factory()->for($team)->create(['name' => 'Ana Pausada']);
    $agentContact = Contact::factory()->for($team)->create(['name' => 'Bruno Agent']);
    $pausedChannel = Channel::factory()->for($team)->connected()->create();
    $agentChannel = Channel::factory()->for($team)->connected()->create(['type' => ChannelType::WhatsAppTest]);
    $pausedState = pausedAgentConversation($user, $pausedContact, $pausedChannel);
    $pausedState->forceFill(['assigned_user_id' => $user->id])->save();
    Message::factory()->for($pausedContact)->for($pausedChannel)->create(['content' => 'Pausada']);
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
        ->get(route('inbox.index', ['state' => 'paused', 'assigned_to' => $user->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.total', 1)
            ->where('conversations.data.0.contactId', $pausedContact->id));
});

it('keeps unavailable channel history visible and blocks sending without calling the provider', function () {
    Http::fake();
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->error()->create();
    $execution = pausedAgentConversation($user, $contact, $channel);
    $execution->forceFill(['assigned_user_id' => $user->id])->save();
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

it('renders only the selected channel messages and history with pagination', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $contact = Contact::factory()->for($user->company())->create();
    $firstExecution = pausedAgentConversation($user, $contact);
    $selectedExecution = pausedAgentConversation($user, $contact, channelType: ChannelType::WhatsAppTest);

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

it('renders the current reaction on the selected conversation message', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $contact = Contact::factory()->for($user->company())->create();
    $execution = pausedAgentConversation($user, $contact, channelType: ChannelType::WhatsAppTest);
    $message = Message::factory()
        ->for($contact)
        ->for($execution->channel)
        ->create([
            'reaction_emoji' => '❤️',
            'reacted_at' => now(),
        ]);

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($execution)))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.messages.data.0.id', $message->id)
            ->where('selected.messages.data.0.reactionEmoji', '❤️')
            ->where('selected.messages.data.0.reactedAt', $message->reacted_at->toIso8601String()));
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

it('does not reveal a paused Agent conversation from another Empresa', function () {
    $user = User::factory()->withTeam()->create();
    $otherUser = User::factory()->withTeam()->create();
    $execution = pausedAgentConversation($otherUser);

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($execution)))
        ->assertNotFound();

    $this->actingAs($user)
        ->post(route('inbox.agent.take-over', inboxConversationParams($execution)))
        ->assertNotFound();
});

it('returns 404 for a legacy contact-only conversation URL', function () {
    $user = User::factory()->withTeam()->create();
    $execution = pausedAgentConversation($user);

    $this->actingAs($user)
        ->get('/atendimento/'.$execution->contact_id)
        ->assertNotFound();
});

it('allows one person to assume a conversation and rejects a competing assignee', function () {
    $user = User::factory()->withTeam()->create();
    $otherUser = User::factory()->create();
    $user->company()->members()->attach($otherUser, ['role' => 'member']);
    $contact = Contact::factory()->for($user->company())->create();
    $otherChannelExecution = pausedAgentConversation($user, $contact, channelType: ChannelType::WebsiteChat);
    $execution = pausedAgentConversation($user, $contact);

    $this->actingAs($user)
        ->post(route('inbox.agent.take-over', inboxConversationParams($execution)))
        ->assertRedirect(route('inbox.show', inboxConversationParams($execution)));

    $this->actingAs($otherUser)
        ->post(route('inbox.agent.pause', inboxConversationParams($execution)))
        ->assertStatus(409);

    expect($execution->fresh()->assigned_user_id)->toBe($user->id)
        ->and($otherChannelExecution->fresh()->assigned_user_id)->toBeNull();
});

it('sends a human message through the conversation channel and records its sender', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'human-message-1']]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    pausedAgentConversation($user, $contact, channelType: ChannelType::WebsiteChat);
    $execution = pausedAgentConversation($user, $contact);
    WhatsAppPhoneNumber::factory()->for($execution->channel)->create();
    Message::factory()->for($execution->contact)->for($execution->channel)->create([
        'direction' => MessageDirection::Incoming,
        'created_at' => now(),
    ]);

    $this->actingAs($user)->post(route('inbox.agent.take-over', inboxConversationParams($execution)));
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

it('pauses and assigns a direct Agent before sending the first human message', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'direct-human-message-1']]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $agent = Agent::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->connected()->create(['agent_id' => $agent->id]);
    WhatsAppPhoneNumber::factory()->for($channel)->create();
    $incoming = Message::factory()->for($contact)->for($channel)->incoming()->create([
        'content' => 'Quero falar com alguém.',
    ]);

    $this->actingAs($user)
        ->post(route('inbox.messages.store', ['contact' => $contact, 'channel' => $channel]), [
            'content' => 'Vou ajudar você.',
        ])
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    $this->assertDatabaseHas('messages', [
        'contact_id' => $contact->id,
        'channel_id' => $channel->id,
        'content' => 'Vou ajudar você.',
        'sent_by_user_id' => $user->id,
        'delivery_status' => 'sent',
    ]);
    expect(AgentConversationState::withoutGlobalScopes()->firstOrFail()->is_paused)->toBeTrue()
        ->and(AgentConversationState::withoutGlobalScopes()->firstOrFail()->assigned_user_id)->toBe($user->id)
        ->and($incoming->fresh()->is_responded)->toBeTrue();
});

it('renews the configured pause snapshot after a successful human reply', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'direct-human-renewed-1']]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $contact = Contact::factory()->for($team)->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create(['agent_id' => $agent->id]);
    WhatsAppPhoneNumber::factory()->for($channel)->create();
    Message::factory()->for($contact)->for($channel)->incoming()->create(['content' => 'Preciso de ajuda.']);
    $state = app(AgentConversationService::class)->pauseAndAssign($contact, $channel, $user);
    $team->update(['agent_pause_duration_minutes' => 15]);
    $this->travel(5)->minutes();

    $this->actingAs($user)
        ->post(route('inbox.messages.store', ['contact' => $contact, 'channel' => $channel]), [
            'content' => 'Estou cuidando disso.',
        ])
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    $state->refresh();
    expect($state->last_activity_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($state->expires_at->toDateTimeString())->toBe(now()->addMinutes(30)->toDateTimeString());
});

it('keeps the direct Agent paused and shows a failed human message when the provider rejects it', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Indisponível']], 500),
    ]);
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $agent = Agent::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->connected()->create(['agent_id' => $agent->id]);
    WhatsAppPhoneNumber::factory()->for($channel)->create();
    Message::factory()->for($contact)->for($channel)->incoming()->create(['content' => 'Preciso de ajuda.']);

    $this->actingAs($user)
        ->post(route('inbox.messages.store', ['contact' => $contact, 'channel' => $channel]), [
            'content' => 'Vou ajudar você.',
        ])
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    expect(AgentConversationState::withoutGlobalScopes()->firstOrFail()->is_paused)->toBeTrue()
        ->and(AgentConversationState::withoutGlobalScopes()->firstOrFail()->assigned_user_id)->toBe($user->id);
    $this->assertDatabaseHas('messages', [
        'contact_id' => $contact->id,
        'channel_id' => $channel->id,
        'content' => 'Vou ajudar você.',
        'sent_by_user_id' => $user->id,
        'delivery_status' => 'failed',
    ]);
});

it('allows a direct human Template after the WhatsApp care window has expired', function () {
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'direct-human-template-1']]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $agent = Agent::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->connected()->create(['agent_id' => $agent->id]);
    WhatsAppPhoneNumber::factory()->for($channel)->create();
    Message::factory()->for($contact)->for($channel)->incoming()->create([
        'created_at' => now()->subDay()->subMinute(),
    ]);

    $this->actingAs($user)
        ->post(route('inbox.messages.store', ['contact' => $contact, 'channel' => $channel]), [
            'type' => 'template',
            'template' => 'reengagement',
            'parameters' => ['Maria'],
        ])
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    Http::assertSent(fn (Request $request): bool => $request->data()['type'] === 'template'
        && $request->data()['template']['name'] === 'reengagement');
    expect(AgentConversationState::withoutGlobalScopes()->firstOrFail()->is_paused)->toBeTrue();
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'direct-human-template-1',
        'sent_by_user_id' => $user->id,
        'delivery_status' => 'sent',
    ]);
});

it('keeps a direct human text failure visible when the WhatsApp care window is closed', function () {
    Http::fake();
    $user = User::factory()->withTeam()->create();
    $contact = Contact::factory()->for($user->company())->create();
    $agent = Agent::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->connected()->create(['agent_id' => $agent->id]);
    WhatsAppPhoneNumber::factory()->for($channel)->create();
    Message::factory()->for($contact)->for($channel)->incoming()->create([
        'created_at' => now()->subDay()->subMinute(),
    ]);

    $this->actingAs($user)
        ->post(route('inbox.messages.store', ['contact' => $contact, 'channel' => $channel]), [
            'content' => 'Ainda posso responder?',
        ])
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    Http::assertNothingSent();
    expect(AgentConversationState::withoutGlobalScopes()->firstOrFail()->is_paused)->toBeTrue();
    $this->assertDatabaseHas('messages', [
        'content' => 'Ainda posso responder?',
        'delivery_status' => 'failed',
        'sent_by_user_id' => $user->id,
    ]);
});

it('devolves an assigned direct conversation after a successful return response', function () {
    Queue::fake();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'returned-agent-message-1']]]),
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $contact = Contact::factory()->for($team)->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create(['agent_id' => $agent->id]);
    WhatsAppPhoneNumber::factory()->for($channel)->create();
    $incoming = Message::factory()->for($contact)->for($channel)->incoming()->create([
        'content' => 'Qual é o horário de funcionamento?',
    ]);
    $agentConversations = app(AgentConversationService::class);
    $agentConversations->pauseAndAssign($contact, $channel, $user);

    $this->actingAs($user)
        ->post(route('inbox.return', ['contact' => $contact, 'channel' => $channel]))
        ->assertRedirect(route('inbox.show', ['contact' => $contact, 'channel' => $channel]));

    Queue::assertPushed(RespondToWhatsAppMessage::class, fn (RespondToWhatsAppMessage $job): bool => $job->returnFromPause);
    $state = AgentConversationState::withoutGlobalScopes()->firstOrFail();
    expect($state->is_paused)->toBeTrue()
        ->and($state->return_action)->toBe('returned');

    expect($agentConversations->beginReturnAttempt($channel, $contact, $incoming))->toBeTrue()
        ->and($agentConversations->completeReturnResponse($channel, $contact, $incoming, 'Abrimos das 9h às 18h.'))->toBeTrue()
        ->and($incoming->fresh()->is_responded)->toBeTrue()
        ->and(AgentConversationState::withoutGlobalScopes()->firstOrFail()->is_paused)->toBeFalse()
        ->and(AgentConversationState::withoutGlobalScopes()->firstOrFail()->assigned_user_id)->toBeNull();

    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'returned-agent-message-1',
        'content' => 'Abrimos das 9h às 18h.',
        'delivery_status' => 'sent',
    ]);
    expect(AgentConversationAudit::withoutGlobalScopes()->orderBy('id')->pluck('action')->all())->toBe(['paused', 'returned']);
});

it('keeps a failed return paused and does not repeat the same failed response', function () {
    Queue::fake();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Indisponível']], 500),
    ]);
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $contact = Contact::factory()->for($team)->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create(['agent_id' => $agent->id]);
    WhatsAppPhoneNumber::factory()->for($channel)->create();
    $incoming = Message::factory()->for($contact)->for($channel)->incoming()->create(['content' => 'Preciso de ajuda.']);
    $agentConversations = app(AgentConversationService::class);
    $agentConversations->pauseAndAssign($contact, $channel, $user);

    $this->actingAs($user)
        ->post(route('inbox.return', ['contact' => $contact, 'channel' => $channel]));
    $agentConversations->beginReturnAttempt($channel, $contact, $incoming);
    expect($agentConversations->completeReturnResponse($channel, $contact, $incoming, 'Resposta automática.'))->toBeFalse()
        ->and(AgentConversationState::withoutGlobalScopes()->firstOrFail()->is_paused)->toBeTrue()
        ->and(AgentConversationState::withoutGlobalScopes()->firstOrFail()->assigned_user_id)->toBe($user->id)
        ->and($incoming->fresh()->is_responded)->toBeFalse();

    $this->assertDatabaseHas('messages', [
        'content' => 'Resposta automática.',
        'delivery_status' => 'failed',
        'failure_reason' => 'provider_error',
    ]);
    $this->actingAs($user)
        ->post(route('inbox.return', ['contact' => $contact, 'channel' => $channel]));
    Queue::assertPushed(RespondToWhatsAppMessage::class, 1);

    $newIncoming = Message::factory()->for($contact)->for($channel)->incoming()->create([
        'content' => 'Ainda está indisponível?',
    ]);
    app(IncomingMessageResponseDispatcher::class)->dispatch($channel, $newIncoming);
    Queue::assertPushed(RespondToWhatsAppMessage::class, fn (RespondToWhatsAppMessage $job): bool => $job->returnFromPause);

    $this->actingAs($user)
        ->post(route('inbox.return', ['contact' => $contact, 'channel' => $channel]));
    Queue::assertPushed(RespondToWhatsAppMessage::class, 2);
    Http::assertSentCount(1);
});

it('expires paused direct conversations once and records a WhatsApp window failure without calling Meta', function () {
    Queue::fake();
    Http::fake();
    $user = User::factory()->withTeam()->create();
    $team = $user->company();
    $contact = Contact::factory()->for($team)->create();
    $agent = Agent::factory()->for($team)->create();
    $channel = Channel::factory()->for($team)->connected()->create(['agent_id' => $agent->id]);
    WhatsAppPhoneNumber::factory()->for($channel)->create();
    $incoming = Message::factory()->for($contact)->for($channel)->incoming()->create([
        'created_at' => now()->subDay()->subMinute(),
    ]);
    $agentConversations = app(AgentConversationService::class);
    $state = $agentConversations->pauseAndAssign($contact, $channel, $user);
    $state->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('agents:resume-expired')->assertSuccessful();
    $this->artisan('agents:resume-expired')->assertSuccessful();
    Queue::assertPushed(RespondToWhatsAppMessage::class, 1);
    expect(AgentConversationState::withoutGlobalScopes()->firstOrFail()->return_action)->toBe('expired');

    $agentConversations->beginReturnAttempt($channel, $contact, $incoming);
    expect($agentConversations->completeReturnResponse($channel, $contact, $incoming, 'Ainda posso ajudar?'))->toBeFalse()
        ->and(AgentConversationState::withoutGlobalScopes()->firstOrFail()->is_paused)->toBeTrue();
    Http::assertNothingSent();
    $this->assertDatabaseHas('messages', [
        'content' => 'Ainda posso ajudar?',
        'delivery_status' => 'failed',
        'failure_reason' => 'customer_care_window_closed',
    ]);
});

it('opens a visible WhatsApp test conversation from the inbox', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $contact = Contact::factory()->for($user->company())->create();
    $channel = Channel::factory()->for($user->company())->create(['type' => ChannelType::WhatsAppTest]);
    $message = Message::factory()->for($contact)->for($channel)->create(['content' => 'Mensagem de teste.']);

    $this->actingAs($user)
        ->get(route('inbox.show', ['contact' => $contact, 'channel' => $channel]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.channel.id', $channel->id)
            ->where('selected.messages.data.0.id', $message->id));
});

it('reopens the customer-care window when a new contact message arrives', function () {
    $user = User::factory()->withTeam()->create();
    $execution = pausedAgentConversation($user);
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
    $execution = pausedAgentConversation($user);
    WhatsAppPhoneNumber::factory()->for($execution->channel)->create();
    Message::factory()->for($execution->contact)->for($execution->channel)->create([
        'direction' => MessageDirection::Incoming,
        'created_at' => now()->subDay()->subMinute(),
    ]);

    $this->actingAs($user)->post(route('inbox.agent.take-over', inboxConversationParams($execution)));
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

it('records a free-form message as failed after the WhatsApp customer-care window without calling Meta', function () {
    Http::fake();
    $user = User::factory()->withTeam()->create();
    $execution = pausedAgentConversation($user);
    WhatsAppPhoneNumber::factory()->for($execution->channel)->create();
    Message::factory()->for($execution->contact)->for($execution->channel)->create([
        'direction' => MessageDirection::Incoming,
        'created_at' => now()->subDay()->subMinute(),
    ]);

    $this->actingAs($user)->post(route('inbox.agent.take-over', inboxConversationParams($execution)));
    $this->actingAs($user)
        ->post(route('inbox.messages.store', inboxConversationParams($execution)), ['content' => 'Ainda posso responder?'])
        ->assertRedirect(route('inbox.show', inboxConversationParams($execution)));

    Http::assertNothingSent();
    $this->assertDatabaseHas('messages', [
        'content' => 'Ainda posso responder?',
        'delivery_status' => 'failed',
        'sent_by_user_id' => $user->id,
    ]);
});

it('counts unread incoming messages per conversation and clears them when the conversation is opened', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $team = $user->company();
    $execution = pausedAgentConversation($user);
    $otherExecution = pausedAgentConversation($user, channelType: ChannelType::WhatsAppTest);
    $readMessage = Message::factory()->for($execution->contact)->for($execution->channel)->create();
    ConversationRead::factory()->create([
        'team_id' => $team->id,
        'contact_id' => $execution->contact_id,
        'channel_id' => $execution->channel_id,
        'last_read_message_id' => $readMessage->id,
    ]);
    Message::factory()->count(2)->for($execution->contact)->for($execution->channel)->create();
    Message::factory()->outgoing()->for($execution->contact)->for($execution->channel)->create();
    Message::factory()->for($otherExecution->contact)->for($otherExecution->channel)->create();

    $this->actingAs($user)
        ->get(route('inbox.index', ['queue' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.data', fn ($conversations): bool => $conversations->pluck('unreadCount', 'contactId')->all() == [
                $execution->contact_id => 2,
                $otherExecution->contact_id => 1,
            ]));

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($execution)))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversations.data', fn ($conversations): bool => $conversations->pluck('unreadCount', 'contactId')->all() == [
                $execution->contact_id => 0,
                $otherExecution->contact_id => 1,
            ]));
});

it('flags a message that arrives after the conversation was opened as unread until it is opened again', function () {
    $user = User::factory()->withTeam()->create(['is_platform_admin' => true]);
    $execution = pausedAgentConversation($user);
    Message::factory()->for($execution->contact)->for($execution->channel)->create();

    $this->actingAs($user)->get(route('inbox.show', inboxConversationParams($execution)))->assertOk();
    Message::factory()->for($execution->contact)->for($execution->channel)->create();

    $this->actingAs($user)
        ->get(route('inbox.index', ['queue' => 'all']))
        ->assertInertia(fn ($page) => $page->where('conversations.data.0.unreadCount', 1));

    $this->actingAs($user)
        ->get(route('inbox.show', inboxConversationParams($execution)))
        ->assertInertia(fn ($page) => $page->where('conversations.data.0.unreadCount', 0));
});
