<?php

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTestConnection;
use Illuminate\Support\Facades\Http;
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

it('transcribes audio at the webhook seam and stores the transcription', function () {
    createOfficialMediaChannel();
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
    Transcription::assertGenerated(fn ($prompt): bool => $prompt->language === 'pt');
});

it('describes an image and keeps its caption in the resolved content', function () {
    createOfficialMediaChannel();
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
});

it('extracts a downloaded document with the knowledge-base extractor', function () {
    createOfficialMediaChannel();
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
    ]);
});

it('resolves embedded audio from the test WhatsApp webhook', function () {
    createTestMediaChannel();
    Transcription::fake(['Áudio recebido no canal de testes.']);
    $payload = mediaTestFixture('message-audio');
    $payload['data']['message']['audioMessage']['base64'] = base64_encode('audio-content');
    $payload['data']['message']['audioMessage']['mimetype'] = 'audio/ogg';

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $this->assertDatabaseHas('messages', [
        'payload_type' => 'audio',
        'content' => 'Áudio recebido no canal de testes.',
    ]);
    Transcription::assertGenerated(fn ($prompt): bool => $prompt->language === 'pt');
});

it('records an explicit warning for videos', function () {
    createOfficialMediaChannel();

    postOfficialMediaWebhook($this, mediaResolutionFixture('message-video'));

    $this->assertDatabaseHas('messages', [
        'payload_type' => 'video',
        'content' => "Um vídeo do defeito.\n\nVídeos ainda não são suportados. Envie um áudio, uma imagem, um documento ou explique o problema por texto.",
    ]);
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
});
