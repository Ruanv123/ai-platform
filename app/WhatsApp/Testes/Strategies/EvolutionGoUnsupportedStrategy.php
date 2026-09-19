<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;
use Illuminate\Support\Str;

final class EvolutionGoUnsupportedStrategy extends EvolutionGoMessageStrategy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): string
    {
        return Str::substr(
            $this->rawMessageType($payload) ?? $this->rawEventType($payload),
            0,
            64,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->textContent($payload);
    }
}
