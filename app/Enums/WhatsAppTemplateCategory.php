<?php

namespace App\Enums;

enum WhatsAppTemplateCategory: string
{
    case Marketing = 'marketing';
    case Utility = 'utility';
    case Authentication = 'authentication';

    public function label(): string
    {
        return match ($this) {
            self::Marketing => 'Marketing',
            self::Utility => 'Utilidade',
            self::Authentication => 'Autenticação',
        };
    }
}
