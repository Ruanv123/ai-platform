<?php

namespace App\Enums;

enum WhatsAppTemplateStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paused = 'paused';
    case Disabled = 'disabled';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Pending => 'Aguardando aprovação',
            self::Approved => 'Aprovado',
            self::Rejected => 'Recusado',
            self::Paused => 'Pausado',
            self::Disabled => 'Desativado',
            self::Deleted => 'Removido na Meta',
        };
    }

    public function canSend(): bool
    {
        return $this === self::Approved;
    }
}
