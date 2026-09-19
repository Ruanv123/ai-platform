<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use Illuminate\Support\Str;

final class EvolutionGoSendMessageStrategy extends EvolutionGoMessageStrategy
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function eventType(array $payload): string
    {
        return $this->detectedMessageType($payload)
            ?? Str::substr($this->rawMessageType($payload) ?? $this->rawEventType($payload), 0, 64);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function content(Channel $channel, array $payload): ?string
    {
        return $this->textContent($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function direction(array $payload): MessageDirection
    {
        return MessageDirection::Outgoing;
    }

    protected function deliveryStatus(): MessageDeliveryStatus
    {
        return MessageDeliveryStatus::Sent;
    }
}
