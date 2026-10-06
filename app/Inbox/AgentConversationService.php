<?php

namespace App\Inbox;

use App\Enums\ChannelStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Events\HumanConversationUpdated;
use App\Jobs\RespondToWhatsAppMessage;
use App\Models\AgentConversationAudit;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Team;
use App\Models\User;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use App\WhatsApp\Output\WhatsAppTemplateMediaOutputAdapter;
use App\WhatsApp\WhatsAppCustomerCareWindow;
use Illuminate\Http\UploadedFile;
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

        return true;
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

    public function recordActivityWhilePaused(Contact $contact, Channel $channel, Message $incomingMessage): bool
    {
        $returnAction = null;
        $wasPaused = DB::transaction(function () use ($contact, $channel, $incomingMessage, &$returnAction): bool {
            $state = $this->lockExistingState($contact, $channel);
            if ($state === null || ! $state->is_paused) {
                return false;
            }

            $this->renewPause($state);

            if ($state->return_requested_at !== null
                && $state->return_started_at === null
                && $state->return_attempted_message_id !== $incomingMessage->getKey()) {
                $returnAction = $state->return_action ?? 'returned';
                $state->forceFill([
                    'return_requested_at' => now(),
                    'return_attempted_message_id' => $incomingMessage->getKey(),
                ])->save();
            }

            HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'activity');

            return true;
        });

        if ($wasPaused && $returnAction !== null) {
            RespondToWhatsAppMessage::dispatch(
                $channel->getKey(),
                (int) $contact->getKey(),
                returnFromPause: true,
                returnAction: $returnAction,
            );
        }

        return $wasPaused;
    }

    public function returnToAgent(Contact $contact, Channel $channel, User $user): void
    {
        $this->requestReturn($contact, $channel, $user, 'returned');
    }

    public function returnExpired(Contact $contact, Channel $channel): void
    {
        $this->requestReturn($contact, $channel, null, 'expired');
    }

    public function beginReturnAttempt(Channel $channel, Contact $contact, Message $pendingMessage): bool
    {
        return DB::transaction(function () use ($channel, $contact, $pendingMessage): bool {
            $state = $this->lockExistingState($contact, $channel);
            if ($state === null
                || ! $state->is_paused
                || $state->return_requested_at === null
                || $state->return_started_at !== null
                || $state->return_attempted_message_id !== $pendingMessage->getKey()) {
                return false;
            }

            $state->forceFill(['return_started_at' => now()])->save();

            return true;
        });
    }

    public function completeReturnWithoutResponse(Channel $channel, Contact $contact): void
    {
        DB::transaction(function () use ($channel, $contact): void {
            $state = $this->lockExistingState($contact, $channel);
            if ($state === null || ! $state->is_paused || $state->return_requested_at === null) {
                return;
            }

            $pendingMessage = $this->latestPendingIncomingMessage($contact, $channel);
            if ($pendingMessage !== null && ! $this->hasSuccessfulReplyAfter($contact, $channel, $pendingMessage)) {
                return;
            }

            $this->markPendingIncomingMessagesAsResponded($contact, $channel);
            $this->clearPause($state, $state->return_requested_by_user_id, $state->return_action ?? 'returned');
        });
    }

    public function completeReturnResponse(
        Channel $channel,
        Contact $contact,
        Message $pendingMessage,
        string $content,
    ): bool {
        $retryWithLatestMessage = false;
        $completed = DB::transaction(function () use ($channel, $contact, $pendingMessage, $content, &$retryWithLatestMessage): bool {
            $state = $this->lockExistingState($contact, $channel);
            if ($state === null
                || ! $state->is_paused
                || $state->return_started_at === null
                || $state->return_attempted_message_id !== $pendingMessage->getKey()) {
                return false;
            }

            $latestPendingMessage = $this->latestPendingIncomingMessage($contact, $channel);
            if ($latestPendingMessage === null) {
                $this->clearPause($state, $state->return_requested_by_user_id, $state->return_action ?? 'returned');

                return true;
            }

            if ($latestPendingMessage->isNot($pendingMessage)) {
                $state->forceFill([
                    'return_attempted_message_id' => $latestPendingMessage->getKey(),
                    'return_started_at' => null,
                    'return_requested_at' => now(),
                ])->save();
                $retryWithLatestMessage = true;

                return false;
            }

            if ($this->hasSuccessfulReplyAfter($contact, $channel, $latestPendingMessage)) {
                $this->markPendingIncomingMessagesAsResponded($contact, $channel);
                $this->clearPause($state, $state->return_requested_by_user_id, $state->return_action ?? 'returned');

                return true;
            }

            if ($this->customerCareWindow->hasExpired($channel, $latestPendingMessage)) {
                $this->recordFailedAgentResponse($channel, $contact, $content, 'customer_care_window_closed');
                $state->forceFill(['return_started_at' => null])->save();
                HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'failed');

                return false;
            }

            try {
                $this->outputAdapterFactory->make($channel)->sendText($channel, $contact, $content);
            } catch (Throwable $exception) {
                report($exception);
                $this->recordFailedAgentResponse($channel, $contact, $content, 'provider_error');
                $state->forceFill(['return_started_at' => null])->save();
                HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'failed');

                return false;
            }

            $this->markPendingIncomingMessagesAsResponded($contact, $channel);
            $this->clearPause($state, $state->return_requested_by_user_id, $state->return_action ?? 'returned');

            return true;
        });

        if ($retryWithLatestMessage) {
            RespondToWhatsAppMessage::dispatch(
                $channel->getKey(),
                (int) $contact->getKey(),
                returnFromPause: true,
            );
        }

        return $completed;
    }

    public function recordFailedReturnAttempt(
        string|int $channelId,
        int $contactId,
        ?int $pendingMessageId,
        ?Throwable $exception,
    ): void {
        $channel = Channel::withoutGlobalScopes()->find($channelId);
        $contact = $channel === null
            ? null
            : Contact::withoutGlobalScopes()->whereKey($contactId)->where('team_id', $channel->team_id)->first();
        if ($channel === null || $contact === null || $pendingMessageId === null) {
            return;
        }

        $retryWithLatestMessage = false;
        DB::transaction(function () use ($channel, $contact, $pendingMessageId, &$retryWithLatestMessage): void {
            $state = $this->lockExistingState($contact, $channel);
            if ($state === null
                || ! $state->is_paused
                || $state->return_attempted_message_id !== $pendingMessageId) {
                return;
            }

            $this->recordFailedAgentResponse(
                $channel,
                $contact,
                'O Agente não conseguiu responder. A Pausa da IA e a atribuição foram mantidas.',
                'agent_response_failed',
            );
            $state->forceFill(['return_started_at' => null])->save();

            $latestPendingMessage = $this->latestPendingIncomingMessage($contact, $channel);
            if ($latestPendingMessage !== null && $latestPendingMessage->getKey() !== $pendingMessageId) {
                $state->forceFill([
                    'return_requested_at' => now(),
                    'return_attempted_message_id' => $latestPendingMessage->getKey(),
                ])->save();
                $retryWithLatestMessage = true;
            }

            HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'failed');
        });

        if ($retryWithLatestMessage) {
            RespondToWhatsAppMessage::dispatch(
                $channel->getKey(),
                (int) $contact->getKey(),
                returnFromPause: true,
            );
        }

        if ($exception !== null) {
            report($exception);
        }
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
                    'return_requested_at' => null,
                    'return_started_at' => null,
                    'return_requested_by_user_id' => null,
                    'return_attempted_message_id' => null,
                    'return_action' => null,
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
                'return_requested_at' => null,
                'return_started_at' => null,
                'return_requested_by_user_id' => null,
                'return_attempted_message_id' => null,
                'return_action' => null,
            ])->save();

            $this->recordAudit($state, $previousAssigneeId, $user->getKey(), $user->getKey(), 'taken_over');
            HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'taken_over');

            return $state->fresh('assignedUser');
        });
    }

    /**
     * @param  array{type?: string, content?: string, template?: string, parameters?: list<string>, media?: UploadedFile}  $data
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
        $media = $data['media'] ?? null;

        DB::transaction(function () use ($contact, $channel, $user): void {
            $state = $this->lockState($contact, $channel);
            if ($state->is_paused && $state->assigned_user_id !== $user->getKey()) {
                throw new ConflictHttpException('Assuma explicitamente esta conversa antes de enviar uma Mensagem.');
            }

            if (! $state->is_paused) {
                $this->pauseLockedState($state, $channel, $user);
            }
        });

        return DB::transaction(function () use ($contact, $channel, $user, $type, $content, $template, $parameters, $media): Message {
            $state = $this->lockState($contact, $channel);
            if (! $state->is_paused || $state->assigned_user_id !== $user->getKey()) {
                throw new ConflictHttpException('A responsabilidade da conversa mudou. Atualize o atendimento e tente novamente.');
            }

            if ($type !== 'template' && $this->customerCareWindow->hasExpired($channel, $this->lastIncomingMessage($contact, $channel))) {
                return $this->recordFailedMessage(
                    $channel,
                    $contact,
                    $user,
                    $type,
                    $media instanceof UploadedFile ? $media->getClientOriginalName() : $content,
                    'customer_care_window_closed',
                );
            }

            try {
                $adapter = $this->outputAdapterFactory->make($channel);
                $message = match ($type) {
                    'template' => $media instanceof UploadedFile
                        ? ($adapter instanceof WhatsAppTemplateMediaOutputAdapter
                            ? $adapter->sendTemplateWithMedia($channel, $contact, $template, $parameters, $media)
                            : throw new \InvalidArgumentException('Este Canal não oferece suporte a Templates com mídia.'))
                        : $adapter->sendTemplate($channel, $contact, $template, $parameters),
                    'image', 'document', 'video', 'audio' => $media instanceof UploadedFile
                        ? $adapter->sendMedia($channel, $contact, $media, $type)
                        : throw new \InvalidArgumentException('O arquivo enviado não foi encontrado.'),
                    default => $adapter->sendText($channel, $contact, $content),
                };
            } catch (Throwable $exception) {
                report($exception);

                return $this->recordFailedMessage(
                    $channel,
                    $contact,
                    $user,
                    $type,
                    $type === 'template'
                        ? $this->templateContent($template, $parameters)
                        : ($media instanceof UploadedFile ? $media->getClientOriginalName() : $content),
                    'provider_error',
                );
            }

            $message->forceFill(['sent_by_user_id' => $user->getKey()])->save();
            $this->renewPause($state);
            $state->forceFill([
                'return_requested_at' => null,
                'return_started_at' => null,
                'return_requested_by_user_id' => null,
                'return_attempted_message_id' => null,
                'return_action' => null,
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
            'return_requested_at' => null,
            'return_started_at' => null,
            'return_requested_by_user_id' => null,
            'return_attempted_message_id' => null,
            'return_action' => null,
        ])->save();

        $this->recordAudit($state, null, $user->getKey(), $user->getKey(), 'paused');
        HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'paused');
    }

    private function renewPause(AgentConversationState $state): void
    {
        $now = now();
        $durationMinutes = max(1, (int) $state->pause_duration_minutes);

        $state->forceFill([
            'last_activity_at' => $now,
            'expires_at' => $now->copy()->addMinutes($durationMinutes),
        ])->save();
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
        string $failureReason,
    ): Message {
        $message = Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => in_array($type, ['template', 'image', 'document', 'video', 'audio'], true) ? $type : 'text',
            'provider_message_id' => null,
            'sent_by_user_id' => $user->getKey(),
            'content' => $content,
            'delivery_status' => MessageDeliveryStatus::Failed,
            'failure_reason' => $failureReason,
        ]);

        HumanConversationUpdated::dispatch($channel->team_id, $contact->getKey(), (string) $channel->getKey(), 'failed');

        return $message;
    }

    private function requestReturn(Contact $contact, Channel $channel, ?User $user, string $action): void
    {
        $shouldDispatch = DB::transaction(function () use ($contact, $channel, $user, $action): bool {
            $state = $this->lockExistingState($contact, $channel);
            if ($state === null || ! $state->is_paused) {
                return false;
            }

            if ($user !== null && $state->assigned_user_id !== $user->getKey()) {
                throw new ConflictHttpException('Somente a pessoa responsável pode devolver esta conversa ao Agente.');
            }

            if ($action === 'expired' && ($state->expires_at === null || $state->expires_at->isFuture())) {
                return false;
            }

            $pendingMessage = $this->latestPendingIncomingMessage($contact, $channel);
            if ($pendingMessage === null) {
                $this->clearPause($state, $user?->getKey(), $action);

                return false;
            }

            if ($this->hasSuccessfulReplyAfter($contact, $channel, $pendingMessage)) {
                $this->markPendingIncomingMessagesAsResponded($contact, $channel);
                $this->clearPause($state, $user?->getKey(), $action);

                return false;
            }

            if ($state->return_attempted_message_id === $pendingMessage->getKey()) {
                return false;
            }

            $state->forceFill([
                'return_requested_at' => now(),
                'return_started_at' => null,
                'return_requested_by_user_id' => $user?->getKey(),
                'return_attempted_message_id' => $pendingMessage->getKey(),
                'return_action' => $action,
            ])->save();
            HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, 'return_requested');

            return true;
        });

        if ($shouldDispatch) {
            RespondToWhatsAppMessage::dispatch(
                $channel->getKey(),
                (int) $contact->getKey(),
                returnFromPause: true,
                returnAction: $action,
            );
        }
    }

    private function clearPause(AgentConversationState $state, ?int $actorUserId, string $action): void
    {
        $previousAssigneeId = $state->assigned_user_id;
        $state->forceFill([
            'is_paused' => false,
            'assigned_user_id' => null,
            'paused_at' => null,
            'expires_at' => null,
            'pause_duration_minutes' => null,
            'last_activity_at' => null,
            'return_requested_at' => null,
            'return_started_at' => null,
            'return_requested_by_user_id' => null,
            'return_attempted_message_id' => null,
            'return_action' => null,
        ])->save();

        $this->recordAudit($state, $previousAssigneeId, null, $actorUserId, $action);
        HumanConversationUpdated::dispatch($state->team_id, $state->contact_id, $state->channel_id, $action);
    }

    private function recordFailedAgentResponse(
        Channel $channel,
        Contact $contact,
        string $content,
        string $failureReason,
    ): Message {
        return Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => 'text',
            'provider_message_id' => null,
            'content' => $content,
            'delivery_status' => MessageDeliveryStatus::Failed,
            'failure_reason' => $failureReason,
        ]);
    }

    private function latestPendingIncomingMessage(Contact $contact, Channel $channel): ?Message
    {
        return $contact->messages()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Incoming)
            ->where('is_responded', false)
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    private function hasSuccessfulReplyAfter(Contact $contact, Channel $channel, Message $incoming): bool
    {
        $outgoing = $contact->messages()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Outgoing)
            ->whereIn('delivery_status', [
                MessageDeliveryStatus::Sent,
                MessageDeliveryStatus::Delivered,
                MessageDeliveryStatus::Read,
            ])
            ->latest('created_at')
            ->latest('id')
            ->first();

        if ($outgoing === null || $outgoing->created_at === null || $incoming->created_at === null) {
            return false;
        }

        return $outgoing->created_at->greaterThan($incoming->created_at)
            || ($outgoing->created_at->equalTo($incoming->created_at) && $outgoing->getKey() > $incoming->getKey());
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
            throw new ConflictHttpException('Esta conversa não possui um Agente vinculado.');
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

    private function lockExistingState(Contact $contact, Channel $channel): ?AgentConversationState
    {
        return AgentConversationState::withoutGlobalScopes()
            ->where('team_id', $channel->team_id)
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->lockForUpdate()
            ->first();
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
