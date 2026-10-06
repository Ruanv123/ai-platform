<?php

namespace App\Support;

final class E164PhoneNumber
{
    public static function format(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (preg_match('/^55\d{2}[6-9]\d{7}$/', $digits) === 1) {
            $digits = substr($digits, 0, 4).'9'.substr($digits, 4);
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }

    public static function recipient(string $phone): string
    {
        return ltrim(self::format($phone) ?? $phone, '+');
    }
}
