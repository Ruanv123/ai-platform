<?php

namespace App\WhatsApp\Oficial;

use App\Models\Channel;
use App\WhatsApp\Oficial\Strategies\MetaAudioStrategy;
use App\WhatsApp\Oficial\Strategies\MetaDocumentStrategy;
use App\WhatsApp\Oficial\Strategies\MetaImageStrategy;
use App\WhatsApp\Oficial\Strategies\MetaStatusStrategy;
use App\WhatsApp\Oficial\Strategies\MetaStrategy;
use App\WhatsApp\Oficial\Strategies\MetaTextStrategy;
use App\WhatsApp\Oficial\Strategies\MetaUnsupportedStrategy;
use App\WhatsApp\Oficial\Strategies\MetaVideoStrategy;

final class MetaEventProcessor
{
    /**
     * Process a Meta event without translating it to a canonical event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function process(Channel $channel, array $payload): void
    {
        $this->strategy($payload)->handle($channel, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function strategy(array $payload): MetaStrategy
    {
        if (is_array(data_get($payload, 'entry.0.changes.0.value.statuses.0'))) {
            return new MetaStatusStrategy;
        }

        return match (data_get($payload, 'entry.0.changes.0.value.messages.0.type')) {
            'text' => new MetaTextStrategy,
            'image' => new MetaImageStrategy,
            'audio' => new MetaAudioStrategy,
            'video' => new MetaVideoStrategy,
            'document' => new MetaDocumentStrategy,
            default => new MetaUnsupportedStrategy,
        };
    }
}
