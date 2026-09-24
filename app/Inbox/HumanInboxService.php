<?php

namespace App\Inbox;

use App\Enums\ChannelStatus;
use App\Enums\FlowExecutionStatus;
use App\Enums\MessageDirection;
use App\Events\HumanConversationUpdated;
use App\Flows\FlowEngine;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\FlowExecution;
use App\Models\Message;
use App\Models\User;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use App\WhatsApp\WhatsAppCustomerCareWindow;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class HumanInboxService
{
    public function __construct(
        private readonly WhatsAppOutputAdapterFactory $outputAdapterFactory,
        private readonly WhatsAppCustomerCareWindow $customerCareWindow,
        private readonly FlowEngine $flowEngine,
        private readonly AgentConversationService $agentConversations,
    ) {}

    public function waitingExecution(Contact $contact, Channel $channel, bool $lock = false): ?FlowExecution
    {
        $query = FlowExecution::query()
            ->where('contact_id', $contact->getKey())
            ->where('channel_id', $channel->getKey())
            ->where('status', FlowExecutionStatus::Paused)
            ->where('waiting_for', 'human')
            ->with(['contact', 'channel', 'flowVersion.flow', 'humanAssignee']);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->latest('created_at')->first();
    }

    public function assign(Contact $contact, Channel $channel, User $user): FlowExecution
    {
        return DB::transaction(function () use ($contact, $channel, $user): FlowExecution {
            $execution = $this->waitingExecution($contact, $channel, lock: true);
            if ($execution === null) {
                throw new ConflictHttpException('Esta conversa não está mais aguardando atendimento.');
            }

            if ($execution->human_assigned_user_id !== null
                && $execution->human_assigned_user_id !== $user->getKey()) {
                throw new ConflictHttpException('Esta conversa já foi assumida por outra pessoa da Equipe.');
            }

            $now = now();
            $execution->forceFill([
                'human_assigned_user_id' => $user->getKey(),
                'human_assigned_at' => $execution->human_assigned_at ?? $now,
                'human_last_activity_at' => $now,
            ])->save();

            HumanConversationUpdated::dispatch($execution->team_id, $execution->contact_id, $execution->channel_id, 'assigned');

            return $execution->fresh(['contact', 'channel', 'flowVersion.flow', 'humanAssignee']);
        });
    }

    /**
     * @param  array{type?: string, content?: string, template?: string, parameters?: list<string>}  $data
     */
    public function send(Contact $contact, Channel $channel, User $user, array $data): Message
    {
        if ($this->waitingExecution($contact, $channel) === null) {
            return $this->agentConversations->send($contact, $channel, $user, $data);
        }

        return DB::transaction(function () use ($contact, $channel, $user, $data): Message {
            $execution = $this->waitingExecution($contact, $channel, lock: true);
            if ($execution === null || $execution->channel === null) {
                throw new ConflictHttpException('Esta conversa não está mais aguardando atendimento.');
            }

            if ($execution->human_assigned_user_id !== $user->getKey()) {
                throw new ConflictHttpException('Assuma a conversa antes de enviar uma Mensagem.');
            }

            $channel = $execution->channel;
            if ($channel->status !== ChannelStatus::Connected) {
                throw new ConflictHttpException('Canal indisponível. Reconecte o Canal antes de enviar uma Mensagem.');
            }

            $type = (string) ($data['type'] ?? 'text');
            $content = trim((string) ($data['content'] ?? ''));
            $lastIncoming = $this->lastIncomingMessage($channel, $contact);

            if ($type === 'text' && $this->customerCareWindow->hasExpired($channel, $lastIncoming)) {
                throw new ConflictHttpException('A janela de 24 horas terminou. Envie um Template aprovado para continuar.');
            }

            $adapter = $this->outputAdapterFactory->make($channel);
            $message = $type === 'template'
                ? $adapter->sendTemplate(
                    $channel,
                    $contact,
                    (string) ($data['template'] ?? ''),
                    $data['parameters'] ?? [],
                )
                : $adapter->sendText($channel, $contact, $content);

            $message->forceFill([
                'sent_by_user_id' => $user->getKey(),
            ])->save();
            $execution->forceFill([
                'human_last_activity_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            HumanConversationUpdated::dispatch($execution->team_id, $execution->contact_id, $execution->channel_id, 'message');

            return $message;
        });
    }

    public function returnToBot(Contact $contact, Channel $channel): void
    {
        DB::transaction(function () use ($contact, $channel): void {
            $execution = $this->waitingExecution($contact, $channel, lock: true);
            if ($execution === null) {
                throw new ConflictHttpException('Esta conversa não está mais aguardando atendimento.');
            }

            $this->flowEngine->returnToFlow($execution);
        });
    }

    public function releaseStaleAssignments(): int
    {
        $cutoff = now()->subSeconds((int) config('flows.human_assignment_idle_seconds', 900));
        $released = 0;

        FlowExecution::withoutGlobalScopes()
            ->where('status', FlowExecutionStatus::Paused)
            ->where('waiting_for', 'human')
            ->whereNotNull('human_assigned_user_id')
            ->where('human_last_activity_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($executions) use (&$released): void {
                foreach ($executions as $execution) {
                    $wasReleased = DB::transaction(function () use ($execution): bool {
                        $locked = FlowExecution::withoutGlobalScopes()
                            ->whereKey($execution->getKey())
                            ->where('status', FlowExecutionStatus::Paused)
                            ->where('waiting_for', 'human')
                            ->whereNotNull('human_assigned_user_id')
                            ->lockForUpdate()
                            ->first();

                        if ($locked === null) {
                            return false;
                        }

                        $cutoff = now()->subSeconds((int) config('flows.human_assignment_idle_seconds', 900));
                        if ($locked->human_last_activity_at === null || $locked->human_last_activity_at->isAfter($cutoff)) {
                            return false;
                        }

                        $locked->forceFill([
                            'human_assigned_user_id' => null,
                            'human_assigned_at' => null,
                            'human_last_activity_at' => null,
                        ])->save();
                        HumanConversationUpdated::dispatch($locked->team_id, $locked->contact_id, $locked->channel_id, 'unassigned');

                        return true;
                    });

                    $released += $wasReleased ? 1 : 0;
                }
            });

        return $released;
    }

    private function lastIncomingMessage(Channel $channel, Contact $contact): ?Message
    {
        return $contact->messages()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Incoming)
            ->latest('created_at')
            ->latest('id')
            ->first();
    }
}
