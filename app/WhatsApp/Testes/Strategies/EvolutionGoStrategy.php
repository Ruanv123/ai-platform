<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;

interface EvolutionGoStrategy
{
    /**
     * Process the raw Evolution Go webhook payload for a channel.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): void;
}
