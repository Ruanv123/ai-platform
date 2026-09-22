<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class HumanConversationUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $teamId,
        public readonly ?int $contactId,
        public readonly string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('team.'.$this->teamId.'.inbox')];
    }

    public function broadcastAs(): string
    {
        return 'human-conversation.updated';
    }

    /** @return array<string, int|string|null> */
    public function broadcastWith(): array
    {
        return [
            'teamId' => $this->teamId,
            'contactId' => $this->contactId,
            'action' => $this->action,
        ];
    }
}
