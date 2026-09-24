<?php

namespace App\Inbox;

use App\Events\HumanConversationUpdated;
use App\Models\AgentConversationAudit;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AgentConversationService
{
    public function canPause(Contact $contact, Channel $channel): bool
    {
        if ($contact->team_id !== $channel->team_id || $channel->agent_id === null) {
            return false;
        }

        return ! Flow::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('channel_id', $channel->getKey())
            ->where('trigger_type', 'message')
            ->whereIn('status', ['published', 'paused'])
            ->where('is_trivial', false)
            ->exists();
    }

    public function stateFor(Contact $contact, Channel $channel): ?AgentConversationState
    {
        return AgentConversationState::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->with('assignedUser')
            ->first();
    }

    public function isPaused(Channel $channel, int $contactId): bool
    {
        return AgentConversationState::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('contact_id', $contactId)
            ->where('channel_id', $channel->getKey())
            ->where('is_paused', true)
            ->exists();
    }

    public function runIfUnpaused(Channel $channel, Contact $contact, callable $action): bool
    {
        return DB::transaction(function () use ($channel, $contact, $action): bool {
            $state = $this->lockState($contact, $channel);
            if ($state->is_paused) {
                return false;
            }

            $action();

            return true;
        });
    }

    public function pauseAndAssign(Contact $contact, Channel $channel, User $user): AgentConversationState
    {
        return DB::transaction(function () use ($contact, $channel, $user): AgentConversationState {
            $this->ensureDirectAgentConversation($contact, $channel, $user);
            $state = $this->lockState($contact, $channel);
            $previousAssigneeId = $state->assigned_user_id;

            if ($state->is_paused && $previousAssigneeId !== null && $previousAssigneeId !== $user->getKey()) {
                throw new ConflictHttpException('Esta conversa está pausada e atribuída a outra pessoa da Equipe.');
            }

            if (! $state->is_paused) {
                $team = Team::withoutGlobalScopes()->findOrFail($channel->team_id);
                $durationMinutes = (int) $team->agent_pause_duration_minutes;
                $now = now();
                $state->forceFill([
                    'is_paused' => true,
                    'assigned_user_id' => $user->getKey(),
                    'paused_at' => $now,
                    'expires_at' => $now->copy()->addMinutes($durationMinutes),
                    'pause_duration_minutes' => $durationMinutes,
                    'last_activity_at' => $now,
                ])->save();

                $this->recordAudit($state, null, $user->getKey(), $user->getKey(), 'paused');
                HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'paused');
            } elseif ($previousAssigneeId === null) {
                $state->forceFill([
                    'assigned_user_id' => $user->getKey(),
                    'last_activity_at' => now(),
                ])->save();
                $this->recordAudit($state, null, $user->getKey(), $user->getKey(), 'assigned');
                HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'assigned');
            }

            return $state->fresh('assignedUser');
        });
    }

    public function takeOver(Contact $contact, Channel $channel, User $user): AgentConversationState
    {
        return DB::transaction(function () use ($contact, $channel, $user): AgentConversationState {
            $this->ensureDirectAgentConversation($contact, $channel, $user);
            $state = $this->lockState($contact, $channel);

            if (! $state->is_paused) {
                throw new ConflictHttpException('Pause o Agente antes de assumir esta conversa.');
            }

            if ($state->assigned_user_id === $user->getKey()) {
                return $state->fresh('assignedUser');
            }

            $previousAssigneeId = $state->assigned_user_id;
            $state->forceFill([
                'assigned_user_id' => $user->getKey(),
                'last_activity_at' => now(),
            ])->save();

            $this->recordAudit($state, $previousAssigneeId, $user->getKey(), $user->getKey(), 'taken_over');
            HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'taken_over');

            return $state->fresh('assignedUser');
        });
    }

    private function ensureDirectAgentConversation(Contact $contact, Channel $channel, User $user): void
    {
        abort_unless($contact->team_id === $channel->team_id && $user->belongsToTeam(Team::withoutGlobalScopes()->findOrFail($channel->team_id)), 404);

        if (! $this->canPause($contact, $channel)) {
            throw new ConflictHttpException('Esta conversa é conduzida por um Fluxo e não pode ser pausada pelo controle do Agente.');
        }
    }

    private function lockState(Contact $contact, Channel $channel): AgentConversationState
    {
        $now = now();

        DB::table('agent_conversation_states')->insertOrIgnore([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'is_paused' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return AgentConversationState::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function recordAudit(
        AgentConversationState $state,
        ?int $previousUserId,
        ?int $newUserId,
        ?int $actorUserId,
        string $action,
    ): void {
        AgentConversationAudit::withoutGlobalScopes()->create([
            'team_id' => $state->team_id,
            'contact_id' => $state->contact_id,
            'channel_id' => $state->channel_id,
            'previous_user_id' => $previousUserId,
            'new_user_id' => $newUserId,
            'actor_user_id' => $actorUserId,
            'action' => $action,
        ]);
    }
}
