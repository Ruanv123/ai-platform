<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SiteReadWebsite implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $parts = parse_url($value);

        if (! is_array($parts)) {
            return;
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('Informe um site válido começando por http:// ou https://.');

            return;
        }

        foreach (['instagram.com', 'wa.me', 'api.whatsapp.com', 'whatsapp.com'] as $blockedHost) {
            if ($host === $blockedHost || str_ends_with($host, '.'.$blockedHost)) {
                $fail('Instagram e endereços de WhatsApp não podem ser lidos. Informe o site da Empresa ou preencha os dados manualmente.');

                return;
            }
        }
    }
}
