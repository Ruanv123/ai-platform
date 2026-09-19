<?php

use App\Enums\ChannelType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTestConnection;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * @return array<string, mixed>
 */
function whatsappConformanceFixture(string $fixtureDirectory, string $fixture): array
{
    $path = base_path("tests/Fixtures/whatsapp/{$fixtureDirectory}/{$fixture}.json");

    TestCase::assertFileExists($path, "The {$fixtureDirectory} channel is missing the {$fixture} webhook fixture.");
    $contents = file_get_contents($path);
    TestCase::assertIsString($contents);

    return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
}

function createWhatsAppConformanceChannel(ChannelType $channelType): Channel
{
    $channel = Channel::factory()->for(Team::factory())->create([
        'type' => $channelType,
    ]);

    if ($channelType === ChannelType::WhatsApp) {
        $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create([
            'phone_number_id' => 'official-phone-number-id',
        ]);
        WhatsAppAccount::factory()->for($phoneNumber, 'phoneNumber')->create([
            'app_secret' => 'conformance-app-secret',
        ]);
    } else {
        WhatsAppTestConnection::factory()->for($channel)->create([
            'instance_name' => 'local-development',
        ]);
    }

    return $channel;
}

/**
 * @param  array<string, mixed>  $payload
 * @return TestResponse<Response>
 */
function postWhatsAppConformanceWebhook(
    TestCase $testCase,
    ChannelType $channelType,
    string $routeName,
    array $payload,
): TestResponse {
    if ($channelType === ChannelType::WhatsAppTest) {
        return $testCase->postJson(route($routeName), $payload);
    }

    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return $testCase->call('POST', route($routeName), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'conformance-app-secret'),
    ], $body);
}

dataset('WhatsApp webhook channels', [
    'official WhatsApp' => [ChannelType::WhatsApp, 'webhooks.whatsapp', 'oficial'],
    'test WhatsApp' => [ChannelType::WhatsAppTest, 'webhooks.whatsapp-test', 'testes'],
]);

it('creates the contact and records each core incoming message', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
    string $fixture,
    string $eventType,
    ?string $content,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $payload = whatsappConformanceFixture($fixtureDirectory, $fixture);

    $response = postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload);

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseHas('contacts', [
        'team_id' => $channel->team_id,
        'phone' => '5511999999999',
        'name' => 'Ana Souza',
    ]);
    $this->assertDatabaseHas('messages', [
        'team_id' => $channel->team_id,
        'channel_id' => $channel->id,
        'event_type' => $eventType,
        'direction' => MessageDirection::Incoming->value,
        'content' => $content,
    ]);
})->with('WhatsApp webhook channels')->with([
    'text' => ['message-text', 'text', 'Olá, preciso de ajuda com meu pedido.'],
    'image' => ['message-image', 'image', 'Esta é a foto do problema.'],
    'audio' => ['message-audio', 'audio', null],
    'video' => ['message-video', 'video', 'Um vídeo do defeito.'],
    'document' => ['message-document', 'document', 'Segue o comprovante.'],
]);

it('does not record the same provider message twice', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
) {
    createWhatsAppConformanceChannel($channelType);
    $payload = whatsappConformanceFixture($fixtureDirectory, 'message-text');

    postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload)->assertOk();
    postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload)->assertOk();

    $this->assertDatabaseCount('contacts', 1);
    $this->assertDatabaseCount('messages', 1);
})->with('WhatsApp webhook channels');

it('records an unsupported event with the raw provider type', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $payload = whatsappConformanceFixture($fixtureDirectory, 'message-unsupported');
    $expectedEventType = $channelType === ChannelType::WhatsApp ? 'sticker' : 'stickerMessage';

    $response = postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload);

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'channel_id' => $channel->id,
        'event_type' => $expectedEventType,
        'direction' => MessageDirection::Incoming->value,
        'content' => null,
    ]);
})->with('WhatsApp webhook channels');

it('updates the matching outgoing message delivery status', function (
    ChannelType $channelType,
    string $routeName,
    string $fixtureDirectory,
) {
    $channel = createWhatsAppConformanceChannel($channelType);
    $contact = Contact::factory()->for($channel->team)->create([
        'phone' => '5511999999999',
    ]);
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'outgoing-message-1',
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    $payload = whatsappConformanceFixture($fixtureDirectory, 'delivery-status');

    $response = postWhatsAppConformanceWebhook($this, $channelType, $routeName, $payload);

    $response->assertOk();
    expect($message->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Read);
    $this->assertDatabaseCount('messages', 1);
})->with('WhatsApp webhook channels');
