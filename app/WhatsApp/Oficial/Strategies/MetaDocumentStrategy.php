<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Models\Channel;

final class MetaDocumentStrategy extends MetaMessageStrategy
{
    /** @param array<string, mixed> $payload */
    protected function eventType(array $payload): string
    {
        return 'document';
    }

    /** @param array<string, mixed> $payload */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->mediaContent(
            $channel,
            $payload,
            'document',
            $this->stringValue($this->message($payload), 'document.caption'),
        );
    }
}
