<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;

final class EvolutionGoImageStrategy extends EvolutionGoMessageStrategy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): string
    {
        return 'image';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->mediaContent($channel, $payload, 'image', $this->textContent($payload));
    }
}
