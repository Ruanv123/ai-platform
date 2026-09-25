<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Models\Channel;

final class MetaButtonReplyStrategy extends MetaMessageStrategy
{
    protected function eventType(array $payload): string
    {
        return 'text';
    }

    protected function content(Channel $channel, array $payload): ?string
    {
        $message = $this->message($payload);

        return ($message['type'] ?? null) === 'button'
            ? $this->stringValue($message, 'button.text')
            : $this->stringValue($message, 'interactive.button_reply.title');
    }

    protected function buttonPayload(array $messageData): ?string
    {
        return ($messageData['type'] ?? null) === 'button'
            ? $this->stringValue($messageData, 'button.payload')
            : $this->stringValue($messageData, 'interactive.button_reply.id');
    }
}
