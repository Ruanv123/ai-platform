<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Support\Str;

final class EvolutionGoReadReceiptStrategy extends EvolutionGoMessageStrategy
{
    /**
     * Mark the outgoing messages the Contact read or played, as named by the provider receipt.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message
    {
        $messageIds = collect((array) data_get($payload, 'data.MessageIDs'))
            ->filter(fn (mixed $id): bool => is_string($id) && filled($id))
            ->values();

        if ($messageIds->isEmpty() || ! in_array(Str::lower((string) data_get($payload, 'data.Type')), ['read', 'played'], true)) {
            return null;
        }

        Message::query()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Outgoing)
            ->whereIn('provider_message_id', $messageIds)
            ->update([
                'delivery_status' => MessageDeliveryStatus::Read,
            ]);

        return null;
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
    protected function content(Channel $channel, array $payload): ?string
    {
        return null;
    }
}
