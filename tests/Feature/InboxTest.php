<?php

use App\Enums\FlowExecutionStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\FlowVersion;
use App\Models\Message;
use App\Models\User;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function humanWaitingExecution(User $user, ?Contact $contact = null, ?Channel $channel = null): FlowExecution
{
    $team = $user->company();
    $channel ??= Channel::factory()->for($team)->create();
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
