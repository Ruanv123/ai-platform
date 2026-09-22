<?php

namespace App\WhatsApp;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Message;
use Carbon\CarbonImmutable;

final class WhatsAppCustomerCareWindow
{
    public const int DURATION_SECONDS = 24 * 60 * 60;

    public function hasExpired(Channel $channel, ?Message $message): bool
    {
        $expiresAt = $this->expiresAt($channel, $message);

        return $expiresAt !== null && $expiresAt->isPast();
    }

    public function expiresAt(Channel $channel, ?Message $message): ?CarbonImmutable
    {
        if ($channel->type !== ChannelType::WhatsApp || $message?->created_at === null) {
            return null;
        }

        return CarbonImmutable::instance($message->created_at)->addSeconds(self::DURATION_SECONDS);
    }

    public function remainingSeconds(Channel $channel, ?Message $message): ?int
    {
        $expiresAt = $this->expiresAt($channel, $message);

        if ($expiresAt === null) {
            return null;
        }

        return max(0, (int) now()->diffInSeconds($expiresAt, false));
    }
}
