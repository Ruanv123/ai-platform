<?php

namespace App\WhatsApp\Oficial\Strategies;

final class MetaDocumentStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'document';
    }

    /** @param array<string, mixed> $payload */
    protected function content(array $payload): ?string
    {
        return $this->stringValue($this->message($payload), 'document.caption');
    }
}
