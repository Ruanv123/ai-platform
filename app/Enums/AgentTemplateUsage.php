<?php

namespace App\Enums;

enum AgentTemplateUsage: string
{
    case ResumeConversation = 'resume_conversation';
    case AppointmentReminder = 'appointment_reminder';
    case FollowUp = 'follow_up';
    case OnDemand = 'on_demand';

    public function label(): string
    {
        return match ($this) {
            self::ResumeConversation => 'Retomar conversa (janela fechada)',
            self::AppointmentReminder => 'Lembrete de compromisso',
            self::FollowUp => 'Follow-up sem resposta',
            self::OnDemand => 'Uso livre (o Agente decide)',
        };
    }

    public function isAutomatic(): bool
    {
        return $this !== self::OnDemand;
    }
}
