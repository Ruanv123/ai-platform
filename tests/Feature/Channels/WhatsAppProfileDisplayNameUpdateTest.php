<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

function createWhatsAppProfileNameChannel(User $user, ?string $lastError = 'Erro anterior preservado.'): Channel
{
    $channel = Channel::factory()
        ->for($user->company())
        ->connected()
        ->create([
            'type' => ChannelType::WhatsApp,
            'last_error' => $lastError,
        ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create([
        'phone_number_id' => '15551234567890',
    ]);
    $phoneNumber->whatsappAccount()->create([
        'method' => 'manual',
        'waba_id' => '102030405060708',
        'app_id' => '987654321012345',
        'access_token' => 'profile-access-token',
        'app_secret' => 'profile-app-secret',
        'verify_token' => 'profile-verify-token',
    ]);

    return $channel;
}

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => false]);
    $this->withoutVite();
});

it('sends the display name request to Meta without changing the channel state', function () {
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*/15551234567890' => Http::response(['success' => true]),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppProfileNameChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.profile.display-name', $channel),
        ['name' => 'Aurora Presentes'],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Pedido de alteração do nome enviado para análise da Meta.',
        ]);
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with(
        parse_url($request->url(), PHP_URL_PATH) ?: '',
        '/15551234567890',
    )
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer profile-access-token')
        && $request->data() === ['new_display_name' => 'Aurora Presentes']);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');
});

it('allows an Admin to request a display name change', function () {
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*/15551234567890' => Http::response(['success' => true]),
    ]);
    $owner = User::factory()->withTeam()->create();
    $admin = User::factory()->create();
    $owner->company()->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($owner->company());
    $channel = createWhatsAppProfileNameChannel($admin);

    $response = $this->actingAs($admin)->post(
        route('channels.whatsapp.profile.display-name', $channel),
        ['name' => 'Aurora Presentes'],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']));
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request->data() === ['new_display_name' => 'Aurora Presentes']);
});

it('returns a translated error when Meta rejects a display name request', function () {
    Http::preventStrayRequests();
    Http::fake([
        'graph.facebook.com/*/15551234567890' => Http::response([
            'error' => ['code' => 100, 'type' => 'GraphMethodException'],
        ], 400),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppProfileNameChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.profile.display-name', $channel),
        ['name' => 'Aurora Presentes'],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'O Phone Number ID ou os campos informados são inválidos.',
        ]);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');
});

it('forbids a Member from requesting a display name change', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());
    $channel = createWhatsAppProfileNameChannel($owner);

    $response = $this->actingAs($member)->post(
        route('channels.whatsapp.profile.display-name', $channel),
        ['name' => 'Aurora Presentes'],
    );

    $response->assertForbidden();
    Http::assertNothingSent();
});

it('returns not found when another company requests a display name change', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppProfileNameChannel(User::factory()->withTeam()->create());

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.profile.display-name', $channel),
        ['name' => 'Aurora Presentes'],
    );

    $response->assertNotFound();
    Http::assertNothingSent();
});

it('does not expose a display name request route for WhatsApp test channels', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WhatsAppTest,
        'status' => ChannelStatus::Connected,
    ]);

    $response = $this->actingAs($user)->post(
        '/channels/whatsapp-test/'.$channel->getKey().'/profile/display-name',
        ['name' => 'Aurora Presentes'],
    );

    $response->assertNotFound();
    Http::assertNothingSent();
});

it('requires a display name before sending a request', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppProfileNameChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.profile.display-name', $channel),
        ['name' => ''],
    );

    $response->assertSessionHasErrors(['name' => 'Informe o novo nome de exibição.']);
    Http::assertNothingSent();
});
