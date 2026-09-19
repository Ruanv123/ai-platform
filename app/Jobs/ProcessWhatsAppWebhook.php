<?php

namespace App\Jobs;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\WhatsApp\Oficial\MetaEventProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWhatsAppWebhook implements ShouldBeUnique, ShouldQueue
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

    public function handle(MetaEventProcessor $processor): void
    {
        $channel = Channel::query()
            ->whereKey($this->channelId)
            ->where('type', ChannelType::WhatsApp)
            ->first();

        if ($channel === null) {
            return;
        }

        $processor->process($channel, $this->payload);
    }

    public function uniqueId(): string
    {
        $providerMessageId = data_get($this->payload, 'entry.0.changes.0.value.messages.0.id')
            ?? data_get($this->payload, 'entry.0.changes.0.value.statuses.0.id');
        $identity = is_scalar($providerMessageId) && filled($providerMessageId)
            ? (string) $providerMessageId
            : hash('sha256', json_encode($this->payload, JSON_THROW_ON_ERROR));

        return $this->channelId.':'.$identity;
    }
}
