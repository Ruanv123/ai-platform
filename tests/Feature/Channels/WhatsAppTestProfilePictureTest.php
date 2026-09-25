<?php

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Enums\TeamRole;
use App\Models\Channel;
use App\Models\User;
use App\Models\WhatsAppTestConnection;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function createWhatsAppTestProfilePictureChannel(User $user, ?string $lastError = null): array
{
    $channel = Channel::factory()->for($user->company())->create([
        'type' => ChannelType::WhatsAppTest,
        'status' => ChannelStatus::Connected,
        'last_error' => $lastError,
    ]);
    $connection = WhatsAppTestConnection::factory()->for($channel)->create([
        'instance_name' => 'canal_'.$user->company()->getKey(),
        'access_token' => 'instance-profile-photo-token',
    ]);

    return [$channel, $connection];
}

function createWhatsAppTestProfilePicturePng(int $width = 192, int $height = 192): string
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

function configureWhatsAppTestProfilePictureEvolution(): void
{
    config([
        'services.evolution.base_url' => 'https://evolution.test',
        'services.evolution.api_key' => 'platform-admin-key',
    ]);
}

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => false]);
});

it('allows an Owner and an Admin to replace the test Profile photo with an expiring signed URL', function (string $role) {
    Http::preventStrayRequests();
    Storage::fake('whatsapp_media');
    $this->withoutVite();
    configureWhatsAppTestProfilePictureEvolution();
    $owner = User::factory()->withTeam()->create();
    $actor = $owner;

    if ($role === 'admin') {
        $actor = User::factory()->create();
        $owner->company()->members()->attach($actor, ['role' => TeamRole::Admin->value]);
        $actor->switchTeam($owner->company());
    }

    [$channel, $connection] = createWhatsAppTestProfilePictureChannel(
        $owner,
        'Erro anterior preservado.',
    );
    $photoBytes = createWhatsAppTestProfilePicturePng();
    $photo = File::createWithContent('profile.png', $photoBytes);
    $imageUrl = null;

    Http::fake(function (ClientRequest $request) use (&$imageUrl, $photoBytes) {
        if ($request->method() === 'POST'
            && $request->url() === 'https://evolution.test/user/profilePicture') {
            $imageUrl = $request['image'];
            $download = $this->get($imageUrl);

            $download->assertOk()
                ->assertHeader('content-type', 'image/png')
                ->assertStreamedContent($photoBytes);

            return Http::response(['success' => true]);
        }

        return Http::response(['message' => 'Requisição inesperada.'], 500);
    });

    $response = $this->actingAs($actor)->post(
        route('channels.whatsapp-test.profile.photo', $channel),
        ['photo' => $photo],
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit', ['tab' => 'perfil']));
    $response->assertInertiaFlash('toast', [
        'type' => 'success',
        'message' => 'Foto do Perfil do WhatsApp atualizada com sucesso.',
    ]);
    Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://evolution.test/user/profilePicture'
        && $request->method() === 'POST'
        && $request->header('apikey') === [$connection->access_token]
        && $request['image'] === $imageUrl);

    parse_str((string) parse_url($imageUrl, PHP_URL_QUERY), $query);
    $photoPathSegments = explode('/', trim((string) parse_url($imageUrl, PHP_URL_PATH), '/'));
    $photoExtension = array_pop($photoPathSegments);
    $photoToken = array_pop($photoPathSegments);
    $photoFilename = $photoToken.'.'.$photoExtension;

    expect(filter_var($imageUrl, FILTER_VALIDATE_URL))->not->toBeFalse()
        ->and($query)->toHaveKeys(['expires', 'signature'])
        ->and((int) $query['expires'] - now()->timestamp)->toBeGreaterThanOrEqual(297)
        ->toBeLessThanOrEqual(300)
        ->and($photoToken)->toMatch('/\A[A-Za-z0-9]{64}\z/')
        ->and($photoExtension)->toBe('png')
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');

    Storage::disk('whatsapp_media')->assertMissing('whatsapp-profile-photos/'.$photoFilename);
})->with([
    'Owner' => 'owner',
    'Admin' => 'admin',
]);

