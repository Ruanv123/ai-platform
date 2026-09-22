<?php

namespace App\Integrations;

use App\Models\Integration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class IntegrationClient
{
    /** @param array<string, string> $requestHeaders */
    public function request(Integration $integration, array $requestHeaders = []): PendingRequest
    {
        $credentials = $integration->credentials;
        $request = Http::connectTimeout(3)->timeout(15);

        if ($requestHeaders !== []) {
            $request = $request->withHeaders($requestHeaders);
        }

        $headers = is_array($credentials['headers'] ?? null) ? $credentials['headers'] : [];
        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        return match ((string) ($credentials['auth_type'] ?? 'none')) {
            'bearer' => $request->withToken((string) ($credentials['token'] ?? $credentials['api_key'] ?? '')),
            'api_key' => $request->withHeaders([
                (string) ($credentials['api_key_header'] ?? 'X-API-Key') => (string) ($credentials['api_key'] ?? $credentials['token'] ?? ''),
            ]),
            'basic' => $request->withBasicAuth(
                (string) ($credentials['username'] ?? ''),
                (string) ($credentials['password'] ?? ''),
            ),
            default => $request,
        };
    }

    public function url(Integration $integration, string $url): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return $url;
        }

        $baseUrl = $integration->baseUrl();
        if ($baseUrl === null) {
            return $url;
        }

        return $baseUrl.'/'.ltrim($url, '/');
    }
}
