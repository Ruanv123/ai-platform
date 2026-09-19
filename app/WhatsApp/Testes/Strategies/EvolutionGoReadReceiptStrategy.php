<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Message;

final class EvolutionGoReadReceiptStrategy extends EvolutionGoMessageStrategy
{
    /**
     * Update the outgoing message named by the provider receipt.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): void
    {
        $providerMessageId = $this->providerMessageId($payload);

        if ($providerMessageId === null) {
            return;
        }

        Message::query()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Outgoing)
            ->where('provider_message_id', $providerMessageId)
            ->update([
                'delivery_status' => MessageDeliveryStatus::Read,
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): string
    {
        return 'read_receipt';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function content(array $payload): ?string
    {
        return null;
    }
}
