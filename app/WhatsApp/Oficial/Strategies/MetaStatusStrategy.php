<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Message;

final class MetaStatusStrategy implements MetaStrategy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message
    {
        $statusData = data_get($payload, 'entry.0.changes.0.value.statuses.0');

        if (! is_array($statusData)) {
            return null;
        }

        $providerMessageId = $statusData['id'] ?? null;
        $deliveryStatus = $this->deliveryStatus($statusData['status'] ?? null);

        if (! is_scalar($providerMessageId) || blank($providerMessageId) || $deliveryStatus === null) {
            return null;
        }

        Message::query()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Outgoing)
            ->where('provider_message_id', (string) $providerMessageId)
            ->update([
                'delivery_status' => $deliveryStatus,
            ]);

        return null;
    }

    private function deliveryStatus(mixed $status): ?MessageDeliveryStatus
    {
        if (! is_string($status)) {
            return null;
        }

        return MessageDeliveryStatus::tryFrom($status);
    }
}
