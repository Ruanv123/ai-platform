<?php

namespace App\Console\Commands;

use App\Enums\AgentTemplateUsage;
use App\Inbox\AgentTemplateSender;
use App\Models\Agent;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

final class SendAppointmentReminders extends Command
{
    public const int MAX_LEAD_HOURS = 168;

    protected $signature = 'agents:send-appointment-reminders';

    protected $description = 'Envia pelo Agente o lembrete dos compromissos que entraram na antecedência configurada.';

    public function handle(AgentTemplateSender $sender): int
    {
        $sent = 0;

        Appointment::withoutGlobalScopes()
            ->where('status', 'booked')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('schedulable_calendar_id')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addHours(self::MAX_LEAD_HOURS))
            ->with(['contact', 'schedulableCalendar'])
            ->chunkById(100, function (Collection $appointments) use ($sender, &$sent): void {
                foreach ($appointments as $appointment) {
                    if ($this->remind($appointment, $sender)) {
                        $sent++;
                    }
                }
            });

        $this->info("Lembretes enviados: {$sent}.");

        return self::SUCCESS;
    }

    private function remind(Appointment $appointment, AgentTemplateSender $sender): bool
    {
        if ($appointment->contact === null) {
            return false;
        }

        $agents = Agent::withoutGlobalScopes()
            ->where('team_id', $appointment->team_id)
            ->where('is_active', true)
            ->whereHas('schedulableCalendars', fn ($query) => $query->whereKey($appointment->schedulable_calendar_id))
            ->whereHas('whatsappTemplates', fn ($query) => $query->where('agent_whatsapp_template.usage', AgentTemplateUsage::AppointmentReminder->value))
            ->with(['channels', 'team'])
            ->get();

        foreach ($agents as $agent) {
            if ($appointment->starts_at->greaterThan(now()->addHours($agent->reminder_lead_hours))) {
                continue;
            }

            $channel = $this->conversationChannel($agent, $appointment);
            if ($channel === null) {
                continue;
            }

            $timezone = $agent->team->timezone ?: 'America/Sao_Paulo';
            $startsAt = $appointment->starts_at->setTimezone($timezone);
            $delivery = $sender->send(
                $agent,
                $channel,
                $appointment->contact,
                AgentTemplateUsage::AppointmentReminder,
                'Lembrete de um compromisso que o Agente marcou com o Contato.',
                array_filter([
                    'Compromisso: '.$startsAt->format('d/m/Y').' às '.$startsAt->format('H:i')
                        .' até '.$appointment->ends_at->setTimezone($timezone)->format('H:i'),
                    'Agenda: '.($appointment->schedulableCalendar?->display_name ?? 'não informada'),
                    filled($appointment->reason) ? 'Motivo informado: '.$appointment->reason : null,
                ]),
            );

            if ($delivery->isNotConfigured() || $delivery->reason === 'conversation_paused') {
                continue;
            }

            $appointment->forceFill(['reminder_sent_at' => now()])->save();

            return $delivery->sent;
        }

        return false;
    }

    private function conversationChannel(Agent $agent, Appointment $appointment): ?Channel
    {
        $channelIds = $agent->channels->modelKeys();
        if ($channelIds === []) {
            return null;
        }

        $channelId = Message::withoutGlobalScopes()
            ->where('contact_id', $appointment->contact_id)
            ->whereIn('channel_id', $channelIds)
            ->latest('created_at')
            ->latest('id')
            ->value('channel_id');

        return $channelId === null ? null : $agent->channels->firstWhere('id', $channelId);
    }
}
