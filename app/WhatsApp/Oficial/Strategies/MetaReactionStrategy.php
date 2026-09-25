<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Models\Channel;
use App\Models\Message;
use App\WhatsApp\MessageReactionUpdater;

final class MetaReactionStrategy implements MetaStrategy
{
    public function __construct(private readonly MessageReactionUpdater $reactionUpdater) {}

    public function shouldDispatchResponse(): bool
    {
        return false;
    }

    public function handle(Channel $channel, array $payload): ?Message
    {
        $reaction = data_get($payload, 'entry.0.changes.0.value.messages.0.reaction');

        if (! is_array($reaction)) {
            return null;
        }

        $providerMessageId = $reaction['message_id'] ?? null;
        $emoji = $reaction['emoji'] ?? null;

        if (! is_string($providerMessageId)
            || $providerMessageId === ''
            || ! is_string($emoji)) {
            return null;
        }

        $this->reactionUpdater->update($channel, $providerMessageId, $emoji);

        return null;
    }
}
