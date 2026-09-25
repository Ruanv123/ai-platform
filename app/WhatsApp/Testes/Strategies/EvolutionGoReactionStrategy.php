<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Models\Channel;
use App\Models\Message;
use App\WhatsApp\MessageReactionUpdater;

final class EvolutionGoReactionStrategy implements EvolutionGoStrategy
{
    public function __construct(private readonly MessageReactionUpdater $reactionUpdater) {}

    public function handle(Channel $channel, array $payload): ?Message
    {
        $reaction = data_get($payload, 'data.Message.reactionMessage');

        if (! is_array($reaction)) {
            return null;
        }

        $providerMessageId = data_get($reaction, 'key.id');
        $emoji = $reaction['text'] ?? null;

        if (! is_string($providerMessageId)
            || $providerMessageId === ''
            || ! is_string($emoji)) {
            return null;
        }

        $this->reactionUpdater->update($channel, $providerMessageId, $emoji);

        return null;
    }
}
