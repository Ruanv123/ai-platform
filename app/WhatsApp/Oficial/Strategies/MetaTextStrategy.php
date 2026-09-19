<?php

namespace App\WhatsApp\Oficial\Strategies;

final class MetaTextStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'text';
    }

    /** @param array<string, mixed> $payload */
    protected function content(array $payload): ?string
    {
        return $this->stringValue($this->message($payload), 'text.body');
    }
}
