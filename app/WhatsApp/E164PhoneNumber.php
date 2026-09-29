<?php

namespace App\WhatsApp;

use App\Support\E164PhoneNumber as SharedE164PhoneNumber;

final class E164PhoneNumber
{
    public static function format(string $phone): ?string
    {
        return SharedE164PhoneNumber::format($phone);
    }

    public static function recipient(string $phone): string
    {
        return SharedE164PhoneNumber::recipient($phone);
    }
}