it('serves a stored test Profile photo to a guest only through its valid signed URL', function () {
    Storage::fake('whatsapp_media');
    $photoToken = str_repeat('a', 64);
    $photoPath = 'whatsapp-profile-photos/'.$photoToken.'.jpg';
    Storage::disk('whatsapp_media')->put($photoPath, 'profile-photo-bytes');
    $imageUrl = URL::temporarySignedRoute(
        'channels.whatsapp-test.profile.photo.serve',
        now()->addMinutes(5),
        ['photoToken' => $photoToken, 'extension' => 'jpg'],
    );

    $this->get($imageUrl)
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg')
        ->assertStreamedContent('profile-photo-bytes');
});

it('rejects a test Profile photo request without a signature or after the signature expires', function () {
    $routeParameters = [
        'photoToken' => str_repeat('b', 64),
        'extension' => 'jpg',
    ];
    $unsignedUrl = route('channels.whatsapp-test.profile.photo.serve', $routeParameters);
    $expiredUrl = URL::temporarySignedRoute(
        'channels.whatsapp-test.profile.photo.serve',
        now()->subMinute(),
        $routeParameters,
    );

    $this->get($unsignedUrl)->assertForbidden();
    $this->get($expiredUrl)->assertForbidden();
});

it('deletes the temporary test Profile photo after Evolution Go rejects the update', function () {
    Http::preventStrayRequests();
    Storage::fake('whatsapp_media');
    $this->withoutVite();
    configureWhatsAppTestProfilePictureEvolution();
    $owner = User::factory()->withTeam()->create();
    [$channel] = createWhatsAppTestProfilePictureChannel($owner, 'Erro anterior preservado.');
    Http::fake([
        'evolution.test/user/profilePicture' => Http::response([
            'message' => 'foto recusada',
        ], 500),
    ]);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp-test.profile.photo', $channel),
        ['photo' => File::createWithContent(
            'profile.png',
            createWhatsAppTestProfilePicturePng(),
        )],
    );

    $response->assertRedirect(route('channels.whatsapp-test.edit', ['tab' => 'perfil']));
    $response->assertInertiaFlash('toast', [
        'type' => 'error',
        'message' => 'O Evolution Go recusou a operação: foto recusada',
    ]);
    expect(Storage::disk('whatsapp_media')->allFiles('whatsapp-profile-photos'))->toBe([])
        ->and($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->fresh()->last_error)->toBe('Erro anterior preservado.');
});

it('forbids a Member from replacing the test Profile photo before contacting Evolution Go', function () {
    Http::preventStrayRequests();
    Storage::fake('whatsapp_media');
    configureWhatsAppTestProfilePictureEvolution();
    $owner = User::factory()->withTeam()->create();
    $member = User::factory()->create();
    $owner->company()->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($owner->company());
    [$channel] = createWhatsAppTestProfilePictureChannel($owner);

    $response = $this->actingAs($member)->post(
        route('channels.whatsapp-test.profile.photo', $channel),
        ['photo' => File::createWithContent(
            'profile.png',
            createWhatsAppTestProfilePicturePng(),
        )],
    );

    $response->assertForbidden();
    Http::assertNothingSent();
    expect(Storage::disk('whatsapp_media')->allFiles('whatsapp-profile-photos'))->toBe([]);
});

it('returns not found when another company targets the test Profile photo endpoint', function () {
    Http::preventStrayRequests();
    Storage::fake('whatsapp_media');
    $owner = User::factory()->withTeam()->create();
    $otherOwner = User::factory()->withTeam()->create();
    [$otherChannel] = createWhatsAppTestProfilePictureChannel($otherOwner);

    $response = $this->actingAs($owner)->post(
        route('channels.whatsapp-test.profile.photo', $otherChannel),
        ['photo' => File::createWithContent(
            'profile.png',
            createWhatsAppTestProfilePicturePng(),
        )],
    );

    $response->assertNotFound();
    Http::assertNothingSent();
    expect(Storage::disk('whatsapp_media')->allFiles('whatsapp-profile-photos'))->toBe([]);
});
