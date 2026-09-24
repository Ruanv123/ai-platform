<?php

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTestConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Transcription;
use Tests\TestCase;

function mediaResolutionFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path("tests/Fixtures/whatsapp/oficial/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function createOfficialMediaChannel(): Channel
{
    $channel = Channel::factory()->for(Team::factory())->create([
        'type' => ChannelType::WhatsApp,
    ]);
    $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create([
        'phone_number_id' => 'official-phone-number-id',
    ]);
    WhatsAppAccount::factory()->for($phoneNumber, 'phoneNumber')->create([
        'app_secret' => 'media-test-secret',
        'access_token' => 'media-test-token',
    ]);

    return $channel;
}

function postOfficialMediaWebhook(TestCase $testCase, array $payload): void
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    $testCase->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'media-test-secret'),
    ], $body)->assertOk();
}

function createTestMediaChannel(): Channel
{
    $channel = Channel::factory()->for(Team::factory())->create([
        'type' => ChannelType::WhatsAppTest,
    ]);

    WhatsAppTestConnection::factory()->for($channel)->create([
        'instance_name' => 'local-development',
    ]);

    return $channel;
}

function mediaTestFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path("tests/Fixtures/whatsapp/testes/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

beforeEach(function () {
    Storage::fake('whatsapp_media');
});

it('transcribes audio at the webhook seam and stores the transcription', function () {
    $channel = createOfficialMediaChannel();
    Transcription::fake(['O áudio fala sobre o prazo do pedido.']);
    Http::fake([
        'graph.facebook.com/*' => Http::response(['url' => 'https://media.example/audio', 'mime_type' => 'audio/ogg']),
        'media.example/*' => Http::response('audio-content', 200, ['Content-Type' => 'audio/ogg']),
    ]);

    postOfficialMediaWebhook($this, mediaResolutionFixture('message-audio'));

    $this->assertDatabaseHas('messages', [
        'payload_type' => 'audio',
        'content' => 'O áudio fala sobre o prazo do pedido.',
    ]);
    $message = Message::query()->where('channel_id', $channel->getKey())->firstOrFail();
    expect($message->media_available)->toBeTrue();
    Storage::disk('whatsapp_media')->assertExists($message->media_path);
    Transcription::assertGenerated(fn ($prompt): bool => $prompt->language === 'pt');
});

it('describes an image and keeps its caption in the resolved content', function () {
    $channel = createOfficialMediaChannel();
    $imageCalls = 0;
    Image::fake(function () use (&$imageCalls): string {
        $imageCalls++;

        return 'A imagem mostra uma caixa danificada.';
    });
    Http::fake([
        'graph.facebook.com/*' => Http::response(['url' => 'https://media.example/image', 'mime_type' => 'image/jpeg']),
        'media.example/*' => Http::response('image-content', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    postOfficialMediaWebhook($this, mediaResolutionFixture('message-image'));

    $this->assertDatabaseHas('messages', [
        'payload_type' => 'image',
        'content' => "Esta é a foto do problema.\n\nA imagem mostra uma caixa danificada.",
    ]);
    expect($imageCalls)->toBe(1);
    Image::assertGenerated(fn ($prompt): bool => $prompt->contains('Descreva em português'));

    $message = Message::query()->where('provider_message_id', 'official-message-image-1')->firstOrFail();
    expect($message->media_available)->toBeTrue()
        ->and($message->media_filename)->toBe('whatsapp-media.jpg');
    Storage::disk('whatsapp_media')->assertExists($message->media_path);

    $owner = User::factory()->withTeam($channel->team)->create();
    $this->actingAs($owner)
        ->get(route('inbox.messages.media', [$message->contact_id, $message->channel_id, $message->id]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($owner)
        ->get(route('inbox.messages.media', [$message->contact_id, $message->channel_id, $message->id]), ['Range' => 'bytes=0-4'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 0-4/13');

    $this->actingAs($owner)
        ->get(route('inbox.show', [$message->contact_id, $message->channel_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.messages.data.0.mediaAvailable', true)
            ->where('selected.messages.data.0.mediaUrl', route('inbox.messages.media', [$message->contact_id, $message->channel_id, $message->id])));

    $otherTeamUser = User::factory()->withTeam()->create();
    $this->actingAs($otherTeamUser)
        ->get(route('inbox.messages.media', [$message->contact_id, $message->channel_id, $message->id]))
        ->assertNotFound();

    Storage::disk('whatsapp_media')->delete($message->media_path);
    $this->actingAs($owner)
        ->get(route('inbox.messages.media', [$message->contact_id, $message->channel_id, $message->id]))
        ->assertNotFound();
});

it('extracts a downloaded document with the knowledge-base extractor', function () {
    $channel = createOfficialMediaChannel();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['url' => 'https://media.example/document', 'mime_type' => 'text/plain']),
        'media.example/*' => Http::response("Pedido 123\nPrazo de entrega: 7 dias.", 200, ['Content-Type' => 'text/plain']),
    ]);

    $payload = mediaResolutionFixture('message-document');
    $payload['entry'][0]['changes'][0]['value']['messages'][0]['document']['filename'] = 'pedido.txt';
    postOfficialMediaWebhook($this, $payload);

    $this->assertDatabaseHas('messages', [
        'payload_type' => 'document',
        'content' => "Segue o comprovante.\n\nPedido 123\nPrazo de entrega: 7 dias.",
    ]);
    $message = Message::query()->where('channel_id', $channel->getKey())->firstOrFail();
    expect($message->media_available)->toBeTrue()
        ->and($message->media_filename)->toBe('pedido.txt');
    Storage::disk('whatsapp_media')->assertExists($message->media_path);
});

it('stores a media failure without failing the webhook', function () {
    createOfficialMediaChannel();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => 'provider unavailable'], 503),
    ]);

    $response = $this->call('POST', route('webhooks.whatsapp'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac(
            'sha256',
            $body = json_encode(mediaResolutionFixture('message-audio'), JSON_THROW_ON_ERROR),
            'media-test-secret',
        ),
    ], $body);

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'payload_type' => 'audio',
        'content' => 'Não foi possível processar esta mídia. O atendimento continuará com as demais mensagens.',
        'media_available' => false,
    ]);
});

it('resolves embedded audio from the test WhatsApp webhook', function () {
    createTestMediaChannel();
    Transcription::fake(['Áudio recebido no canal de testes.']);
    $payload = mediaTestFixture('message-audio');
    $payload['data']['Message']['base64'] = base64_encode('audio-content');
    $payload['data']['Message']['audioMessage']['mimetype'] = 'audio/ogg';

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $this->assertDatabaseHas('messages', [
        'payload_type' => 'audio',
        'content' => 'Áudio recebido no canal de testes.',
    ]);
    Transcription::assertGenerated(fn ($prompt): bool => $prompt->language === 'pt');
});

it('stores original image, audio, video, and document media for test channels', function (string $fixture, string $type, string $mimeType, string $contents) {
    $channel = createTestMediaChannel();
    $payload = mediaTestFixture($fixture);
    $messageKey = $type.'Message';
    $payload['data']['Message']['base64'] = base64_encode($contents);
    $payload['data']['Message'][$messageKey]['mimetype'] = $mimeType;

    if ($type === 'image') {
        Image::fake(['A imagem mostra uma etiqueta.']);
    } elseif ($type === 'audio') {
        Transcription::fake(['O áudio descreve o defeito.']);
    } elseif ($type === 'document') {
        $payload['data']['Message'][$messageKey]['fileName'] = 'pedido.txt';
        $payload['data']['Message'][$messageKey]['mimetype'] = 'text/plain';
    }

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $message = Message::query()
        ->where('channel_id', $channel->getKey())
        ->where('provider_message_id', data_get($payload, 'data.Info.ID'))
        ->firstOrFail();
    expect($message->media_available)->toBeTrue()
        ->and(Storage::disk('whatsapp_media')->get($message->media_path))->toBe($contents);

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();
    $this->assertDatabaseCount('messages', 1);
    expect(Storage::disk('whatsapp_media')->allFiles())->toHaveCount(1);

    $message->delete();
    Storage::disk('whatsapp_media')->assertMissing($message->media_path);
})->with([
    'image' => ['message-image', 'image', 'image/jpeg', 'image-content'],
    'audio' => ['message-audio', 'audio', 'audio/ogg', 'audio-content'],
    'video' => ['message-video', 'video', 'video/mp4', 'video-content'],
    'document' => ['message-document', 'document', 'application/pdf', 'document-content'],
]);

it('records an explicit warning for videos', function () {
    $channel = createOfficialMediaChannel();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['url' => 'https://media.example/video', 'mime_type' => 'video/mp4']),
        'media.example/*' => Http::response('video-content', 200, ['Content-Type' => 'video/mp4']),
    ]);

    postOfficialMediaWebhook($this, mediaResolutionFixture('message-video'));

    $this->assertDatabaseHas('messages', [
        'payload_type' => 'video',
        'content' => "Um vídeo do defeito.\n\nVídeos ainda não são suportados. Envie um áudio, uma imagem, um documento ou explique o problema por texto.",
    ]);
    $message = Message::query()->where('channel_id', $channel->getKey())->firstOrFail();
    expect($message->media_available)->toBeTrue();
    Storage::disk('whatsapp_media')->assertExists($message->media_path);
});

