<?php

namespace App\Enums;

enum ChannelStatus: string
{
    case NotConfigured = 'not_configured';
    case Connected = 'connected';
    case Error = 'error';

    /**
     * Get the display label for the channel status.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotConfigured => 'Não conectado',
            self::Connected => 'Conectado',
            self::Error => 'Erro',
        };
    }
}
