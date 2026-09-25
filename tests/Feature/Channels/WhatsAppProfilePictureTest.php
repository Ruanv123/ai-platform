<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function createWhatsAppProfilePictureChannel(User $user, ?string $appId = '123456789012345', ?string $lastError = null): array
{
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WhatsApp,
        'status' => ChannelStatus::Connected,
        'last_error' => $lastError,
    ]);
    $account = WhatsAppAccount::factory()->for($user->company(), 'team')->create([
        'connection_method' => 'own_app',
        'waba_id' => '102030405060708',
        'app_id' => $appId,
        'access_token' => 'profile-access-token',
        'app_secret' => 'profile-app-secret',
        'verify_token' => 'profile-verify-token',
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->for($account, 'whatsappAccount')->create([
        'phone_number_id' => '15551234567890',
    ]);

    return [$channel, $phoneNumber, $account];
}

function createWhatsAppProfilePng(int $width = 192, int $height = 192): string
{
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data))
        .$type
        .$data
        .pack('N', crc32($type.$data));
    $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
    $row = "\0".str_repeat("\x7f", $width * 3);
    $imageData = gzcompress(str_repeat($row, $height));

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', $header)
        .$chunk('IDAT', $imageData)
        .$chunk('IEND', '');
}

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => false]);
});

it('uploads the Meta photo through a resumable session and shows the new live photo', function () {
    Http::preventStrayRequests();
    $this->withoutVite();
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $admin = User::factory()->create();
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $admin->switchTeam($team);
    [$channel] = createWhatsAppProfilePictureChannel($owner, '123456789012345', 'Existing channel error.');
    $photo = File::createWithContent('profile.png', createWhatsAppProfilePng());
    $graphUrl = rtrim((string) config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version');
    Http::fake(function (ClientRequest $request) use ($graphUrl) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($request->method() === 'POST' && str_ends_with((string) $path, '/uploads')) {
            return Http::response(['id' => 'upload:photo-session']);
        }

        if ($request->method() === 'POST' && str_ends_with((string) $path, '/upload:photo-session')) {
            return Http::response(['h' => 'photo-handle-123']);
        }

        if ($request->method() === 'POST' && str_ends_with((string) $path, '/whatsapp_business_profile')) {
            return Http::response(['success' => true]);
        }

        if ($request->method() === 'GET' && str_ends_with((string) $path, '/whatsapp_business_profile')) {
            return Http::response(['data' => [[
                'profile_picture_url' => 'https://cdn.example.test/new-profile.jpg',
            ]]]);
        }

        if ($request->method() === 'GET' && str_ends_with((string) $path, '/15551234567890')) {
            return Http::response(['verified_name' => 'Loja Aurora']);
        }

        return Http::response(['error' => ['message' => 'Unexpected request: '.$graphUrl]], 500);
    });

    $response = $this->actingAs($admin)->post(
        route('channels.whatsapp.profile.photo', $channel),
        ['photo' => $photo],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Foto do Perfil do WhatsApp atualizada com sucesso.',
    ]);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/123456789012345/uploads')
        && $request->hasHeader('Authorization', 'OAuth profile-access-token')
        && str_contains($request->url(), 'file_length='.strlen($photo->getContent()))
        && str_contains($request->url(), 'file_type=image%2Fpng')
        && str_contains($request->url(), 'file_name=whatsapp-profile.png'));
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/upload:photo-session')
        && $request->hasHeader('Authorization', 'OAuth profile-access-token')
        && $request->hasHeader('file_offset', '0')
        && $request->hasHeader('Content-Type', 'image/png')
        && $request->body() === $photo->getContent());
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/15551234567890/whatsapp_business_profile')
        && $request->hasHeader('Authorization', 'Bearer profile-access-token')
        && $request->data() === [
            'messaging_product' => 'whatsapp',
            'profile_picture_handle' => 'photo-handle-123',
        ]);

    $profilePage = $this->actingAs($admin)->get(route('channels.whatsapp.edit', ['tab' => 'perfil']));
    $profilePage->assertOk();
    $profilePage->assertInertia(fn (Assert $page) => $page->loadDeferredProps(
        'default',
        fn (Assert $deferredPage) => $deferredPage
            ->where('whatsappProfile.data.profilePictureUrl', 'https://cdn.example.test/new-profile.jpg'),
    ));
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Existing channel error.');
});

