<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppTestConnection;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

function createWhatsAppTestProfileTextsChannel(User $user): Channel
{
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WhatsAppTest,
        'status' => ChannelStatus::Connected,
        'last_error' => 'Erro anterior preservado.',
    ]);

    WhatsAppTestConnection::factory()->for($channel)->create([
        'instance_name' => 'canal_'.$user->company()->getKey(),
        'access_token' => 'instance-profile-token',
    ]);

    return $channel;
}

beforeEach(function (): void {
    app()->instance('env', 'local');
    config([
        'inertia.ssr.enabled' => false,
        'services.evolution.base_url' => 'https://evolution.test',
        'services.evolution.api_key' => 'platform-admin-key',
    ]);
    $this->withoutVite();
});

it('sends the updated recado to Evolution Go and preserves the channel state', function () {
    Http::preventStrayRequests();
    Http::fake([
        'evolution.test/user/profileStatus' => Http::response([
            'message' => 'success',
            'data' => ['status' => 'Atendimento de segunda a sexta'],
        ]),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppTestProfileTextsChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.profile.texts', $channel),
        ['about' => 'Atendimento de segunda a sexta'],
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit', ['tab' => 'perfil']))
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'message' => 'Perfil do WhatsApp atualizado com sucesso.',
        ]);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://evolution.test/user/profileStatus'
        && $request->method() === 'POST'
        && $request->header('apikey') === ['instance-profile-token']
        && $request->data() === ['status' => 'Atendimento de segunda a sexta']);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');
});

it('shows Evolution Go failures without changing the channel state', function () {
    Http::preventStrayRequests();
    Http::fake([
        'evolution.test/user/profileStatus' => Http::response([
            'error' => 'profile status unavailable',
        ], 500),
    ]);
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppTestProfileTextsChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.profile.texts', $channel),
        ['about' => 'Novo recado'],
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit', ['tab' => 'perfil']))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => 'O Evolution Go recusou a operação: profile status unavailable',
        ]);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');
});

it('allows an Admin to update the test WhatsApp recado', function () {
    Http::preventStrayRequests();
    Http::fake([
        'evolution.test/user/profileStatus' => Http::response(['message' => 'success']),
    ]);
    $owner = User::factory()->withTeam()->create();
    $admin = User::factory()->create();
    $owner->company()->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($owner->company());
    $channel = createWhatsAppTestProfileTextsChannel($owner);

    $response = $this->actingAs($admin)->post(
        route('channels.whatsapp-test.profile.texts', $channel),
        ['about' => 'Novo recado pelo Admin'],
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit', ['tab' => 'perfil']));
    Http::assertSent(fn (ClientRequest $request): bool => $request->data() === ['status' => 'Novo recado pelo Admin']);
});

it('forbids a Member from updating the test WhatsApp recado', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());
    $channel = createWhatsAppTestProfileTextsChannel($owner);

    $response = $this->actingAs($member)->post(
        route('channels.whatsapp-test.profile.texts', $channel),
        ['about' => 'Não autorizado'],
    );

    $response->assertForbidden();
    Http::assertNothingSent();
});

it('returns not found when another company updates the test WhatsApp recado', function () {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppTestProfileTextsChannel(User::factory()->withTeam()->create());

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.profile.texts', $channel),
        ['about' => 'Não autorizado'],
    );

    $response->assertNotFound();
    Http::assertNothingSent();
});

it('rejects recados outside the Evolution Go limits', function (string $about, string $message) {
    Http::preventStrayRequests();
    $user = User::factory()->withTeam()->create();
    $channel = createWhatsAppTestProfileTextsChannel($user);

    $response = $this->actingAs($user)->post(
        route('channels.whatsapp-test.profile.texts', $channel),
        ['about' => $about],
    );

    $response->assertSessionHasErrors(['about' => $message]);
    Http::assertNothingSent();
})->with([
    'recado vazio' => ['', 'O recado precisa ter pelo menos 1 caractere.'],
    'recado acima do limite' => [str_repeat('a', 140), 'O recado pode ter no máximo 139 caracteres.'],
]);
