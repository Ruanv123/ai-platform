<?php

namespace App\WhatsApp\Testes;

use App\Models\Channel;
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
            'SEND_MESSAGE' => new EvolutionGoSendMessageStrategy,
            'READ_RECEIPT' => new EvolutionGoReadReceiptStrategy,
            default => new EvolutionGoUnsupportedStrategy,
        };

        $strategy->handle($channel, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function messageStrategy(array $payload): EvolutionGoStrategy
    {
        $messageType = (new EvolutionGoTextStrategy)->detectedMessageType($payload);

        return match ($messageType) {
            'text' => new EvolutionGoTextStrategy,
            'image' => new EvolutionGoImageStrategy,
            'audio' => new EvolutionGoAudioStrategy,
            'video' => new EvolutionGoVideoStrategy,
            'document' => new EvolutionGoDocumentStrategy,
            default => new EvolutionGoUnsupportedStrategy,
        };
    }
}