it('rejects photo replacement without an App ID before contacting Meta', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfilePictureChannel($owner, null);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp.profile.photo', $channel),
        ['photo' => File::createWithContent('profile.png', createWhatsAppProfilePng())],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']));
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => 'Configure o App ID da Meta na aba Conexão para trocar a foto do Perfil do WhatsApp.',
    ]);
    Http::assertNothingSent();
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBeNull();
});

it('allows an Owner to replace the photo when an App ID is configured', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfilePictureChannel($owner);
    Http::fake([
        'graph.facebook.com/*/123456789012345/uploads*' => Http::response([
            'id' => 'upload:owner-photo-session',
        ]),
        'graph.facebook.com/*/upload:owner-photo-session' => Http::response([
            'h' => 'owner-photo-handle',
        ]),
        'graph.facebook.com/*/15551234567890/whatsapp_business_profile' => Http::response([
            'success' => true,
        ]),
    ]);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp.profile.photo', $channel),
        ['photo' => File::createWithContent('profile.png', createWhatsAppProfilePng())],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Foto do Perfil do WhatsApp atualizada com sucesso.',
    ]);
    Http::assertSentCount(3);
});

it('marks the channel unavailable when Meta rejects the photo upload with an invalid token', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfilePictureChannel($owner, '123456789012345', 'Previous error.');
    Http::fake([
        'graph.facebook.com/*/123456789012345/uploads*' => Http::response([
            'id' => 'upload:photo-session',
        ]),
        'graph.facebook.com/*/upload:photo-session' => Http::response([
            'error' => ['code' => 190, 'type' => 'OAuthException'],
        ], 401),
    ]);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp.profile.photo', $channel),
        ['photo' => File::createWithContent('profile.png', createWhatsAppProfilePng())],
    );

    $response->assertRedirect(route('channels.whatsapp.edit', ['tab' => 'perfil']));
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => 'O access token é inválido ou expirou.',
    ]);
    expect($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->toBe('O access token é inválido ou expirou.');
});

it('forbids a Member from changing the photo before contacting Meta', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    $team = $owner->company();
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);
    [$channel] = createWhatsAppProfilePictureChannel($owner);

    $response = $this->actingAs($member)->post(
        route('channels.whatsapp.profile.photo', $channel),
        ['photo' => File::createWithContent('profile.png', createWhatsAppProfilePng())],
    );

    $response->assertForbidden();
    Http::assertNothingSent();
});

it('returns not found when another company targets the photo endpoint', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    $otherOwner = User::factory()->withTeam()->create();
    [$otherChannel] = createWhatsAppProfilePictureChannel($otherOwner);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp.profile.photo', $otherChannel),
        ['photo' => File::createWithContent('profile.png', createWhatsAppProfilePng())],
    );

    $response->assertNotFound();
    Http::assertNothingSent();
});

it('rejects a photo with non-square dimensions outside the allowed range', function (int $width, int $height) {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfilePictureChannel($owner);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp.profile.photo', $channel),
        ['photo' => File::createWithContent('profile.png', createWhatsAppProfilePng($width, $height))],
    );

    $response->assertSessionHasErrors([
        'photo' => 'A foto precisa ser quadrada e ter entre 192 e 640 pixels.',
    ]);
    Http::assertNothingSent();
})->with([
    'below minimum size' => [191, 191],
    'above maximum size' => [641, 641],
    'non-square size' => [192, 193],
]);

it('rejects an unsupported image format', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfilePictureChannel($owner);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp.profile.photo', $channel),
        ['photo' => File::createWithContent(
            'profile.gif',
            base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true),
        )],
    );

    $response->assertSessionHasErrors([
        'photo' => 'A foto precisa estar no formato JPEG ou PNG.',
    ]);
    Http::assertNothingSent();
});

it('rejects a photo larger than 5 MB', function () {
    Http::preventStrayRequests();
    $owner = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppProfilePictureChannel($owner);
    $oversizedPhoto = createWhatsAppProfilePng().str_repeat('0', 5 * 1024 * 1024);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp.profile.photo', $channel),
        ['photo' => File::createWithContent('profile.png', $oversizedPhoto)],
    );

    $response->assertSessionHasErrors([
        'photo' => 'A foto não pode ultrapassar 5 MB.',
    ]);
    Http::assertNothingSent();
});
