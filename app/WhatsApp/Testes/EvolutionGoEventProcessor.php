<?php

namespace App\WhatsApp\Testes;

use App\Enums\MessageDirection;
use App\Models\Channel;
use App\WhatsApp\IncomingMessageResponseDispatcher;
use App\WhatsApp\Media\IncomingMediaResolver;
use App\WhatsApp\Testes\Strategies\EvolutionGoAudioStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoDocumentStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoImageStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoReadReceiptStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoSendMessageStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoTextStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoUnsupportedStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoVideoStrategy;
use Illuminate\Support\Str;

final class EvolutionGoEventProcessor
{
    public function __construct(
        private readonly IncomingMediaResolver $mediaResolver,
        private readonly EvolutionGoMediaDownloader $mediaDownloader,
        private readonly IncomingMessageResponseDispatcher $responseDispatcher,
    ) {}

    /**
     * Process an Evolution Go envelope without translating it to a canonical event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function process(Channel $channel, array $payload): void
    {
        $event = is_scalar($payload['event'] ?? null)
            ? Str::upper((string) $payload['event'])
            : '';
        $strategy = match ($event) {
            'MESSAGE' => $this->messageStrategy($payload),
            'SEND_MESSAGE' => new EvolutionGoSendMessageStrategy($this->mediaResolver, $this->mediaDownloader),
            'READ_RECEIPT' => new EvolutionGoReadReceiptStrategy($this->mediaResolver, $this->mediaDownloader),
            default => new EvolutionGoUnsupportedStrategy($this->mediaResolver, $this->mediaDownloader),
        };

        $message = $strategy->handle($channel, $payload);

        if ($message?->direction === MessageDirection::Incoming) {
            $this->responseDispatcher->dispatch($channel, $message);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function messageStrategy(array $payload): EvolutionGoStrategy
    {
        $messageType = (new EvolutionGoTextStrategy($this->mediaResolver, $this->mediaDownloader))
            ->detectedMessageType($payload);

        return match ($messageType) {
            'text' => new EvolutionGoTextStrategy($this->mediaResolver, $this->mediaDownloader),
            'image' => new EvolutionGoImageStrategy($this->mediaResolver, $this->mediaDownloader),
            'audio' => new EvolutionGoAudioStrategy($this->mediaResolver, $this->mediaDownloader),
            'video' => new EvolutionGoVideoStrategy($this->mediaResolver, $this->mediaDownloader),
            'document' => new EvolutionGoDocumentStrategy($this->mediaResolver, $this->mediaDownloader),
            default => new EvolutionGoUnsupportedStrategy($this->mediaResolver, $this->mediaDownloader),
        };
    }
}
