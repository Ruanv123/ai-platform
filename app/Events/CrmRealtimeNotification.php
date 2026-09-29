<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class CrmRealtimeNotification implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $teamId,
        public readonly int $contactId,
        public readonly string $type,
        public readonly string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('team.'.$this->teamId.'.inbox')];
    }

    public function broadcastAs(): string
    {
        return 'crm.notification';
    }

    /** @return array{teamId: int, contactId: int, type: string, message: string} */
    public function broadcastWith(): array
    {
        return [
            'teamId' => $this->teamId,
            'contactId' => $this->contactId,
            'type' => $this->type,
            'message' => $this->message,
        ];
    }
}
