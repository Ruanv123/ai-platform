<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Models\Channel;

final class MetaVideoStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'video';
    }

    /** @param array<string, mixed> $payload */
    protected function content(Channel $channel, array $payload): string
    {
        return $this->videoContent($channel, $payload);
    }
}
