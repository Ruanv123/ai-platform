<?php

namespace App\WhatsApp\Testes;

use App\Enums\MessageDirection;
use App\Models\Channel;
use App\WhatsApp\IncomingMessageResponseDispatcher;
use App\WhatsApp\Media\IncomingMediaResolver;
use App\WhatsApp\Media\IncomingMediaStorage;
use App\WhatsApp\Testes\Strategies\EvolutionGoAudioStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoConnectionStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoDocumentStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoImageStrategy;
use App\WhatsApp\Testes\Strategies\EvolutionGoQrCodeStrategy;
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
        private readonly IncomingMediaStorage $mediaStorage,
        private readonly IncomingMessageResponseDispatcher $responseDispatcher,
    ) {}

    /**
     * Process a native Evolution Go webhook body.
     *
     * @param  array<string, mixed>  $payload
     */
    public function process(Channel $channel, array $payload): void
    {
        $strategy = match (Str::lower((string) ($payload['event'] ?? ''))) {
            'message' => $this->messageStrategy($payload),
            'sendmessage' => new EvolutionGoSendMessageStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'receipt' => new EvolutionGoReadReceiptStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'connected', 'pairsuccess', 'loggedout' => new EvolutionGoConnectionStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'qrcode' => new EvolutionGoQrCodeStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            default => new EvolutionGoUnsupportedStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
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
        if ($this->isSentToAnotherChat($payload)) {
            return new EvolutionGoSendMessageStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage);
        }

        $messageType = (new EvolutionGoTextStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage))
            ->detectedMessageType($payload);

        return match ($messageType) {
            'text' => new EvolutionGoTextStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'image' => new EvolutionGoImageStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'audio' => new EvolutionGoAudioStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'video' => new EvolutionGoVideoStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'document' => new EvolutionGoDocumentStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            default => new EvolutionGoUnsupportedStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
        };
    }

    /**
     * Messages typed on the paired phone reach us as `Message` events with `IsFromMe`. In the account's own
     * conversation (the chat is the sender itself) they are the user talking to the Agent; anywhere else they
     * are outgoing history. Messages sent through the API arrive as `SendMessage` events instead.
     *
     * @param  array<string, mixed>  $payload
     */
    private function isSentToAnotherChat(array $payload): bool
    {
        $info = data_get($payload, 'data.Info');

        if (! is_array($info) || ($info['IsFromMe'] ?? false) !== true) {
            return false;
        }

        return $this->jidUser((string) ($info['Chat'] ?? '')) !== $this->jidUser((string) ($info['Sender'] ?? ''));
    }

    /**
     * Get the user part of a JID without its server or device suffix.
     */
    private function jidUser(string $jid): string
    {
        return Str::before(Str::before($jid, '@'), ':');
    }
}
