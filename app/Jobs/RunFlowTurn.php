<?php

namespace App\Jobs;

use App\Flows\FlowEngine;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RunFlowTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public string $channelId, public int $contactId) {}

    /** @return array<int, WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("flow:{$this->channelId}:{$this->contactId}"))
                ->releaseAfter(1)
                ->expireAfter(120),
        ];
    }

    public function handle(FlowEngine $engine): void
    {
        $channel = Channel::withoutGlobalScopes()->find($this->channelId);
        if ($channel === null) {
            return;
        }

        Message::withoutGlobalScopes()
            ->where('channel_id', $channel->getKey())
            ->where('contact_id', $this->contactId)
            ->where('direction', 'incoming')
            ->where('is_responded', false)
            ->oldest('created_at')
            ->oldest('id')
            ->get()
            ->each(function (Message $message) use ($engine, $channel): void {
                $engine->handleMessage($channel, $message);
            });
    }
}
