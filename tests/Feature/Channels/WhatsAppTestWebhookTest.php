<?php

use App\Enums\ChannelStatus;
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
        'payload_type' => 'text',
        'direction' => MessageDirection::Incoming->value,
        'provider_message_id' => 'test-message-text-1',
        'content' => 'Olá, preciso de ajuda com meu pedido.',
    ]);
    $this->assertDatabaseHas('contacts', [
        'phone' => '+5511999999999',
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
        'payload_type' => $eventType,
        'direction' => MessageDirection::Incoming->value,
        'content' => $content,
    ]);
})->with([
    'image' => ['message-image', 'image', 'Esta é a foto do problema.'],
    'audio' => ['message-audio', 'audio', null],
    'video' => ['message-video', 'video', "Um vídeo do defeito.\n\nVídeos ainda não são suportados. Envie um áudio, uma imagem, um documento ou explique o problema por texto."],
    'document' => ['message-document', 'document', 'Segue o comprovante.'],
]);

it('records a sent test message as outgoing', function () {
    createWhatsAppTestWebhookChannel();

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('send-message'));

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'payload_type' => 'text',
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

it('records a message typed on the paired phone to someone else as outgoing history', function () {
    createWhatsAppTestWebhookChannel();
    $payload = whatsappTestWebhookFixture('message-text');
    $payload['data']['Info']['IsFromMe'] = true;
    $payload['data']['Info']['Sender'] = '11313078092023@lid';
    $payload['data']['Info']['Chat'] = '5511999999999@s.whatsapp.net';

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'direction' => MessageDirection::Outgoing->value,
    ]);
});

it('ignores group messages instead of creating a Contact for them', function () {
    createWhatsAppTestWebhookChannel();
    $payload = whatsappTestWebhookFixture('message-text');
    $payload['data']['Info']['Chat'] = '120363430751518641@g.us';
    $payload['data']['Info']['IsGroup'] = true;
    $payload['data']['Info']['SenderAlt'] = '233522270871781@lid';

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $this->assertDatabaseCount('messages', 0);
    $this->assertDatabaseCount('contacts', 0);
});

it('resolves the Contact phone from the alternate JID when the chat is addressed by an opaque lid', function () {
    createWhatsAppTestWebhookChannel();
    $incoming = whatsappTestWebhookFixture('message-text');
    $incoming['data']['Info']['Chat'] = '11313078092023@lid';
    $incoming['data']['Info']['Sender'] = '11313078092023@lid';
    $incoming['data']['Info']['SenderAlt'] = '5511999999999@s.whatsapp.net';
    $sent = whatsappTestWebhookFixture('send-message');
    $sent['data']['Info']['Chat'] = '11313078092023@lid';
    $sent['data']['Info']['RecipientAlt'] = '5511999999999@s.whatsapp.net';

    $this->postJson(route('webhooks.whatsapp-test'), $incoming)->assertOk();
    $this->postJson(route('webhooks.whatsapp-test'), $sent)->assertOk();

    $this->assertDatabaseCount('contacts', 1);
    $this->assertDatabaseHas('contacts', ['phone' => '+5511999999999', 'name' => 'Ana Souza']);
    $this->assertDatabaseCount('messages', 2);
});

it('marks every message named by a read receipt and ignores receipts that are not reads', function () {
    $channel = createWhatsAppTestWebhookChannel();
    $contact = Contact::factory()->for($channel->team)->create(['phone' => '5511999999999']);
    $messages = collect(['sent-1', 'sent-2'])->map(fn (string $id): Message => Message::factory()
        ->for($contact)
        ->for($channel)
        ->outgoing()
        ->create(['provider_message_id' => $id, 'delivery_status' => MessageDeliveryStatus::Sent]));
    $delivered = whatsappTestWebhookFixture('read-receipt');
    $delivered['data']['MessageIDs'] = ['sent-1', 'sent-2'];
    $delivered['data']['Type'] = 'delivered';
    $read = $delivered;
    $read['data']['Type'] = 'read';

    $this->postJson(route('webhooks.whatsapp-test'), $delivered)->assertOk();
    expect($messages->every(fn (Message $message): bool => $message->fresh()->delivery_status === MessageDeliveryStatus::Sent))->toBeTrue();

    $this->postJson(route('webhooks.whatsapp-test'), $read)->assertOk();
    expect($messages->every(fn (Message $message): bool => $message->fresh()->delivery_status === MessageDeliveryStatus::Read))->toBeTrue();
});

