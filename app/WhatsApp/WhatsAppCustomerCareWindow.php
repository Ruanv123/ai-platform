<?php

namespace App\WhatsApp;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Message;

final class WhatsAppCustomerCareWindow
{
    public const int DURATION_SECONDS = 24 * 60 * 60;

    public function hasExpired(Channel $channel, ?Message $message): bool
    {
        return $channel->type === ChannelType::WhatsApp
            && $message?->created_at !== null
            && $message->created_at->lte(now()->subSeconds(self::DURATION_SECONDS));
    }
}
