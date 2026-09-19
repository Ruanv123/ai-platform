<?php

namespace App\WhatsApp\Testes\Strategies;

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
    protected function content(array $payload): ?string
    {
        return $this->textContent($payload);
    }
}
