<?php

namespace App\WhatsApp\Oficial\Strategies;

final class MetaAudioStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'audio';
    }

    /** @param array<string, mixed> $payload */
    protected function content(array $payload): ?string
    {
        return null;
    }
}
