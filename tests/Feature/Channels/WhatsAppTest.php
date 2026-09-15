<?php

use App\Enums\TeamRole;
use App\Enums\WhatsAppConnectionStatus;
use App\Models\Agent;
use App\Models\User;
use App\Models\WhatsAppNumber;
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

it('redirects guests to login when they visit channels', function () {
    $response = $this->get(route('channels.index'));

    $response->assertRedirect(route('login'));
});

it('renders the WhatsApp channel without returning its secrets', function () {
    $user = User::factory()->withTeam()->create();
    $whatsappNumber = WhatsAppNumber::factory()->for($user->company())->create([
        'access_token' => 'token-that-must-stay-private',
        'app_secret' => 'secret-that-must-stay-private',
        'status' => WhatsAppConnectionStatus::Connected,
    ]);

    $response = $this->actingAs($user)->get(route('channels.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('channels/whatsapp')
        ->where('whatsapp.id', $whatsappNumber->id)
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

it('allows an owner to configure one WhatsApp number for the company', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->company();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.store'),
        whatsappDetails(),
    );

    $response->assertRedirect(route('channels.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'WhatsApp configurado com sucesso.',
    ]);
    $this->assertDatabaseHas('whats_app_numbers', [
        'team_id' => $team->id,
        'waba_id' => '102030405060708',
        'phone_number_id' => '15551234567890',
        'method' => 'manual',
        'status' => 'unverified',
    ]);

    $whatsappNumber = WhatsAppNumber::query()->whereBelongsTo($team)->firstOrFail();
    expect($whatsappNumber->verify_token)->toHaveLength(64)
        ->and($whatsappNumber->access_token)->toBe('EAAGold-token')
        ->and($whatsappNumber->app_secret)->toBe('old-app-secret');
});

it('rejects a second WhatsApp number for the same company', function () {
    $user = User::factory()->withTeam()->create();
    WhatsAppNumber::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.store'),
        whatsappDetails(),
    );

    $response->assertSessionHasErrors([
        'waba_id' => 'Sua Empresa já possui um número de WhatsApp configurado.',
    ]);
    $this->assertDatabaseCount('whats_app_numbers', 1);
});

it('preserves secrets when an owner edits the other WhatsApp fields', function () {
    $user = User::factory()->withTeam()->create();
    $whatsappNumber = WhatsAppNumber::factory()->for($user->company())->create([
        'access_token' => 'keep-this-token',
        'app_secret' => 'keep-this-secret',
    ]);

    $response = $this->actingAs($user)->patch(
        route('channels.whatsapp.update', $whatsappNumber),
        whatsappDetails([
            'waba_id' => 'updated-waba',
            'phone_number_id' => 'updated-phone',
            'access_token' => '',
            'app_secret' => '',
        ]),
    );

    $response->assertRedirect(route('channels.index'));
    $whatsappNumber->refresh();
    expect($whatsappNumber->waba_id)->toBe('updated-waba')
        ->and($whatsappNumber->phone_number_id)->toBe('updated-phone')
        ->and($whatsappNumber->access_token)->toBe('keep-this-token')
        ->and($whatsappNumber->app_secret)->toBe('keep-this-secret');
});

it('allows an admin to link and replace the WhatsApp Agent', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    $firstAgent = Agent::factory()->for($team)->create();
    $secondAgent = Agent::factory()->for($team)->create();
    $whatsappNumber = WhatsAppNumber::factory()->for($team)->create();

    $firstResponse = $this->actingAs($admin)->patch(
        route('channels.whatsapp.update', $whatsappNumber),
        whatsappDetails(['agent_id' => $firstAgent->id]),
    );
    $secondResponse = $this->actingAs($admin)->patch(
        route('channels.whatsapp.update', $whatsappNumber),
        whatsappDetails(['agent_id' => $secondAgent->id]),
    );

    $firstResponse->assertRedirect(route('channels.index'));
    $secondResponse->assertRedirect(route('channels.index'));
    expect($whatsappNumber->fresh()->agent_id)->toBe($secondAgent->id);
});