it('rejects a webhook for an instance it does not know, using the native instance name', function () {
    createWhatsAppTestWebhookChannel();
    $payload = whatsappTestWebhookFixture('message-text');
    $payload['instanceName'] = 'someone-else';

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertForbidden();
});

it('registers unsupported test message types with their raw provider type', function () {
    createWhatsAppTestWebhookChannel();

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('message-unsupported'));

    $response->assertOk();
    $this->assertDatabaseHas('messages', [
        'payload_type' => 'stickerMessage',
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

it('ignores the QR code pushed for the pairing session since it is read live from Evolution Go', function () {
    $channel = createWhatsAppTestWebhookChannel();

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('qrcode'));

    $response->assertOk();
    expect($channel->whatsappTestConnection->fresh()->qrcode)->toBeNull()
        ->and($channel->fresh()->status)->toBe(ChannelStatus::NotConfigured);
    $this->assertDatabaseCount('messages', 0);
});

it('connects the Channel and clears the QR code once the session pairs', function () {
    $channel = createWhatsAppTestWebhookChannel();
    $channel->whatsappTestConnection->update(['qrcode' => 'data:image/png;base64,stale']);

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('connection-open'));

    $response->assertOk();
    expect($channel->fresh()->status)->toBe(ChannelStatus::Connected)
        ->and($channel->whatsappTestConnection->fresh()->qrcode)->toBeNull();
});

it('marks the Channel in error when the pairing session logs out', function () {
    $channel = createWhatsAppTestWebhookChannel();
    $channel->update(['status' => ChannelStatus::Connected]);

    $response = $this->postJson(route('webhooks.whatsapp-test'), whatsappTestWebhookFixture('connection-logged-out'));

    $response->assertOk();
    expect($channel->fresh()->status)->toBe(ChannelStatus::Error)
        ->and($channel->fresh()->last_error)->not->toBeNull();
});

it('uses the provider timestamp when messages arrive out of order', function () {
    $channel = createWhatsAppTestWebhookChannel();
    $latestPayload = whatsappTestWebhookFixture('message-text');
    $latestPayload['data']['Info']['ID'] = 'test-message-latest';
    $latestPayload['data']['Info']['Timestamp'] = '2025-10-09T08:55:00Z';
    $latestPayload['data']['Message']['conversation'] = 'Mensagem mais nova';
    $oldPayload = whatsappTestWebhookFixture('message-text');
    $oldPayload['data']['Info']['ID'] = 'test-message-old';
    $oldPayload['data']['Info']['Timestamp'] = '2025-10-09T08:53:20Z';
    $oldPayload['data']['Message']['conversation'] = 'Mensagem mais antiga';

    $this->postJson(route('webhooks.whatsapp-test'), $latestPayload)->assertOk();
    $this->postJson(route('webhooks.whatsapp-test'), $oldPayload)->assertOk();

    $contact = Contact::query()->where('team_id', $channel->team_id)->firstOrFail();
    expect($contact->history($channel)->pluck('content')->all())
        ->toBe(['Mensagem mais antiga', 'Mensagem mais nova']);
});

it('stores provider timestamps with an offset in the application timezone', function () {
    createWhatsAppTestWebhookChannel();
    $payload = whatsappTestWebhookFixture('message-text');
    $payload['data']['Info']['Timestamp'] = '2026-09-24T23:38:20-03:00';

    $this->postJson(route('webhooks.whatsapp-test'), $payload)->assertOk();

    $this->assertDatabaseHas('messages', [
        'provider_message_id' => 'test-message-text-1',
        'created_at' => '2026-09-25 02:38:20',
    ]);
});
