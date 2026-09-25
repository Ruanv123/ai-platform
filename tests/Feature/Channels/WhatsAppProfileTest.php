<?php

use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function createWhatsAppProfileChannel(User $user, ChannelStatus $status = ChannelStatus::Connected, ?string $lastError = null): array
{
    $channel = Channel::factory()->for($user->company())->create([
        'status' => $status,
        'last_error' => $lastError,
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create([
        'phone_number_id' => '15551234567890',
    ]);
    $account = $phoneNumber->whatsappAccount()->create([
        'method' => 'manual',
        'waba_id' => '102030405060708',
        'access_token' => 'profile-access-token',
        'app_secret' => 'profile-app-secret',
        'verify_token' => 'profile-verify-token',
    ]);

    return [$channel, $phoneNumber, $account];
}

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => false]);
});

it('defers the live Profile until the Perfil tab is open and reads the supported Meta fields', function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    $user = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfileChannel($user);

    Http::fake(function (ClientRequest $request) {
        if (str_contains($request->url(), '/whatsapp_business_profile')) {
            return Http::response([
                'data' => [[
                    'about' => 'Atendimento todos os dias',
                    'address' => 'Rua das Flores, 10',
                    'description' => 'Loja de presentes',
                    'email' => 'contato@example.test',
                    'profile_picture_url' => 'https://cdn.example.test/profile.jpg',
                    'websites' => ['https://example.test'],
                    'vertical' => 'RETAIL',
                ]],
            ]);
        }

        return Http::response([
            'verified_name' => 'Loja Aurora',
            'name_status' => 'APPROVED',
            'new_display_name' => 'Aurora Presentes',
            'new_name_status' => 'PENDING',
        ]);
    });

    $response = $this->actingAs($user)->get(
        route('channels.whatsapp.edit', ['tab' => 'perfil']),
    );
    $response->assertInertia(fn (Assert $page) => $page
        ->component('channels/whatsapp')
        ->where('activeTab', 'perfil')
        ->where('whatsappProfileCapabilities.canRequestDisplayName', true)
    );
    Http::assertNothingSent();

    $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps(
        'default',
        fn (Assert $deferredPage) => $deferredPage
            ->where('whatsappProfile.state', 'ready')
            ->where('whatsappProfile.data.profilePictureUrl', 'https://cdn.example.test/profile.jpg')
            ->where('whatsappProfile.data.displayName', 'Loja Aurora')
            ->where('whatsappProfile.data.nameStatus', 'APPROVED')
            ->where('whatsappProfile.data.newDisplayName', 'Aurora Presentes')
            ->where('whatsappProfile.data.newNameStatus', 'PENDING')
            ->where('whatsappProfile.data.about', 'Atendimento todos os dias')
            ->where('whatsappProfile.data.description', 'Loja de presentes')
            ->where('whatsappProfile.data.address', 'Rua das Flores, 10')
            ->where('whatsappProfile.data.email', 'contato@example.test')
            ->where('whatsappProfile.data.websites.0', 'https://example.test')
            ->where('whatsappProfile.data.category', 'RETAIL'),
    ));

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/15551234567890/whatsapp_business_profile')
        && $request->method() === 'GET'
        && $request->hasHeader('Authorization', 'Bearer profile-access-token')
        && $request['fields'] === 'about,address,description,email,profile_picture_url,websites,vertical'
    );
    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?: '', '/15551234567890')
        && $request->method() === 'GET'
        && $request['fields'] === 'verified_name,name_status,new_display_name,new_name_status'
    );

    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected);
});

it('does not call Meta for a channel that is not connected', function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    $user = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfileChannel($user, ChannelStatus::Error, 'Credenciais inválidas.');

    $response = $this->actingAs($user)->get(
        route('channels.whatsapp.edit', ['tab' => 'perfil']),
    );

    $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps(
        'default',
        fn (Assert $deferredPage) => $deferredPage->where('whatsappProfile.state', 'not_connected'),
    ));
    Http::assertNothingSent();
    expect($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->toBe('Credenciais inválidas.');
});

it('shows a translated provider error without changing the channel connection state', function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    $user = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfileChannel($user, ChannelStatus::Connected, 'Erro anterior preservado.');
    Http::fake([
        'graph.facebook.com/*/15551234567890/whatsapp_business_profile*' => Http::response([
            'error' => ['code' => 190, 'type' => 'OAuthException'],
        ], 401),
    ]);

    $response = $this->actingAs($user)->get(
        route('channels.whatsapp.edit', ['tab' => 'perfil']),
    );

    $response->assertInertia(fn (Assert $page) => $page->loadDeferredProps(
        'default',
        fn (Assert $deferredPage) => $deferredPage
            ->where('whatsappProfile.state', 'error')
            ->where('whatsappProfile.message', 'O access token é inválido ou expirou.')
            ->hasFlash('toast', [
                'type' => 'error',
                'message' => 'O access token é inválido ou expirou.',
            ]),
    ));
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');
});

it('does not request the live Profile when the Conexão tab is active', function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    $user = User::factory()->withTeam()->create();
    createWhatsAppProfileChannel($user);

    $response = $this->actingAs($user)->get(route('channels.whatsapp.edit'));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('activeTab', 'conexao')
        ->where('whatsappProfile', null)
    );
    Http::assertNothingSent();
});