it('tests a WhatsApp connection and stores the Meta response', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $whatsappNumber = WhatsAppNumber::factory()->for($user->company())->create([
        'access_token' => 'valid-token',
        'phone_number_id' => '15551234567890',
    ]);
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/15551234567890';
    Http::fake([
        $endpoint.'?fields=display_phone_number%2Cverified_name%2Cquality_rating' => Http::response([
            'display_phone_number' => '+55 11 99999-8888',
            'verified_name' => 'Empresa Teste',
            'quality_rating' => 'GREEN',
        ]),
    ]);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.test', $whatsappNumber),
    );

    $response->assertRedirect(route('channels.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Conexão testada com sucesso.',
    ]);
    $whatsappNumber->refresh();
    expect($whatsappNumber->status)->toBe(WhatsAppConnectionStatus::Connected)
        ->and($whatsappNumber->display_phone_number)->toBe('+55 11 99999-8888')
        ->and($whatsappNumber->verified_name)->toBe('Empresa Teste')
        ->and($whatsappNumber->quality_rating)->toBe('GREEN');
});

it('translates a Meta connection error and stores the failed status', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $whatsappNumber = WhatsAppNumber::factory()->for($user->company())->create();
    $endpoint = rtrim(config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$whatsappNumber->phone_number_id;
    Http::fake([
        $endpoint.'?fields=display_phone_number%2Cverified_name%2Cquality_rating' => Http::response([
            'error' => ['code' => 190, 'type' => 'OAuthException'],
        ], 401),
    ]);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp.test', $whatsappNumber),
    );

    $response->assertRedirect(route('channels.index'));
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => 'O access token é inválido ou expirou.',
    ]);
    $whatsappNumber->refresh();
    expect($whatsappNumber->status)->toBe(WhatsAppConnectionStatus::Error)
        ->and($whatsappNumber->last_error)->toBe('O access token é inválido ou expirou.');
});

it('allows a member to view the channel but forbids channel mutations', function () {
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    $whatsappNumber = WhatsAppNumber::factory()->for($team)->create();

    $getResponse = $this->actingAs($member)->get(route('channels.index'));
    $postResponse = $this->actingAs($member)->post(
        route('channels.whatsapp.store'),
        whatsappDetails(),
    );
    $deleteResponse = $this->actingAs($member)->delete(
        route('channels.whatsapp.destroy', $whatsappNumber),
    );

    $getResponse->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('permissions.canManageChannels', false)
        ->where('whatsapp.id', $whatsappNumber->id)
        ->where('whatsapp.accessTokenSet', false)
        ->where('whatsapp.verifyToken', null)
        ->missing('whatsapp.access_token')
        ->missing('whatsapp.app_secret'),
    );
    $postResponse->assertForbidden();
    $deleteResponse->assertForbidden();
    $this->assertModelExists($whatsappNumber);
});

it('returns not found when a company accesses another company WhatsApp number', function () {
    $user = User::factory()->withTeam()->create();
    $otherNumber = WhatsAppNumber::factory()->create();

    $response = $this->actingAs($user)->patch(
        route('channels.whatsapp.update', $otherNumber),
        whatsappDetails(),
    );

    $response->assertNotFound();
});

it('unlinks a WhatsApp number when its Agent is deleted', function () {
    $user = User::factory()->withTeam()->create();
    $agent = Agent::factory()->for($user->company())->create();
    $whatsappNumber = WhatsAppNumber::factory()->forAgent($agent)->create();

    $response = $this->actingAs($user)->delete(route('agents.destroy', $agent));

    $response->assertRedirect(route('agents.index'));
    expect($whatsappNumber->fresh()->agent_id)->toBeNull();
});

it('allows an owner to remove the WhatsApp number', function () {
    $user = User::factory()->withTeam()->create();
    $whatsappNumber = WhatsAppNumber::factory()->for($user->company())->create();

    $response = $this->actingAs($user)->delete(
        route('channels.whatsapp.destroy', $whatsappNumber),
    );

    $response->assertRedirect(route('channels.index'));
    $this->assertModelMissing($whatsappNumber);
});
