<?php

use App\Enums\ChannelType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Team;
use App\Models\WhatsAppTestConnection;

function whatsappTestWebhookFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path("tests/Fixtures/whatsapp/testes/{$name}.json")),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function createWhatsAppTestWebhookChannel(): Channel
{
    $team = Team::factory()->create();
    $channel = Channel::factory()->for($team)->create([
        'type' => ChannelType::WhatsAppTest,
    ]);

    WhatsAppTestConnection::factory()->for($channel)->create([
        'instance_name' => 'local-development',
    ]);

    return $channel;
}

it('accepts a test webhook without authentication or a CSRF token', function () {
    createWhatsAppTestWebhookChannel();

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('message-text'));

    $response->assertOk()->assertContent('EVENT_RECEIVED');
    $this->assertDatabaseHas('messages', [
        'event_type' => 'text',
        'direction' => MessageDirection::Incoming->value,
        'provider_message_id' => 'test-message-text-1',
        'content' => 'Olá, preciso de ajuda com meu pedido.',
    ]);
    $this->assertDatabaseHas('contacts', [
        'phone' => '5511999999999',
        'name' => 'Ana Souza',
    ]);
});

it('rejects a test webhook for an unknown instance', function () {
    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('message-text'));

    $response->assertForbidden();
    $this->assertDatabaseCount('contacts', 0);
    $this->assertDatabaseCount('messages', 0);
});

it('registers every supported test message type at the webhook seam', function (string $fixture, string $eventType, ?string $content) {
    createWhatsAppTestWebhookChannel();

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture($fixture));

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'event_type' => $eventType,
        'direction' => MessageDirection::Incoming->value,
        'content' => $content,
    ]);
})->with([
    'image' => ['message-image', 'image', 'Esta é a foto do problema.'],
    'audio' => ['message-audio', 'audio', null],
    'video' => ['message-video', 'video', 'Um vídeo do defeito.'],
    'document' => ['message-document', 'document', 'Segue o comprovante.'],
]);

it('records a sent test message as outgoing', function () {
    createWhatsAppTestWebhookChannel();

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('send-message'));

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'event_type' => 'text',
        'direction' => MessageDirection::Outgoing->value,
        'provider_message_id' => 'test-message-send-1',
        'delivery_status' => MessageDeliveryStatus::Sent->value,
        'content' => 'Resposta enviada pelo Agente.',
    ]);
});

it('updates the matching outgoing message when the test provider sends a read receipt', function () {
    $channel = createWhatsAppTestWebhookChannel();
    $contact = Contact::factory()->for($channel->team)->create([
        'phone' => '5511999999999',
    ]);
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create([
            'provider_message_id' => 'test-message-send-1',
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('read-receipt'));

    $response->assertOk();
    expect($message->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Read);
    $this->assertDatabaseCount('messages', 1);
});

it('registers unsupported test message types with their raw provider type', function () {
    createWhatsAppTestWebhookChannel();

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('message-unsupported'));

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'event_type' => 'stickerMessage',
        'direction' => MessageDirection::Incoming->value,
        'provider_message_id' => 'test-message-sticker-1',
    ]);
});

it('does not register the same provider message twice', function () {
    createWhatsAppTestWebhookChannel();
    $payload = whatsappTestWebhookFixture('message-text');

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();
    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseCount('contacts', 1);
});

it('uses the provider timestamp when messages arrive out of order', function () {
    $channel = createWhatsAppTestWebhookChannel();
    $latestPayload = whatsappTestWebhookFixture('message-text');
    $latestPayload['data']['key']['id'] = 'test-message-latest';
    $latestPayload['data']['messageTimestamp'] = 1760000100;
    $latestPayload['data']['message']['conversation'] = 'Mensagem mais nova';
    $oldPayload = whatsappTestWebhookFixture('message-text');
    $oldPayload['data']['key']['id'] = 'test-message-old';
    $oldPayload['data']['messageTimestamp'] = 1760000000;
    $oldPayload['data']['message']['conversation'] = 'Mensagem mais antiga';

    $this->postJson(route('webhooks.whatsapp-test'), $latestPayload)->assertOk();
    $this->postJson(route('webhooks.whatsapp-test'), $oldPayload)->assertOk();

    $contact = Contact::query()->where('team_id', $channel->team_id)->firstOrFail();
    expect($contact->history($channel)->pluck('content')->all())
        ->toBe(['Mensagem mais antiga', 'Mensagem mais nova']);
});
