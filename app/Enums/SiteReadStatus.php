<?php

namespace App\Enums;

enum SiteReadStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Em andamento',
            self::Completed => 'Concluída',
            self::Failed => 'Com falha',
        };
    }
}
