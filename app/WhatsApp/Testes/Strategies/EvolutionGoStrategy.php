<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;
use App\Models\Message;

interface EvolutionGoStrategy
{
    /**
     * Process the raw Evolution Go webhook payload for a channel.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message;

    public function shouldDispatchResponse(): bool;
}
