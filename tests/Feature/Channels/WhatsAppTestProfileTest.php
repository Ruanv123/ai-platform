<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppTestConnection;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function createWhatsAppTestProfileChannel(User $user, ChannelStatus $status = ChannelStatus::Connected, ?string $lastError = null): array
{
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WhatsAppTest,
        'status' => $status,
        'last_error' => $lastError,
    ]);
    $connection = WhatsAppTestConnection::factory()->for($channel)->create([
        'instance_name' => 'canal_'.$user->company()->getKey(),
        'access_token' => 'instance-profile-token',
    ]);

    return [$channel, $connection];
}

function configureWhatsAppTestProfileEvolution(): void
{
    config([
        'services.evolution.base_url' => 'https://evolution.test',
        'services.evolution.api_key' => 'platform-admin-key',
    ]);
}

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => false]);
});

it('defers the live test Profile and reads only its supported Evolution Go fields', function () {
    app()->instance('env', 'local');
    Http::preventStrayRequests();
    $this->withoutVite();
    configureWhatsAppTestProfileEvolution();
    $user = User::factory()->withTeam()->create();
    [$channel, $connection] = createWhatsAppTestProfileChannel($user);
    $jid = '15551234567890@s.whatsapp.net';

    Http::fake([
        'evolution.test/instance/all' => Http::response(['data' => [
            [
                'name' => $connection->instance_name,
                'token' => 'other-token',
                'jid' => '15550000000000@s.whatsapp.net',
            ],
            [
                'name' => 'another-instance',
                'token' => 'other-token',
                'jid' => '15550000000001@s.whatsapp.net',
            ],
            [
                'name' => $connection->instance_name,
                'token' => $connection->access_token,
                'jid' => $jid,
            ],
        ]]),
        'evolution.test/instance/status' => Http::response(['data' => [
            'Connected' => true,
            'LoggedIn' => true,
            'Name' => 'Loja Aurora',
        ]]),
        'evolution.test/user/info' => Http::response(['data' => ['Users' => [
            $jid => ['Status' => 'Atendimento de segunda a sexta'],
        ]]]),
        'evolution.test/user/avatar' => Http::response(['data' => [
            'url' => 'https://cdn.example.test/profile.jpg',
        ]]),
    ]);

    $response = $this->actingAs($user)->get(
        route('channels.whatsapp-test.edit', ['tab' => 'perfil']),
    );

    $response->assertInertia(fn (Assert $page) => $page
        ->component('channels/whatsapp-test')
        ->where('activeTab', 'perfil')
        ->where('whatsappProfileCapabilities.readableFields', ['profilePicture', 'displayName', 'about'])
        ->where('whatsappProfileCapabilities.editableFields', ['profilePicture', 'about'])
        ->where('whatsappProfileCapabilities.canRequestDisplayName', false)
    );
    Http::assertNothingSent();

    $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps(
        'default',
        fn (Assert $deferredPage) => $deferredPage
            ->where('whatsappProfile.state', 'ready')
            ->where('whatsappProfile.data.profilePictureUrl', 'https://cdn.example.test/profile.jpg')
            ->where('whatsappProfile.data.displayName', 'Loja Aurora')
            ->where('whatsappProfile.data.about', 'Atendimento de segunda a sexta')
            ->where('whatsappProfile.data.description', null)
            ->where('whatsappProfile.data.address', null)
            ->where('whatsappProfile.data.email', null)
            ->where('whatsappProfile.data.websites', [])
            ->where('whatsappProfile.data.category', null)
            ->missing('whatsappProfile.data.instanceJid')
            ->missing('whatsappTest.accessToken')
            ->missing('whatsappTest.access_token'),
    ));

    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://evolution.test/instance/all'
        && $request->method() === 'GET'
        && $request->header('apikey') === ['platform-admin-key']);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://evolution.test/instance/status'
        && $request->method() === 'GET'
        && $request->header('apikey') === [$connection->access_token]);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://evolution.test/user/info'
        && $request->method() === 'POST'
        && $request->header('apikey') === [$connection->access_token]
        && $request['number'] === [$jid]);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://evolution.test/user/avatar'
        && $request->method() === 'POST'
        && $request->header('apikey') === [$connection->access_token]
        && $request['number'] === $jid
        && $request['preview'] === false);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected);
});

it('returns an empty Profile without calling Evolution Go before the QR code is scanned', function () {
    app()->instance('env', 'local');
    Http::preventStrayRequests();
    $this->withoutVite();
    configureWhatsAppTestProfileEvolution();
    $user = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppTestProfileChannel(
        $user,
        ChannelStatus::NotConfigured,
        'Estado anterior preservado.',
    );

    $response = $this->actingAs($user)->get(
        route('channels.whatsapp-test.edit', ['tab' => 'perfil']),
    );

    $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps(
        'default',
        fn (Assert $deferredPage) => $deferredPage->where('whatsappProfile.state', 'not_connected'),
    ));
    Http::assertNothingSent();
    expect($channel->fresh()->status)->toBe(ChannelStatus::NotConfigured)
        ->and($channel->fresh()->last_error)->toBe('Estado anterior preservado.');
});

it('does not load the test Profile while the Conexão tab is active', function () {
    app()->instance('env', 'local');
    Http::preventStrayRequests();
    $this->withoutVite();
    configureWhatsAppTestProfileEvolution();
    $user = User::factory()->withTeam()->create();
    createWhatsAppTestProfileChannel($user);

    $response = $this->actingAs($user)->get(route('channels.whatsapp-test.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('activeTab', 'conexao')
        ->where('whatsappProfile', null)
    );
    Http::assertNothingSent();
});

it('shows the Evolution Go error without changing the Channel status', function () {
    app()->instance('env', 'local');
    Http::preventStrayRequests();
    $this->withoutVite();
    configureWhatsAppTestProfileEvolution();
    $user = User::factory()->withTeam()->create();
    [$channel, $connection] = createWhatsAppTestProfileChannel(
        $user,
        ChannelStatus::Connected,
        'Erro anterior preservado.',
    );
    Http::fake([
        'evolution.test/instance/status' => Http::response(['data' => [
            'LoggedIn' => true,
            'Name' => 'Loja Aurora',
        ]]),
        'evolution.test/instance/all' => Http::response(['data' => [[
            'name' => $connection->instance_name,
            'token' => $connection->access_token,
            'jid' => '15551234567890@s.whatsapp.net',
        ]]]),
        'evolution.test/user/info' => Http::response(['error' => 'perfil indisponível'], 500),
        'evolution.test/user/avatar' => Http::response(['data' => [
            'url' => 'https://cdn.example.test/profile.jpg',
        ]]),
    ]);

    $response = $this->actingAs($user)->get(
        route('channels.whatsapp-test.edit', ['tab' => 'perfil']),
    );

    $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps(
        'default',
        fn (Assert $deferredPage) => $deferredPage
            ->where('whatsappProfile.state', 'error')
            ->where('whatsappProfile.message', 'O Evolution Go recusou a operação: perfil indisponível')
            ->hasFlash('toast', [
                'type' => 'error',
                'message' => 'O Evolution Go recusou a operação: perfil indisponível',
            ]),
    ));
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');
});

it('keeps the test channel hidden from regular users outside local', function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    $user = User::factory()->withTeam()->create();
    createWhatsAppTestProfileChannel($user);

    $this->actingAs($user)
        ->get(route('channels.whatsapp-test.edit', ['tab' => 'perfil']))
        ->assertNotFound();

    Http::assertNothingSent();
});
