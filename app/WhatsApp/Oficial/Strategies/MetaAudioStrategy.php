<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Models\Channel;

final class MetaAudioStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'audio';
    }

    /** @param array<string, mixed> $payload */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->mediaContent($channel, $payload, 'audio', null);
    }
}
