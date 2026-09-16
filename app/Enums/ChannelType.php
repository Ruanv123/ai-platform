<?php

namespace App\Enums;

enum ChannelType: string
{
    case WhatsApp = 'whatsapp';
    case WebsiteChat = 'website_chat';
    case InstagramDirect = 'instagram_direct';

    /**
     * Get the display label for the channel type.
     */
    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::WebsiteChat => 'Chat no site',
            self::InstagramDirect => 'Instagram Direct',
        };
    }
}
