<?php

namespace App\Console\Commands;

use App\Inbox\AgentConversationService;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

final class ResumeExpiredAgentConversations extends Command
{
    protected $signature = 'agents:resume-expired';

    protected $description = 'Retoma conversas pausadas cujo prazo de intervenção terminou.';

    public function handle(AgentConversationService $agentConversations): int
    {
        AgentConversationState::withoutGlobalScopes()
            ->where('is_paused', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function (Collection $states) use ($agentConversations): void {
                foreach ($states as $state) {
                    $contact = Contact::withoutGlobalScopes()->find($state->contact_id);
                    $channel = Channel::withoutGlobalScopes()->find($state->channel_id);

                    if ($contact !== null && $channel !== null && $contact->team_id === $state->team_id) {
                        $agentConversations->returnExpired($contact, $channel);
                    }
                }
            });

        return self::SUCCESS;
    }
}
