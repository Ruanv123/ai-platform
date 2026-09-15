<?php

namespace App\Enums;

enum WhatsAppConnectionStatus: string
{
    case Unverified = 'unverified';
    case Connected = 'connected';
    case Error = 'error';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Não verificado',
            self::Connected => 'Conectado',
            self::Error => 'Erro',
        };
    }
}
