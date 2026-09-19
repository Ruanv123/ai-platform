<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Models\Channel;

final class MetaTextStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'text';
    }

    /** @param array<string, mixed> $payload */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->stringValue($this->message($payload), 'text.body');
    }
}
