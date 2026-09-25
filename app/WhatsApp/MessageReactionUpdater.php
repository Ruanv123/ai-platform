<?php

namespace App\WhatsApp;

use App\Events\HumanConversationUpdated;
use App\Models\Channel;
use App\Models\Message;

final class MessageReactionUpdater
{
    public function update(Channel $channel, string $providerMessageId, string $emoji): void
    {
        $message = Message::query()
            ->where('team_id', $channel->team_id)
            ->where('channel_id', $channel->getKey())
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($message === null) {
            return;
        }

        $message->update([
            'reaction_emoji' => $emoji === '' ? null : $emoji,
            'reacted_at' => $emoji === '' ? null : now(),
        ]);

        HumanConversationUpdated::dispatch(
            (int) $message->team_id,
            (int) $message->contact_id,
            (string) $message->channel_id,
            'message',
        );
    }
}
