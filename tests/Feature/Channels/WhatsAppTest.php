<?php

use App\Enums\ChannelStatus;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function whatsappDetails(array $overrides = []): array
{
    return array_merge([
        'waba_id' => '102030405060708',
        'phone_number_id' => '15551234567890',
        'access_token' => 'EAAGold-token',
        'app_secret' => 'old-app-secret',
        'agent_id' => null,
    ], $overrides);
}

function createWhatsAppChannel(User $user, array $channelAttributes = [], array $accountAttributes = [], array $phoneAttributes = []): array
{
    $channel = Channel::factory()->for($user->company())->create($channelAttributes);
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create($phoneAttributes);
    $account = WhatsAppAccount::factory()
        ->for($phoneNumber, 'phoneNumber')
        ->create($accountAttributes);

    return [$channel, $phoneNumber, $account];
}

it('redirects guests to login when they visit channels', function () {
    $response = $this->get(route('channels.index'));

    $response->assertRedirect(route('login'));
});

it('redirects guests to login when they visit WhatsApp configuration', function () {
    $response = $this->get(route('channels.whatsapp.edit'));

    $response->assertRedirect(route('login'));
});

it('renders the WhatsApp channel without returning its secrets', function () {
    $user = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppChannel($user, ['status' => ChannelStatus::Connected], [], [
        'phone_number_id' => '15551234567890',
    ]);

    $channel->whatsappPhoneNumber->whatsappAccount->update([
        'access_token' => 'token-that-must-stay-private',
        'app_secret' => 'secret-that-must-stay-private',
    ]);

    $response = $this->actingAs($user)->get(route('channels.whatsapp.edit'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channels/whatsapp')
        ->where('whatsapp.id', $channel->id)
        ->where('whatsapp.status', 'connected')
        ->where('whatsapp.accessTokenSet', true)
        ->where('whatsapp.accessTokenMasked', '••••••••')
        ->where('whatsapp.appSecretSet', true)
        ->where('whatsapp.appSecretMasked', '••••••••')
        ->missing('whatsapp.access_token')
        ->missing('whatsapp.app_secret')
        ->where('permissions.canManageChannels', true),
    );
});

it('allows an owner to configure one WhatsApp channel with its account and number', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.store'),
        whatsappDetails(),
    );

    $response->assertRedirect(route('channels.whatsapp.edit'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'WhatsApp configurado com sucesso.',
    ]);
    $this->assertDatabaseHas('channels', [
        'team_id' => $team->id,
        'type' => 'whatsapp',
        'status' => 'not_configured',
    ]);
    $this->assertDatabaseHas('whatsapp_phone_numbers', [
        'phone_number_id' => '15551234567890',
    ]);
    $this->assertDatabaseHas('whatsapp_accounts', [
        'waba_id' => '102030405060708',
        'method' => 'manual',
    ]);

    $channel = Channel::query()->whereBelongsTo($team)->firstOrFail();
    expect($channel->whatsappPhoneNumber->whatsappAccount->verify_token)->toHaveLength(64)
        ->and($channel->whatsappPhoneNumber->whatsappAccount->access_token)->toBe('EAAGold-token')
        ->and($channel->whatsappPhoneNumber->whatsappAccount->app_secret)->toBe('old-app-secret');
});

it('allows multiple official WhatsApp channels for the same company', function () {
    $user = User::factory()->withTeam()->create();
    createWhatsAppChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.store'),
        whatsappDetails(),
    );

    $response->assertRedirect(route('channels.whatsapp.edit'));
    $this->assertDatabaseCount('channels', 2);
});

it('preserves account secrets when an owner edits the other WhatsApp fields', function () {
    $user = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppChannel($user, [], [
        'access_token' => 'keep-this-token',
        'app_secret' => 'keep-this-secret',
    ]);

    $response = $this->actingAs($user)->patch(
        route('channels.whatsapp.update', $channel),
        whatsappDetails([
            'waba_id' => 'updated-waba',
            'phone_number_id' => 'updated-phone',
            'access_token' => '',
            'app_secret' => '',
        ]),
    );

    $response->assertRedirect(route('channels.whatsapp.edit'));
    $channel->refresh();
    expect($channel->whatsappPhoneNumber->phone_number_id)->toBe('updated-phone')
        ->and($channel->whatsappPhoneNumber->whatsappAccount->waba_id)->toBe('updated-waba')
        ->and($channel->whatsappPhoneNumber->whatsappAccount->access_token)->toBe('keep-this-token')
        ->and($channel->whatsappPhoneNumber->whatsappAccount->app_secret)->toBe('keep-this-secret');
});

