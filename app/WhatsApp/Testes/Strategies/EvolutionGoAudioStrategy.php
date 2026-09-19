<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;

final class EvolutionGoAudioStrategy extends EvolutionGoMessageStrategy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): string
    {
        return 'audio';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->mediaContent($channel, $payload, 'audio', $this->textContent($payload));
    }
}
