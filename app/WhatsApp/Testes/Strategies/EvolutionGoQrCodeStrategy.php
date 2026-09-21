<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;
use App\Models\Message;

final class EvolutionGoQrCodeStrategy extends EvolutionGoMessageStrategy
{
    /**
     * Store the latest QR code pushed for the Channel's pairing session.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message
    {
        $qrcode = $this->qrcode($payload);

        if ($qrcode === null) {
            return null;
        }

        $channel->loadMissing('whatsappTestConnection');
        $channel->whatsappTestConnection?->update(['qrcode' => $qrcode]);

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function qrcode(array $payload): ?string
    {
        foreach (['data.qrcode', 'data.qrCode', 'data.Qrcode', 'data.base64', 'data.code'] as $path) {
            $value = data_get($payload, $path);

            if (is_string($value) && filled($value)) {
                return $value;
            }
        }

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
