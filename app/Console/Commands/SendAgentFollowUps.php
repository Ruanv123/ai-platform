<?php

namespace App\Console\Commands;

use App\Enums\AgentTemplateUsage;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Inbox\AgentTemplateSender;
use App\Models\Agent;
use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class SendAgentFollowUps extends Command
{
    public const int LOOKBACK_HOURS = 72;

    protected $signature = 'agents:send-follow-ups';

    protected $description = 'Retoma pelo Agente as conversas em que o Contato parou de responder.';

    public function handle(AgentTemplateSender $sender): int
    {
        $sent = 0;

        Agent::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereHas('whatsappTemplates', fn ($query) => $query->where('agent_whatsapp_template.usage', AgentTemplateUsage::FollowUp->value))
            ->with(['channels', 'team'])
            ->each(function (Agent $agent) use ($sender, &$sent): void {
                if (! $this->isWithinBusinessHours($agent->team)) {
                    return;
                }

                foreach ($agent->channels as $channel) {
                    $sent += $this->followUpChannel($agent, $channel, $sender);
                }
            });

        $this->info("Follow-ups enviados: {$sent}.");

        return self::SUCCESS;
    }

    private function followUpChannel(Agent $agent, Channel $channel, AgentTemplateSender $sender): int
    {
        $threshold = now()->toImmutable()->subHours($agent->follow_up_after_hours);
        $sent = 0;

        $candidates = Message::withoutGlobalScopes()
            ->where('channel_id', $channel->getKey())
            ->where('direction', MessageDirection::Outgoing)
            ->where('payload_type', 'text')
            ->whereNull('sent_by_user_id')
            ->where(fn ($query) => $query
                ->whereNull('delivery_status')
                ->orWhere('delivery_status', '!=', MessageDeliveryStatus::Failed->value))
            ->where('created_at', '<=', $threshold)
            ->where('created_at', '>', $threshold->subHours(self::LOOKBACK_HOURS))
            ->whereNotExists(fn ($query) => $query
                ->from('messages as later')
                ->whereColumn('later.contact_id', 'messages.contact_id')
                ->whereColumn('later.channel_id', 'messages.channel_id')
                ->where(fn ($query) => $query
                    ->whereColumn('later.created_at', '>', 'messages.created_at')
                    ->orWhere(fn ($query) => $query
                        ->whereColumn('later.created_at', 'messages.created_at')
                        ->whereColumn('later.id', '>', 'messages.id'))))
            ->get();

        foreach ($candidates as $message) {
            $state = AgentConversationState::withoutGlobalScopes()->firstOrNew([
                'team_id' => $channel->team_id,
                'contact_id' => $message->contact_id,
                'channel_id' => $channel->getKey(),
            ]);
            if ($state->is_paused || (int) $state->follow_up_message_id === (int) $message->getKey()) {
                continue;
            }

            $contact = Contact::withoutGlobalScopes()->find($message->contact_id);
            if ($contact === null) {
                continue;
            }

            $delivery = $sender->send(
                $agent,
                $channel,
                $contact,
                AgentTemplateUsage::FollowUp,
                'O Contato parou de responder depois desta mensagem do Agente: '.str($message->content ?? '')->limit(500),
            );

            if ($delivery->isNotConfigured() || $delivery->reason === 'conversation_paused') {
                continue;
            }

            $state->forceFill([
                'is_paused' => $state->is_paused ?? false,
                'follow_up_message_id' => $message->getKey(),
            ])->save();

            if ($delivery->sent) {
                $sent++;
            }
        }

        return $sent;
    }

    private function isWithinBusinessHours(Team $team): bool
    {
        $hours = $team->business_hours;
        if (! is_array($hours) || $hours === []) {
            return true;
        }

        $now = CarbonImmutable::now($team->timezone ?: 'America/Sao_Paulo');
        $day = $hours[strtolower($now->englishDayOfWeek)] ?? null;
        if (! is_array($day) || ($day['enabled'] ?? true) === false || ($day['enabled'] ?? true) === '0') {
            return false;
        }

        $open = $day['open'] ?? null;
        $close = $day['close'] ?? null;
        $time = $now->format('H:i');

        return is_string($open) && is_string($close) && $time >= $open && $time < $close;
    }
}
