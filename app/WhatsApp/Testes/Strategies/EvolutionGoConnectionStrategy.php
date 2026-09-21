<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Enums\ChannelStatus;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Support\Str;

final class EvolutionGoConnectionStrategy extends EvolutionGoMessageStrategy
{
    /**
     * Reflect the pairing session's connection state on the Channel.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message
    {
        if ($this->connected($payload)) {
            $channel->update(['status' => ChannelStatus::Connected, 'last_error' => null]);
            $channel->loadMissing('whatsappTestConnection');
            $channel->whatsappTestConnection?->update(['qrcode' => null]);

            return null;
        }

        if ($this->loggedOut($payload)) {
            $channel->update([
                'status' => ChannelStatus::Error,
                'last_error' => 'O WhatsApp de testes desconectou. Remova esta integração e crie uma nova para reconectar.',
            ]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function connected(array $payload): bool
    {
        if (data_get($payload, 'data.connected') === true) {
            return true;
        }

        return $this->stateContains($payload, ['open', 'connected', 'pairsuccess']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function loggedOut(array $payload): bool
    {
        return $this->stateContains($payload, ['loggedout', 'close', 'closed']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stateContains(array $payload, array $needles): bool
    {
        foreach (['data.state', 'data.type', 'data.status'] as $path) {
            $value = data_get($payload, $path);

            if (is_string($value) && in_array(Str::lower($value), $needles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): string
    {
        return 'connection';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function content(Channel $channel, array $payload): ?string
    {
        return null;
    }
}
