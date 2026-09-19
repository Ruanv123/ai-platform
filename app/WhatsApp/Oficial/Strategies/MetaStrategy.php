<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Models\Channel;

interface MetaStrategy
{
    /**
     * Process the raw Meta webhook payload for a channel.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): void;
}
