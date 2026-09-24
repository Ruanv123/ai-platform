<?php

namespace App\WhatsApp\Oficial;

use App\Enums\MessageDirection;
use App\Models\Channel;
use App\WhatsApp\IncomingMessageResponseDispatcher;
use App\WhatsApp\Media\IncomingMediaResolver;
use App\WhatsApp\Media\IncomingMediaStorage;
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
    public function __construct(
        private readonly IncomingMediaResolver $mediaResolver,
        private readonly MetaMediaDownloader $mediaDownloader,
        private readonly IncomingMediaStorage $mediaStorage,
        private readonly IncomingMessageResponseDispatcher $responseDispatcher,
    ) {}

    /**
     * Process a Meta event without translating it to a canonical event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function process(Channel $channel, array $payload): void
    {
        $message = $this->strategy($payload)->handle($channel, $payload);

        if ($message?->direction === MessageDirection::Incoming) {
            $this->responseDispatcher->dispatch($channel, $message);
        }
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
            'text' => new MetaTextStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'image' => new MetaImageStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'audio' => new MetaAudioStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'video' => new MetaVideoStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'document' => new MetaDocumentStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            default => new MetaUnsupportedStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
        };
    }
}
