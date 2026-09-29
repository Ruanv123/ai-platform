<?php

namespace App\Crm\Adapters;

use App\Crm\AbstractCrmProviderAdapter;
use App\Crm\CrmErrorCategory;
use App\Crm\CrmProviderException;
use App\Models\Integration;
use App\Models\Scopes\TeamScope;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * @phpstan-import-type CrmCapabilities from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmMetadata from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmOAuthTokens from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmRateLimit from \App\Crm\Contracts\CrmProviderAdapter
 */
class RdStationCrmAdapter extends AbstractCrmProviderAdapter
{
    private const AUTHORIZATION_URL = 'https://accounts.rdstation.com/oauth/authorize';

    private const TOKEN_URL = 'https://api.rd.services/oauth2/token';

    private const API_URL = 'https://api.rd.services/crm/v2';

    private const REFRESH_WINDOW_SECONDS = 300;

    public function provider(): string
    {
        return 'rd_station';
    }

    public function authorizationUrl(string $state): string
    {
        $clientId = config('services.rd_station_crm.client_id');
        $redirect = config('services.rd_station_crm.redirect');
        if (! is_string($clientId) || $clientId === '' || ! is_string($redirect) || $redirect === '') {
            throw new RuntimeException('Configure o aplicativo do RD Station CRM antes de conectar.');
        }

        return self::AUTHORIZATION_URL.'?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    /** @return CrmOAuthTokens */
    public function exchangeAuthorizationCode(string $code): array
    {
        $response = Http::asForm()->connectTimeout(3)->timeout(15)->post(self::TOKEN_URL, [
            'client_id' => config('services.rd_station_crm.client_id'),
            'client_secret' => config('services.rd_station_crm.client_secret'),
            'redirect_uri' => config('services.rd_station_crm.redirect'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        $this->throwIfFailed($response);
        $tokens = $response->json();
        if (! is_string($tokens['access_token'] ?? null) || ! is_string($tokens['refresh_token'] ?? null)) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM não retornou as credenciais esperadas.');
        }

        $expiresIn = is_numeric($tokens['expires_in'] ?? null) ? (int) $tokens['expires_in'] : 7200;
        $normalizedTokens = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds($expiresIn)->getTimestamp(),
            'expires_in' => $expiresIn,
        ];
        if (is_string($tokens['token_type'] ?? null)) {
            $normalizedTokens['token_type'] = $tokens['token_type'];
        }

        return $normalizedTokens;
    }

    public function freshIntegration(Integration $integration): Integration
    {
        $expiresAt = (int) $integration->credential('expires_at', 0);
        if (! $integration->exists || $expiresAt > now()->addSeconds(self::REFRESH_WINDOW_SECONDS)->timestamp) {
            return $integration;
        }

        $integrationId = (string) $integration->getKey();
        $credentials = Cache::lock('crm:token-refresh:'.$integrationId, 30)->block(10, function () use ($integrationId): array {
            $current = Integration::withoutGlobalScope(TeamScope::class)
                ->whereKey($integrationId)
                ->where('type', 'crm')
                ->firstOrFail();
            $expiry = (int) $current->credential('expires_at', 0);
            if ($expiry > now()->addSeconds(self::REFRESH_WINDOW_SECONDS)->timestamp) {
                return $current->credentials;
            }

            $refreshToken = $current->credential('refresh_token');
            if (! is_string($refreshToken) || $refreshToken === '') {
                $this->markAccessError($current);
                throw new CrmProviderException(CrmErrorCategory::AccessDenied, 'A autorização do RD Station CRM expirou. Conecte novamente.');
            }

            $response = Http::asForm()->connectTimeout(3)->timeout(15)->post(self::TOKEN_URL, [
                'client_id' => config('services.rd_station_crm.client_id'),
                'client_secret' => config('services.rd_station_crm.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

            if (! $response->successful()) {
                if ($response->status() === 429 || $response->serverError()) {
                    $this->throwIfFailed($response);
                }
                $this->markAccessError($current);
                $message = data_get($response->json(), 'error_description') ?? 'O acesso do RD Station CRM foi revogado. Reconecte a conta.';

                throw new CrmProviderException(CrmErrorCategory::AccessDenied, is_string($message) ? $message : 'O acesso do RD Station CRM foi revogado. Reconecte a conta.');
            }

            $tokens = $response->json();
            if (! is_array($tokens) || ! is_string($tokens['access_token'] ?? null) || ! is_string($tokens['refresh_token'] ?? null)) {
                $this->markAccessError($current);
                throw new CrmProviderException(CrmErrorCategory::AccessDenied, 'O RD Station CRM recusou a renovação do acesso. Reconecte a conta.');
            }

            $credentials = [
                ...$current->credentials,
                ...$tokens,
                'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 7200))->timestamp,
            ];
            $current->forceFill(['credentials' => $credentials, 'status' => 'connected', 'status_message' => null])->save();

            return $credentials;
        });

        $integration->forceFill(['credentials' => $credentials]);

        return $integration;
    }

    /** @return CrmMetadata */
    public function loadMetadata(Integration $integration): array
    {
        $integration = $this->freshIntegration($integration);
        $pipelines = $this->allPages($integration, '/pipelines');
        $normalizedPipelines = [];
        foreach ($pipelines as $pipeline) {
            $pipelineId = (string) ($pipeline['id'] ?? '');
            $stages = $pipelineId === '' ? [] : $this->allPages($integration, '/pipelines/'.rawurlencode($pipelineId).'/stages');
            $normalizedPipelines[] = [
                'id' => $pipelineId,
                'name' => (string) ($pipeline['name'] ?? ''),
                'stages' => array_map(fn (array $stage): array => [
                    'id' => (string) ($stage['id'] ?? ''),
                    'name' => (string) ($stage['name'] ?? ''),
                    'status' => isset($stage['status']) ? (string) $stage['status'] : null,
                    'position' => is_numeric($stage['position'] ?? null) ? (int) $stage['position'] : null,
                ], $stages),
            ];
        }

        $fields = $this->allPages($integration, '/custom_fields');
        $owners = $this->allPages($integration, '/users');
        $lostReasons = $this->allPages($integration, '/lost_reasons');
        $teams = $this->allPages($integration, '/teams');

        return [
            'account' => [
                'teams' => array_map(fn (array $team): array => [
                    'id' => (string) ($team['id'] ?? ''),
                    'name' => (string) ($team['name'] ?? ''),
                ], $teams),
            ],
            'pipelines' => $normalizedPipelines,
            'fields' => array_map(fn (array $field): array => [
                'id' => (string) ($field['id'] ?? ''),
                'name' => (string) ($field['label'] ?? $field['name'] ?? ''),
                'type' => (string) ($field['type'] ?? 'text'),
                'options' => is_array($field['options'] ?? null) ? array_values($field['options']) : [],
            ], $fields),
            'owners' => array_map(fn (array $owner): array => [
                'id' => (string) ($owner['id'] ?? ''),
                'name' => (string) ($owner['name'] ?? $owner['email'] ?? ''),
                'email' => is_string($owner['email'] ?? null) ? $owner['email'] : null,
            ], $owners),
            'lost_reasons' => array_map(fn (array $reason): array => [
                'id' => (string) ($reason['id'] ?? ''),
                'name' => (string) ($reason['name'] ?? ''),
            ], $lostReasons),
        ];
    }

    /** @return CrmCapabilities */
    public function capabilities(): array
    {
        return [
            'metadata' => true,
            'loss_reason_required' => true,
            'won_and_lost_stages' => false,
            'webhooks' => false,
            'operations' => [],
        ];
    }

    /** @return CrmRateLimit */
    public function rateLimit(): array
    {
        return ['requests' => 120, 'window_seconds' => 60];
    }

    /** @return list<array<string, mixed>> */
    private function allPages(Integration $integration, string $path): array
    {
        $page = 1;
        $nextUrl = null;
        $nextParameters = [];
        $hasNextPage = true;
        $pageCount = 0;
        $results = [];
        while ($hasNextPage && $pageCount < 100) {
            $response = $this->api($integration)->get(
                $nextUrl ?? $path,
                $nextUrl === null ? ['page[number]' => $page, 'page[size]' => 100] : $nextParameters,
            );
            $this->throwIfFailed($response);
            $payload = $response->json();
            $items = is_array($payload) ? ($payload['data'] ?? $payload['items'] ?? $payload['results'] ?? $payload) : [];
            if (! is_array($items)) {
                break;
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    $results[] = $item;
                }
            }
            $nextLink = data_get($payload, 'links.next');
            if (is_string($nextLink) && $nextLink !== '') {
                $nextUrl = $this->nextPagePath($nextLink, $path, $nextParameters);
                $page++;
            } else {
                $pagination = $payload['pagination'] ?? $payload['meta']['pagination'] ?? [];
                $totalPages = (int) ($pagination['total_pages'] ?? $pagination['totalPages'] ?? 1);
                $hasNextPage = (bool) ($pagination['has_next_page'] ?? $page < $totalPages);
                $nextUrl = null;
                $nextParameters = [];
                if ($hasNextPage) {
                    $page++;
                }
            }
            $pageCount++;
        }

        return $results;
    }

    /** @param array<array-key, mixed> $parameters */
    private function nextPagePath(string $nextLink, string $currentPath, array &$parameters): string
    {
        $parts = parse_url($nextLink);
        if (! is_array($parts)
            || (isset($parts['host']) && $parts['host'] !== 'api.rd.services')
            || (isset($parts['scheme']) && $parts['scheme'] !== 'https')) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou um endereço de paginação inválido.');
        }

        $nextPath = $parts['path'] ?? $currentPath;
        if (! str_starts_with($nextPath, '/crm/v2/')) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou um endereço de paginação inválido.');
        }

        parse_str($parts['query'] ?? '', $parameters);

        return substr($nextPath, strlen('/crm/v2'));
    }

    private function api(Integration $integration): PendingRequest
    {
        $fresh = $this->freshIntegration($integration);
        $token = $fresh->credential('access_token');
        if (! is_string($token) || $token === '') {
            throw new CrmProviderException(CrmErrorCategory::AccessDenied, 'A conta do RD Station CRM precisa ser reconectada.');
        }

        return Http::baseUrl(self::API_URL)
            ->connectTimeout(3)
            ->timeout(15)
            ->acceptJson()
            ->withToken($token);
    }

    private function throwIfFailed(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $category = match ($response->status()) {
            401, 403 => CrmErrorCategory::AccessDenied,
            404 => CrmErrorCategory::MissingReference,
            429 => CrmErrorCategory::RateLimit,
            500, 502, 503, 504 => CrmErrorCategory::Temporary,
            default => CrmErrorCategory::Definitive,
        };
        $message = data_get($response->json(), 'message') ?? 'O RD Station CRM não concluiu a solicitação.';
        $retryAfter = $response->header('Retry-After');

        throw new CrmProviderException(
            $category,
            is_string($message) ? $message : 'O RD Station CRM não concluiu a solicitação.',
            is_numeric($retryAfter) ? (int) $retryAfter : null,
        );
    }

    private function markAccessError(Integration $integration): void
    {
        $integration->forceFill([
            'status' => 'error',
            'status_message' => 'O acesso ao RD Station CRM foi recusado. Reconecte a conta.',
        ])->save();
    }
}
