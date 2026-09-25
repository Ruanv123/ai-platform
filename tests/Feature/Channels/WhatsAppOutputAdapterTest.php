<?php

use App\Enums\ChannelType;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use App\Models\WhatsAppTestConnection;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function createWhatsAppOutputChannel(ChannelType $channelType): Channel
{
    $channel = Channel::factory()->for(Team::factory())->create([
        'type' => $channelType,
    ]);

    if ($channelType === ChannelType::WhatsApp) {
        $account = WhatsAppAccount::factory()->for($channel->team, 'team')->create([
            'access_token' => 'official-access-token',
        ]);
        $phoneNumber = WhatsAppPhoneNumber::factory()->for($channel)->create([
            'phone_number_id' => 'official-phone-number-id',
            'whatsapp_account_id' => $account->getKey(),
        ]);
    } else {
        WhatsAppTestConnection::factory()->for($channel)->create([
            'service_url' => 'https://evolution.test',
            'instance_name' => 'local-development',
            'access_token' => 'test-access-token',
        ]);
    }

    return $channel;
}

/**
 * @return array{0: Contact, 1: Message}
 */
function createWhatsAppOutputMessage(Channel $channel): array
{
    $contact = Contact::factory()->for($channel->team)->create([
        'phone' => '5511999999999',
    ]);
    $message = Message::factory()
        ->for($contact)
        ->for($channel)
        ->incoming()
        ->create([
            'provider_message_id' => 'incoming-message-1',
            'content' => 'Olá',
        ]);

    return [$contact, $message];
}

dataset('WhatsApp output channels', [
    'official WhatsApp' => [ChannelType::WhatsApp],
    'test WhatsApp' => [ChannelType::WhatsAppTest],
]);

it('records a provider text response for each WhatsApp channel', function (ChannelType $channelType) {
    $channel = createWhatsAppOutputChannel($channelType);
    [$contact, $message] = createWhatsAppOutputMessage($channel);
    $adapter = app(WhatsAppOutputAdapterFactory::class)->make($channel);
    $sendUrl = $channelType === ChannelType::WhatsApp
        ? 'https://graph.facebook.com/v23.0/official-phone-number-id/messages'
        : 'https://evolution.test/send/text';
    $providerMessageId = $channelType === ChannelType::WhatsApp
        ? 'official-outgoing-message-1'
        : 'test-outgoing-message-1';

    Http::preventStrayRequests();
    Http::fake([
        $sendUrl => Http::response($channelType === ChannelType::WhatsApp
            ? ['messages' => [['id' => $providerMessageId]]]
            : ['data' => ['Info' => ['ID' => $providerMessageId]]]),
    ]);

    $outgoingMessage = $adapter->sendText($channel, $contact, 'Resposta do Agente.');

    $this->assertDatabaseHas('messages', [
        'id' => $outgoingMessage->id,
        'contact_id' => $contact->id,
        'channel_id' => $channel->id,
        'direction' => MessageDirection::Outgoing->value,
        'payload_type' => 'text',
        'provider_message_id' => $providerMessageId,
        'content' => 'Resposta do Agente.',
        'delivery_status' => 'sent',
    ]);
    expect($outgoingMessage->direction)->toBe(MessageDirection::Outgoing)
        ->and($outgoingMessage->provider_message_id)->toBe($providerMessageId)
        ->and($outgoingMessage->content)->toBe('Resposta do Agente.')
        ->and($message->fresh()->direction)->toBe(MessageDirection::Incoming);

    Http::assertSent(function (Request $request) use ($channelType, $sendUrl): bool {
        $expectedData = $channelType === ChannelType::WhatsApp
            ? [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => '5511999999999',
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => 'Resposta do Agente.',
                ],
            ]
            : [
                'number' => '5511999999999',
                'text' => 'Resposta do Agente.',
            ];

        return $request->url() === $sendUrl
            && $request->data() === $expectedData
            && ($channelType !== ChannelType::WhatsAppTest || $request->header('apikey') === ['test-access-token']);
    });
})->with('WhatsApp output channels');

it('marks the incoming message as read and shows typing for each WhatsApp channel', function (ChannelType $channelType) {
    $channel = createWhatsAppOutputChannel($channelType);
    [$contact, $message] = createWhatsAppOutputMessage($channel);
    $adapter = app(WhatsAppOutputAdapterFactory::class)->make($channel);
    $readUrl = $channelType === ChannelType::WhatsApp
        ? 'https://graph.facebook.com/v23.0/official-phone-number-id/messages'
        : 'https://evolution.test/message/markread';
    $typingUrl = $channelType === ChannelType::WhatsApp
        ? 'https://graph.facebook.com/v23.0/official-phone-number-id/messages'
        : 'https://evolution.test/message/presence';

    Http::preventStrayRequests();
    Http::fake([
        $readUrl => Http::response([]),
        $typingUrl => Http::response([]),
    ]);

    $adapter->markIncomingMessageAsRead($channel, $message);
    $adapter->sendTypingIndicator($channel, $message);

    Http::assertSent(function (Request $request) use ($channelType, $readUrl): bool {
        $expectedData = $channelType === ChannelType::WhatsApp
            ? [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => 'incoming-message-1',
            ]
            : [
                'number' => '5511999999999',
                'id' => ['incoming-message-1'],
            ];

        return $request->url() === $readUrl
            && $request->data() === $expectedData
            && ($channelType !== ChannelType::WhatsAppTest || $request->header('apikey') === ['test-access-token']);
    });
    Http::assertSent(function (Request $request) use ($channelType, $typingUrl): bool {
        $expectedData = $channelType === ChannelType::WhatsApp
            ? [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => 'incoming-message-1',
                'typing_indicator' => ['type' => 'text'],
            ]
            : [
                'number' => '5511999999999',
                'state' => 'composing',
                'delay' => 5000,
            ];

        return $request->url() === $typingUrl
            && $request->data() === $expectedData
            && ($channelType !== ChannelType::WhatsAppTest || $request->header('apikey') === ['test-access-token']);
    });
})->with('WhatsApp output channels');
