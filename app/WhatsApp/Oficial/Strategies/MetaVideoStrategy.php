<?php

namespace App\WhatsApp\Oficial\Strategies;

final class MetaVideoStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'video';
    }

    /** @param array<string, mixed> $payload */
    protected function content(array $payload): ?string
    {
        return $this->stringValue($this->message($payload), 'video.caption');
    }
}
