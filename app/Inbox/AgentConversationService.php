<?php

namespace App\Inbox;

use App\Enums\ChannelStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Events\HumanConversationUpdated;
use App\Models\AgentConversationAudit;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Flow;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use App\WhatsApp\WhatsAppCustomerCareWindow;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class AgentConversationService
{
    public function __construct(
        private readonly WhatsAppOutputAdapterFactory $outputAdapterFactory,
        private readonly WhatsAppCustomerCareWindow $customerCareWindow,
    ) {}

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

    /**
     * @param  array{type?: string, content?: string, template?: string, parameters?: list<string>}  $data
     */
    public function send(Contact $contact, Channel $channel, User $user, array $data): Message
    {
        $this->ensureDirectAgentConversation($contact, $channel, $user);
        if ($channel->status !== ChannelStatus::Connected) {
            throw new ConflictHttpException('Canal indisponível. Reconecte o Canal antes de enviar uma Mensagem.');
        }

        $type = (string) ($data['type'] ?? 'text');
        $content = trim((string) ($data['content'] ?? ''));
        $template = trim((string) ($data['template'] ?? ''));
        $parameters = $data['parameters'] ?? [];

        DB::transaction(function () use ($contact, $channel, $user): void {
            $state = $this->lockState($contact, $channel);
            if ($state->is_paused && $state->assigned_user_id !== $user->getKey()) {
                throw new ConflictHttpException('Assuma explicitamente esta conversa antes de enviar uma Mensagem.');
            }

            if (! $state->is_paused) {
                $this->pauseLockedState($state, $channel, $user);
            }
        });

        return DB::transaction(function () use ($contact, $channel, $user, $type, $content, $template, $parameters): Message {
            $state = $this->lockState($contact, $channel);
            if (! $state->is_paused || $state->assigned_user_id !== $user->getKey()) {
                throw new ConflictHttpException('A responsabilidade da conversa mudou. Atualize o atendimento e tente novamente.');
            }

            if ($type === 'text' && $this->customerCareWindow->hasExpired($channel, $this->lastIncomingMessage($contact, $channel))) {
                return $this->recordFailedMessage(
                    $channel,
                    $contact,
                    $user,
                    $type,
                    $content,
                );
            }

            try {
                $adapter = $this->outputAdapterFactory->make($channel);
                $message = $type === 'template'
                    ? $adapter->sendTemplate($channel, $contact, $template, $parameters)
                    : $adapter->sendText($channel, $contact, $content);
            } catch (Throwable $exception) {
                report($exception);

                return $this->recordFailedMessage(
                    $channel,
                    $contact,
                    $user,
                    $type,
                    $type === 'template' ? $this->templateContent($template, $parameters) : $content,
                );
            }

            $message->forceFill(['sent_by_user_id' => $user->getKey()])->save();
            $state->forceFill([
                'last_activity_at' => now(),
                'expires_at' => now()->addMinutes((int) $state->pause_duration_minutes),
            ])->save();
            $this->markPendingIncomingMessagesAsResponded($contact, $channel);
            HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'message');

            return $message;
        });
    }

    private function pauseLockedState(AgentConversationState $state, Channel $channel, User $user): void
    {
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
    }

    private function lastIncomingMessage(Contact $contact, Channel $channel): ?Message
    {
        return $contact->messages()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Incoming)
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    private function recordFailedMessage(
        Channel $channel,
        Contact $contact,
        User $user,
        string $type,
        string $content,
    ): Message {
        $message = Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => $type === 'template' ? 'template' : 'text',
            'provider_message_id' => null,
            'sent_by_user_id' => $user->getKey(),
            'content' => $content,
            'delivery_status' => MessageDeliveryStatus::Failed,
        ]);

        HumanConversationUpdated::dispatch($channel->team_id, $contact->getKey(), (string) $channel->getKey(), 'failed');

        return $message;
    }

    /** @param list<string> $parameters */
    private function templateContent(string $template, array $parameters): string
    {
        return json_encode([
            'template' => $template,
            'parameters' => $parameters,
        ], JSON_THROW_ON_ERROR);
    }

    private function markPendingIncomingMessagesAsResponded(Contact $contact, Channel $channel): void
    {
        $contact->messages()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Incoming)
            ->where('is_responded', false)
            ->update(['is_responded' => true]);
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
