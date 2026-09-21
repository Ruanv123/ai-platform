<?php

namespace App\WhatsApp\Output;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use InvalidArgumentException;

abstract class AbstractWhatsAppOutputAdapter implements WhatsAppOutputAdapter
{
    protected function recordOutgoingText(
        Channel $channel,
        Contact $contact,
        string $content,
        string $providerMessageId,
    ): Message {
        return Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => 'text',
            'provider_message_id' => $providerMessageId,
            'content' => $content,
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    }

    /** @param list<string> $parameters */
    protected function recordOutgoingTemplate(
        Channel $channel,
        Contact $contact,
        string $template,
        array $parameters,
        string $providerMessageId,
    ): Message {
        return Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => 'template',
            'provider_message_id' => $providerMessageId,
            'content' => json_encode(['template' => $template, 'parameters' => $parameters], JSON_THROW_ON_ERROR),
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    }

    protected function assertContactBelongsToChannel(Channel $channel, Contact $contact): void
    {
        if ($contact->team_id !== $channel->team_id) {
            throw new InvalidArgumentException('O Contato não pertence à Empresa do Canal.');
        }
    }

    protected function assertIncomingMessageBelongsToChannel(Channel $channel, Message $message): void
    {
        $message->loadMissing('contact');

        if (
            $message->channel_id !== $channel->getKey()
            || $message->contact->team_id !== $channel->team_id
            || $message->direction !== MessageDirection::Incoming
            || blank($message->provider_message_id)
        ) {
            throw new InvalidArgumentException('A Mensagem de entrada não pertence ao Canal ou não possui id do provedor.');
        }
    }
}
