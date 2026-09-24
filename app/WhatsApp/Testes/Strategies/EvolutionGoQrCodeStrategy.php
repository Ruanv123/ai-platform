<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;
use App\Models\Message;

final class EvolutionGoQrCodeStrategy extends EvolutionGoMessageStrategy
{
    /**
     * QR codes rotate every few seconds, so they are read live from Evolution Go instead of being stored.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): string
    {
        return 'qrcode';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function content(Channel $channel, array $payload): ?string
    {
        return null;
    }
}
