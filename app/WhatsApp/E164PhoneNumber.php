<?php

namespace App\WhatsApp;

final class E164PhoneNumber
{
    /**
     * Format a phone number as E.164, restoring the Brazilian mobile ninth digit omitted by WhatsApp ids.
     */
    public static function format(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (preg_match('/^55\d{2}[6-9]\d{7}$/', $digits) === 1) {
            $digits = substr($digits, 0, 4).'9'.substr($digits, 4);
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }

    /**
     * Get the digits WhatsApp providers expect as the recipient of a message.
     */
    public static function recipient(string $phone): string
    {
        return ltrim(self::format($phone) ?? $phone, '+');
    }
}
