<?php

namespace App\WhatsApp\Testes\Strategies;

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
    protected function content(array $payload): ?string
    {
        return $this->textContent($payload);
    }
}