it('allows an admin to link and replace the WhatsApp Agent', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $firstAgent = Agent::factory()->for($team)->create();
    $secondAgent = Agent::factory()->for($team)->create();
    [$channel] = createWhatsAppChannel($admin);

    $firstResponse = $this->actingAs($admin)->patch(
        route('channels.whatsapp.update', $channel),
        whatsappDetails(['agent_id' => $firstAgent->id]),
    );
    $secondResponse = $this->actingAs($admin)->patch(
        route('channels.whatsapp.update', $channel),
        whatsappDetails(['agent_id' => $secondAgent->id]),
    );

    $firstResponse->assertRedirect(route('channels.whatsapp.edit'));
    $secondResponse->assertRedirect(route('channels.whatsapp.edit'));
    expect($channel->fresh()->agent_id)->toBe($secondAgent->id);
});

it('tests a WhatsApp connection and stores the Meta response on the number and account', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    [$channel, $phoneNumber] = createWhatsAppChannel($user, [], [], [
        'phone_number_id' => '15551234567890',
    ]);
    $phoneNumber->whatsappAccount->update(['access_token' => 'valid-token']);
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/15551234567890';
    Http::fake([
        $endpoint.'?fields=display_phone_number%2Cverified_name%2Cquality_rating' => Http::response([
            'display_phone_number' => '+55 11 99999-8888',
            'verified_name' => 'Empresa Teste',
            'quality_rating' => 'GREEN',
        ]),
    ]);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.test', $channel),
    );

    $response->assertRedirect(route('channels.whatsapp.edit'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Conexão testada com sucesso.',
    ]);
    $channel->refresh();
    $phoneNumber->refresh();
    expect($channel->status)->toBe(ChannelStatus::Connected)
        ->and($phoneNumber->display_phone_number)->toBe('+55 11 99999-8888')
        ->and($phoneNumber->verified_name)->toBe('Empresa Teste')
        ->and($phoneNumber->quality_rating)->toBe('GREEN')
        ->and($phoneNumber->whatsappAccount->last_tested_at)->not->toBeNull();
});

it('translates a Meta connection error and stores the failed channel status', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    [$channel, $phoneNumber] = createWhatsAppChannel($user);
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$phoneNumber->phone_number_id;
    Http::fake([
        $endpoint.'?fields=display_phone_number%2Cverified_name%2Cquality_rating' => Http::response([
            'error' => ['code' => 190, 'type' => 'OAuthException'],
        ], 401),
    ]);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.test', $channel),
    );

    $response->assertRedirect(route('channels.whatsapp.edit'));
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => 'O access token é inválido ou expirou.',
    ]);
    $channel->refresh();
    expect($channel->status)->toBe(ChannelStatus::Error)
        ->and($channel->last_error)->toBe('O access token é inválido ou expirou.');
});

it('allows a member to view the channel but forbids channel mutations', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    [$channel] = createWhatsAppChannel($member);

    $getResponse = $this->actingAs($member)->get(route('channels.whatsapp.edit'));
    $postResponse = $this->actingAs($member)->post(
        route('channels.whatsapp.store'),
        whatsappDetails(),
    );
    $deleteResponse = $this->actingAs($member)->delete(
        route('channels.whatsapp.destroy', $channel),
    );

    $getResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permissions.canManageChannels', false)
        ->where('whatsapp.id', $channel->id)
        ->where('whatsapp.accessTokenSet', false)
        ->where('whatsapp.verifyToken', null)
        ->missing('whatsapp.access_token')
        ->missing('whatsapp.app_secret'),
    );
    $postResponse->assertForbidden();
    $deleteResponse->assertForbidden();
    $this->assertModelExists($channel);
});

it('returns not found when a company accesses another company WhatsApp channel', function () {
    $user = User::factory()->withTeam()->create();
    [$otherChannel] = createWhatsAppChannel(User::factory()->withTeam()->create());

    $response = $this->actingAs($user)->patch(
        route('channels.whatsapp.update', $otherChannel),
        whatsappDetails(),
    );

    $response->assertNotFound();
});

it('unlinks a WhatsApp channel when its Agent is deleted', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();
    [$channel] = createWhatsAppChannel($user, ['agent_id' => $agent->id]);

    $response = $this->actingAs($user)->delete(route('agents.destroy', $agent));

    $response->assertRedirect(route('agents.index'));
    expect($channel->fresh()->agent_id)->toBeNull();
});

it('allows an owner to remove the WhatsApp channel and its specific records', function () {
    $user = User::factory()->withTeam()->create();
    [$channel, $phoneNumber, $account] = createWhatsAppChannel($user);

    $response = $this->actingAs($user)->delete(
        route('channels.whatsapp.destroy', $channel),
    );

    $response->assertRedirect(route('channels.whatsapp.edit'));
    $this->assertModelMissing($channel);
    $this->assertModelMissing($phoneNumber);
    $this->assertModelMissing($account);
});
