<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;

final class EvolutionGoDocumentStrategy extends EvolutionGoMessageStrategy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): string
    {
        return 'document';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->mediaContent($channel, $payload, 'document', $this->textContent($payload));
    }
}
