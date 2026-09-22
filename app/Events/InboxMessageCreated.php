<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InboxMessageCreated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $teamId,
        public readonly int $contactId,
        public readonly int $messageId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('team.'.$this->teamId.'.inbox')];
    }

    public function broadcastAs(): string
    {
        return 'inbox-message.created';
    }

    /** @return array<string, int> */
    public function broadcastWith(): array
    {
        return [
            'teamId' => $this->teamId,
            'contactId' => $this->contactId,
            'messageId' => $this->messageId,
        ];
    }
}