it('marks official and test channel stickers without storing or exposing them', function (string $provider) {
    if ($provider === 'official') {
        createOfficialMediaChannel();
        postOfficialMediaWebhook($this, mediaResolutionFixture('message-unsupported'));

        $this->assertDatabaseHas('messages', [
            'provider_message_id' => 'official-message-sticker-1',
            'payload_type' => 'sticker',
            'content' => 'Figurinha não exibida',
            'media_available' => null,
        ]);

        return;
    }

    createTestMediaChannel();
    $this->postJson(route('webhooks.whatsapp-test'), mediaTestFixture('message-unsupported'))->assertOk();
    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-sticker-1',
        'payload_type' => 'stickerMessage',
        'content' => 'Figurinha não exibida',
        'media_available' => null,
    ]);
})->with(['official', 'test']);

it('removes channel media when the channel and its messages are deleted', function () {
    $channel = createTestMediaChannel();
    Image::fake(['A imagem mostra um recibo.']);
    $payload = mediaTestFixture('message-image');
    $payload['data']['Message']['base64'] = base64_encode('image-content');
    $payload['data']['Message']['imageMessage']['mimetype'] = 'image/jpeg';

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();
    $message = Message::query()->where('channel_id', $channel->getKey())->firstOrFail();
    Storage::disk('whatsapp_media')->assertExists($message->media_path);

    $channel->delete();

    Storage::disk('whatsapp_media')->assertMissing($message->media_path);
});

it('resolves a repeated provider event only once', function () {
    createOfficialMediaChannel();
    $imageCalls = 0;
    Image::fake(function () use (&$imageCalls): string {
        $imageCalls++;

        return 'Descrição única.';
    });
    Http::fake([
        'graph.facebook.com/*' => Http::response(['url' => 'https://media.example/image', 'mime_type' => 'image/jpeg']),
        'media.example/*' => Http::response('image-content', 200, ['Content-Type' => 'image/jpeg']),
    ]);
    $payload = mediaResolutionFixture('message-image');

    postOfficialMediaWebhook($this, $payload);
    postOfficialMediaWebhook($this, $payload);

    expect($imageCalls)->toBe(1);
    $this->assertDatabaseCount('messages', 1);
    expect(Storage::disk('whatsapp_media')->allFiles())->toHaveCount(1);
});
