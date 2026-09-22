<?php

namespace App\Flows;

use Illuminate\Support\Str;

final class TraceSanitizer
{
    /** @var list<string> */
    private const CREDENTIAL_KEYS = [
        'access_token',
        'api_key',
        'apikey',
        'authorization',
        'client_secret',
        'cookie',
        'credential',
        'password',
        'private_key',
        'secret',
        'set_cookie',
        'token',
    ];

    /**
     * @param  array<int, string>  $sensitiveFields
     */
    public function sanitize(mixed $value, array $sensitiveFields = []): mixed
    {
        $sensitiveKeys = $this->sensitiveKeys($sensitiveFields);

        return $this->sanitizeValue($value, $sensitiveKeys);
    }

    /**
     * @param  array<string, mixed>  $nodeConfig
     * @return array<string, mixed>
     */
    public function sanitizeVariables(array $variables, array $nodeConfig = []): array
    {
        $fields = $nodeConfig['sensitive_fields']
            ?? $nodeConfig['sensitiveFields']
            ?? $nodeConfig['sensitive']
            ?? [];
        $fields = is_array($fields) ? array_values(array_filter($fields, 'is_string')) : [];

        /** @var array<string, mixed> */
        return $this->sanitize($variables, $fields);
    }

    /**
     * Redact secrets that may have been interpolated into trace text.
     *
     * @param  array<int, string>  $sensitiveFields
     * @param  array<string, mixed>  $context
     */
    public function sanitizeText(string $text, array $sensitiveFields = [], array $context = []): string
    {
        $sensitiveKeys = $this->sensitiveKeys($sensitiveFields);
        $values = $this->sensitiveValues($context, $sensitiveKeys);

        foreach ($values as $value) {
            if ($value !== '') {
                $text = str_replace($value, '***', $text);
            }
        }

        $text = preg_replace('/\\bBearer\\s+[^\\s,;"\']+/i', 'Bearer ***', $text) ?? $text;
        $text = preg_replace('~(https?://)[^/\\s:@]+:[^@\\s/]+@~i', '$1[credencial]@', $text) ?? $text;

        return preg_replace(
            '/(authorization|access[_-]?token|api[_-]?key|client[_-]?secret|password|private[_-]?key|secret|token)(\\s*[=:]\\s*)([^\\s,;"\']+)/i',
            '$1$2***',
            $text,
        ) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<int, string>  $sensitiveFields
     * @return array<string, mixed>
     */
    public function sanitizeHeaders(array $headers, array $sensitiveFields = []): array
    {
        /** @var array<string, mixed> */
        return $this->sanitize($headers, $sensitiveFields);
    }

    /**
     * Remove credentials from a URL while retaining useful query context.
     */
    public function sanitizeUrl(string $url, array $sensitiveFields = []): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return '[url inválida]';
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $authority = $parts['host'] ?? '';
        if (isset($parts['user'])) {
            $authority = '[credencial]@'.$authority;
        }
        if (isset($parts['port'])) {
            $authority .= ':'.$parts['port'];
        }
        $path = $parts['path'] ?? '';
        $query = isset($parts['query'])
            ? '?'.http_build_query($this->sanitizeQuery($parts['query'], $sensitiveFields))
            : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.$authority.$path.$query.$fragment;
    }

    /**
     * @param  array<int, string>  $sensitiveFields
     * @return array<string, mixed>
     */
    private function sanitizeQuery(string $query, array $sensitiveFields): array
    {
        parse_str($query, $parameters);

        /** @var array<string, mixed> */
        return $this->sanitize($parameters, $sensitiveFields);
    }

    /**
     * @param  array<int, string>  $sensitiveFields
     * @return array<string, bool>
     */
    private function sensitiveKeys(array $sensitiveFields): array
    {
        $keys = [];
        foreach ($sensitiveFields as $field) {
            $normalized = $this->normalizeKey($field);
            $keys[$normalized] = true;
            $keys[$this->normalizeKey(Str::afterLast($field, '.'))] = true;
        }

        return $keys;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  array<string, bool>  $sensitiveKeys
     * @return list<string>
     */
    private function sensitiveValues(array $value, array $sensitiveKeys): array
    {
        $values = [];
        foreach ($value as $key => $item) {
            $normalizedKey = $this->normalizeKey((string) $key);
            if (isset($sensitiveKeys[$normalizedKey]) || $this->isCredentialKey($normalizedKey)) {
                if (is_scalar($item)) {
                    $values[] = (string) $item;
                }

                continue;
            }
            if (is_array($item)) {
                $values = [...$values, ...$this->sensitiveValues($item, $sensitiveKeys)];
            }
        }

        return array_values(array_unique(array_filter($values, filled(...))));
    }

    /**
     * @param  array<string, bool>  $sensitiveKeys
     */
    private function sanitizeValue(mixed $value, array $sensitiveKeys): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];
        foreach ($value as $key => $item) {
            $normalizedKey = $this->normalizeKey((string) $key);
            $sanitized[$key] = isset($sensitiveKeys[$normalizedKey]) || $this->isCredentialKey($normalizedKey)
                ? '***'
                : $this->sanitizeValue($item, $sensitiveKeys);
        }

        return $sanitized;
    }

    private function normalizeKey(string $key): string
    {
        return Str::of($key)->lower()->replaceMatches('/[^a-z0-9]/', '_')->trim('_')->toString();
    }

    private function isCredentialKey(string $key): bool
    {
        return in_array($key, self::CREDENTIAL_KEYS, true)
            || str_ends_with($key, '_token')
            || str_ends_with($key, '_secret')
            || str_ends_with($key, '_password');
    }
}
