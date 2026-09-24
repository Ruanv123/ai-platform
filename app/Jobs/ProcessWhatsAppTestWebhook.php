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
use Illuminate\Support\Str;

class ProcessWhatsAppTestWebhook implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 172800;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string|int $channelId,
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

    /**
     * Deduplicate redelivered Message and Receipt events; state events carry no identity and are never deduplicated.
     */
    public function uniqueId(): string
    {
        $identity = data_get($this->payload, 'data.Info.ID')
            ?? collect((array) data_get($this->payload, 'data.MessageIDs'))->filter()->sort()->implode(',')
            ?: null;

        $identity = is_scalar($identity) && filled($identity)
            ? (string) $identity
            : Str::uuid()->toString();

        $event = is_scalar($this->payload['event'] ?? null)
            ? (string) $this->payload['event']
            : 'unknown';

        return $this->channelId.':'.$event.':'.$identity;
    }
}
