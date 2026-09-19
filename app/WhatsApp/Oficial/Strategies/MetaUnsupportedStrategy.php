<?php

namespace App\WhatsApp\Oficial\Strategies;

use Illuminate\Support\Str;

final class MetaUnsupportedStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        $type = $this->message($payload)['type'] ?? 'unsupported';

        return is_scalar($type) && filled($type)
            ? Str::substr((string) $type, 0, 64)
            : 'unsupported';
    }

    /** @param array<string, mixed> $payload */
    protected function content(array $payload): ?string
    {
        return null;
    }
}
