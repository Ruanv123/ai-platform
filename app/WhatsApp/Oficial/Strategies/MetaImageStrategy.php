<?php

namespace App\WhatsApp\Oficial\Strategies;

final class MetaImageStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'image';
    }

    /** @param array<string, mixed> $payload */
    protected function content(array $payload): ?string
    {
        return $this->stringValue($this->message($payload), 'image.caption');
    }
}
