<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\WhatsApp\Testes\EvolutionGoEventProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppTestWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 172800;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $channelId,
        public array $payload,
    ) {}

    public function handle(EvolutionGoEventProcessor $processor): void
    {
        $channel = Channel::query()
            ->whereKey($this->channelId)
            ->where('type', ChannelType::WhatsAppTest)
            ->first();

        if ($channel === null) {
            return;
        }

        $processor->process($channel, $this->payload);
    }

    public function uniqueId(): string
    {
        $providerMessageId = data_get($this->payload, 'data.key.id')
            ?? data_get($this->payload, 'data.message.key.id')
            ?? data_get($this->payload, 'data.messageId')
            ?? data_get($this->payload, 'data.message_id')
            ?? data_get($this->payload, 'data.id');

        $identity = is_scalar($providerMessageId) && filled($providerMessageId)
            ? (string) $providerMessageId
            : hash('sha256', json_encode($this->payload, JSON_THROW_ON_ERROR));

        $event = is_scalar($this->payload['event'] ?? null)
            ? (string) $this->payload['event']
            : 'unknown';

        return $this->channelId.':'.$event.':'.$identity;
    }
}
