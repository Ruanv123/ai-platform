<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Models\Channel;

final class MetaImageStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'image';
    }

    /** @param array<string, mixed> $payload */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->mediaContent(
            $channel,
            $payload,
            'image',
            $this->stringValue($this->message($payload), 'image.caption'),
        );
    }
}
