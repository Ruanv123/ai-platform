<?php

namespace App\Crm\Adapters;

use App\Crm\AbstractCrmProviderAdapter;
use App\Crm\CrmErrorCategory;
use App\Crm\CrmProviderException;
use App\Models\Integration;
use App\Models\Scopes\TeamScope;
use App\Support\E164PhoneNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * @phpstan-import-type CrmCapabilities from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmDeal from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmMetadata from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmOAuthTokens from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmPerson from \App\Crm\Contracts\CrmProviderAdapter
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

    public function freshIntegration(Integration $integration, float $timeoutSeconds = 15.0): Integration
    {
        $expiresAt = (int) $integration->credential('expires_at', 0);
        if (! $integration->exists || $expiresAt > now()->addSeconds(self::REFRESH_WINDOW_SECONDS)->timestamp) {
            return $integration;
        }

        return $this->refreshIntegration($integration, $timeoutSeconds, false);
    }

    public function renewIntegration(Integration $integration, float $timeoutSeconds = 15.0): Integration
    {
        return $this->refreshIntegration($integration, $timeoutSeconds, true);
    }

    private function refreshIntegration(Integration $integration, float $timeoutSeconds, bool $force): Integration
    {
        if (! $integration->exists) {
            throw new CrmProviderException(CrmErrorCategory::AccessDenied, 'A conta do RD Station CRM precisa ser reconectada.');
        }

        $integrationId = (string) $integration->getKey();
        $deadline = hrtime(true) + (int) ($timeoutSeconds * 1_000_000_000);
        $lockWaitSeconds = max(1, min(10, (int) ceil($timeoutSeconds)));
        $credentials = Cache::lock('crm:token-refresh:'.$integrationId, 30)->block($lockWaitSeconds, function () use ($integrationId, $deadline, $force): array {
            $current = Integration::withoutGlobalScope(TeamScope::class)
                ->whereKey($integrationId)
                ->where('type', 'crm')
                ->firstOrFail();
            $expiry = (int) $current->credential('expires_at', 0);
            if (! $force && $expiry > now()->addSeconds(self::REFRESH_WINDOW_SECONDS)->timestamp) {
                return $current->credentials;
            }

            $refreshToken = $current->credential('refresh_token');
            if (! is_string($refreshToken) || $refreshToken === '') {
                $this->markAccessError($current);
                throw new CrmProviderException(CrmErrorCategory::AccessDenied, 'A autorização do RD Station CRM expirou. Conecte novamente.');
            }

            $remainingSeconds = $this->remainingSeconds($deadline);
            if ($remainingSeconds <= 0) {
                throw new RuntimeException('The CRM request exceeded its time limit.');
            }

            $response = Http::asForm()->connectTimeout(min(2, $remainingSeconds))->timeout($remainingSeconds)->post(self::TOKEN_URL, [
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
                'slug' => (string) ($field['slug'] ?? ''),
                'entity' => (string) ($field['entity'] ?? ''),
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
            'operations' => ['lookup', 'person_create', 'person_update', 'deal_create', 'deal_update'],
        ];
    }

    public function findPeopleByPhone(Integration $integration, string $phone, float $timeoutSeconds = 15.0): array
    {
        $normalizedPhone = E164PhoneNumber::formatForCountry($phone, 'BR');
        if ($normalizedPhone === null) {
            return [];
        }

        $filters = array_map(
            fn (string $variant): string => 'phone:"'.$variant.'"',
            $this->phoneVariants($normalizedPhone),
        );
        $records = $this->allPages(
            $integration,
            '/contacts',
            ['filter' => implode(' or ', $filters)],
            $timeoutSeconds,
        );
        $people = [];

        foreach ($records as $record) {
            $person = $this->normalizePerson($record, $normalizedPhone);
            if ($person !== null) {
                $people[] = $person;
            }
        }

        return $people;
    }

    public function findPerson(Integration $integration, string $personId, float $timeoutSeconds = 15.0): array
    {
        $response = $this->api($integration, $timeoutSeconds)->get('/contacts/'.rawurlencode($personId));
        $this->throwIfFailed($response);
        $record = $response->json('data');

        return is_array($record)
            ? ($this->normalizePerson($record) ?? throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma pessoa inválida.'))
            : throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma pessoa inválida.');
    }

    public function findDeal(Integration $integration, string $dealId): array
    {
        $response = $this->api($integration, 2)->get('/deals/'.rawurlencode($dealId));
        $this->throwIfFailed($response);
        $record = $response->json('data');

        return is_array($record)
            ? $this->normalizeDeal($record)
            : throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma negociação inválida.');
    }

    public function findOpenDeals(Integration $integration, string $personId, float $timeoutSeconds = 15.0): array
    {
        $records = $this->allPages(
            $integration,
            '/deals',
            ['filter' => 'contact_id:"'.$personId.'" status:(ongoing,paused)'],
            $timeoutSeconds,
        );

        return array_values(array_map(
            fn (array $deal): array => $this->normalizeDeal($deal, $personId),
            array_filter($records, fn (array $deal): bool => $this->dealIncludesPerson($deal, $personId)
                && $this->dealStatus($deal['status'] ?? null) === 'open'),
        ));
    }

    public function createPerson(Integration $integration, array $attributes): array
    {
        $response = $this->api($integration)->post('/contacts', [
            'data' => $this->personRequestData($attributes),
        ]);
        $this->throwIfFailed($response);
        $record = $response->json('data');

        return is_array($record)
            ? ($this->normalizePerson($record, $attributes['phone'] ?? null) ?? throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma pessoa inválida.'))
            : throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma pessoa inválida.');
    }

    public function updatePerson(Integration $integration, string $personId, array $attributes): array
    {
        $response = $this->api($integration)->put('/contacts/'.rawurlencode($personId), [
            'data' => $this->personRequestData($attributes),
        ]);
        $this->throwIfFailed($response);
        $record = $response->json('data');

        return is_array($record)
            ? ($this->normalizePerson($record, $attributes['phone'] ?? null) ?? throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma pessoa inválida.'))
            : throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma pessoa inválida.');
    }

    public function createDeal(Integration $integration, array $attributes): array
    {
        $data = [
            'name' => $attributes['name'] ?? '',
            'contact_ids' => [$attributes['person_id']],
            'pipeline_id' => $attributes['pipeline_id'],
            'stage_id' => $attributes['stage_id'],
            'status' => ($attributes['status'] ?? 'open') === 'open' ? 'ongoing' : ($attributes['status'] ?? 'ongoing'),
        ];
        if (is_numeric($attributes['value'] ?? null)) {
            $data['total_price'] = (float) $attributes['value'];
        }
        if (is_string($attributes['owner_id'] ?? null) && $attributes['owner_id'] !== '') {
            $data['owner_id'] = $attributes['owner_id'];
        }
        if (is_string($attributes['organization_id'] ?? null) && $attributes['organization_id'] !== '') {
            $data['organization_id'] = $attributes['organization_id'];
        }

        $response = $this->api($integration)->post('/deals', ['data' => $data]);
        $this->throwIfFailed($response);
        $record = $response->json('data');

        return is_array($record)
            ? $this->normalizeDeal($record, $attributes['person_id'])
            : throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma negociação inválida.');
    }

    public function updateDeal(Integration $integration, string $dealId, array $attributes): array
    {
        $data = [];
        if (is_string($attributes['name'] ?? null)) {
            $data['name'] = $attributes['name'];
        }
        if (is_numeric($attributes['value'] ?? null)) {
            $data['total_price'] = (float) $attributes['value'];
        }

        $response = $this->api($integration)->put('/deals/'.rawurlencode($dealId), ['data' => $data]);
        $this->throwIfFailed($response);
        $record = $response->json('data');

        return is_array($record)
            ? $this->normalizeDeal($record, isset($attributes['person_id']) ? (string) $attributes['person_id'] : null)
            : throw new CrmProviderException(CrmErrorCategory::Definitive, 'O RD Station CRM retornou uma negociação inválida.');
    }

    /** @param CrmPersonAttributes $attributes
     * @return array<string, mixed>
     */
    private function personRequestData(array $attributes): array
    {
        $data = [];
        if (is_string($attributes['name'] ?? null) && $attributes['name'] !== '') {
            $data['name'] = $attributes['name'];
        }
        if (is_string($attributes['phone'] ?? null) && $attributes['phone'] !== '') {
            $data['phones'] = [['phone' => $attributes['phone'], 'type' => 'mobile']];
        }
        if (is_string($attributes['email'] ?? null) && $attributes['email'] !== '') {
            $data['emails'] = [['email' => $attributes['email']]];
        }
        if (is_string($attributes['organization_id'] ?? null) && $attributes['organization_id'] !== '') {
            $data['organization_id'] = $attributes['organization_id'];
        }
        if (is_string($attributes['owner_id'] ?? null) && $attributes['owner_id'] !== '') {
            $data['owner_id'] = $attributes['owner_id'];
        }
        if (is_array($attributes['fields'] ?? null) && $attributes['fields'] !== []) {
            $data['custom_fields'] = $attributes['fields'];
        }

        return $data;
    }

    /** @return CrmRateLimit */
    public function rateLimit(): array
    {
        return ['requests' => 120, 'window_seconds' => 60];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array<string, mixed>>
     */
    private function allPages(Integration $integration, string $path, array $parameters = [], float $timeoutSeconds = 15.0): array
    {
        $deadline = hrtime(true) + (int) ($timeoutSeconds * 1_000_000_000);
        $page = 1;
        $nextUrl = null;
        $nextParameters = [];
        $hasNextPage = true;
        $pageCount = 0;
        $results = [];
        while ($hasNextPage && $pageCount < 100) {
            $remainingSeconds = $this->remainingSeconds($deadline);
            if ($remainingSeconds <= 0) {
                throw new RuntimeException('The CRM request exceeded its time limit.');
            }

            $response = $this->api($integration, $remainingSeconds)->get(
                $nextUrl ?? $path,
                $nextUrl === null ? [...$parameters, 'page[number]' => $page, 'page[size]' => 100] : $nextParameters,
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

    private function api(Integration $integration, float $timeoutSeconds = 15.0): PendingRequest
    {
        $deadline = hrtime(true) + (int) ($timeoutSeconds * 1_000_000_000);
        $fresh = $this->freshIntegration($integration, $timeoutSeconds);
        $this->consumeRequestQuota($fresh);
        $token = $fresh->credential('access_token');
        if (! is_string($token) || $token === '') {
            throw new CrmProviderException(CrmErrorCategory::AccessDenied, 'A conta do RD Station CRM precisa ser reconectada.');
        }

        $remainingSeconds = $this->remainingSeconds($deadline);
        if ($remainingSeconds <= 0) {
            throw new RuntimeException('The CRM request exceeded its time limit.');
        }

        return Http::baseUrl(self::API_URL)
            ->connectTimeout(min(2, $remainingSeconds))
            ->timeout($remainingSeconds)
            ->acceptJson()
            ->withToken($token);
    }

    private function consumeRequestQuota(Integration $integration): void
    {
        $limit = $this->rateLimit();
        $requests = max(0, $limit['requests']);
        $windowSeconds = max(1, $limit['window_seconds']);
        if ($requests === 0) {
            return;
        }

        $now = now()->timestamp;
        $windowStart = intdiv($now, $windowSeconds) * $windowSeconds;
        $remainingSeconds = max(1, $windowStart + $windowSeconds - $now);
        $key = 'crm:request-quota:'.$integration->getKey().':'.$windowStart;

        Cache::lock('crm:request-quota-lock:'.$integration->getKey(), 5)->block(2, function () use ($key, $remainingSeconds, $requests): void {
            if (! Cache::has($key)) {
                Cache::put($key, 0, $remainingSeconds);
            }

            $used = (int) Cache::get($key, 0);
            if ($used >= $requests) {
                $retryAfter = max(1, $remainingSeconds);
                throw new CrmProviderException(CrmErrorCategory::RateLimit, 'O limite de requisições do CRM foi atingido.', $retryAfter);
            }

            Cache::increment($key);
        });
    }

    private function remainingSeconds(int $deadline): float
    {
        return max(0, ($deadline - hrtime(true)) / 1_000_000_000);
    }

    /** @param array<string, mixed> $record
     * @return CrmPerson|null
     */
    private function normalizePerson(array $record, ?string $expectedPhone = null): ?array
    {
        $phones = is_array($record['phones'] ?? null) ? $record['phones'] : [];
        $normalizedPhones = [];

        foreach ($phones as $phone) {
            $value = is_array($phone) && is_string($phone['phone'] ?? null) ? $phone['phone'] : null;
            $normalized = $value === null ? null : E164PhoneNumber::formatForCountry($value, 'BR');
            if ($normalized !== null) {
                $normalizedPhones[] = $normalized;
            }
        }

        $normalizedPhones = array_values(array_unique($normalizedPhones));
        if ($expectedPhone !== null && ! in_array($expectedPhone, $normalizedPhones, true)) {
            return null;
        }

        $emails = is_array($record['emails'] ?? null) ? $record['emails'] : [];
        $email = $emails[0]['email'] ?? null;
        $ownerId = $record['owner_id'] ?? $record['user_id'] ?? null;

        return [
            'id' => (string) ($record['id'] ?? ''),
            'name' => is_string($record['name'] ?? null) ? $record['name'] : null,
            'phone' => $expectedPhone ?? ($normalizedPhones[0] ?? null),
            'email' => is_string($email) ? $email : null,
            'organization_id' => is_string($record['organization_id'] ?? null) ? $record['organization_id'] : null,
            'owner_id' => is_string($ownerId) ? $ownerId : null,
            'fields' => is_array($record['custom_fields'] ?? null) ? $record['custom_fields'] : [],
            'updated_at' => is_string($record['updated_at'] ?? null) ? $record['updated_at'] : null,
        ];
    }

    /** @param array<string, mixed> $record
     * @return CrmDeal
     */
    private function normalizeDeal(array $record, ?string $personId = null): array
    {
        $contactIds = is_array($record['contact_ids'] ?? null) ? $record['contact_ids'] : [];

        return [
            'id' => (string) ($record['id'] ?? ''),
            'person_id' => $personId ?? (isset($contactIds[0]) ? (string) $contactIds[0] : null),
            'organization_id' => is_string($record['organization_id'] ?? null) ? $record['organization_id'] : null,
            'pipeline_id' => (string) ($record['pipeline_id'] ?? ''),
            'stage_id' => (string) ($record['stage_id'] ?? ''),
            'status' => $this->dealStatus($record['status'] ?? null),
            'loss_reason_id' => is_string($record['lost_reason_id'] ?? null) ? $record['lost_reason_id'] : null,
            'value' => is_numeric($record['total_price'] ?? null) ? (float) $record['total_price'] : null,
            'owner_id' => is_string($record['owner_id'] ?? null) ? $record['owner_id'] : null,
            'updated_at' => is_string($record['updated_at'] ?? null) ? $record['updated_at'] : null,
        ];
    }

    /** @param array<string, mixed> $deal */
    private function dealIncludesPerson(array $deal, string $personId): bool
    {
        $contactIds = is_array($deal['contact_ids'] ?? null) ? array_map('strval', $deal['contact_ids']) : [];

        return in_array($personId, $contactIds, true);
    }

    /** @return 'open'|'won'|'lost' */
    private function dealStatus(mixed $status): string
    {
        return match (strtolower(is_string($status) ? $status : 'open')) {
            'won', 'closed_won' => 'won',
            'lost', 'closed_lost' => 'lost',
            default => 'open',
        };
    }

    /** @return list<string> */
    private function phoneVariants(string $normalizedPhone): array
    {
        $digits = ltrim($normalizedPhone, '+');
        $variants = [$normalizedPhone, $digits];

        if (str_starts_with($digits, '55') && preg_match('/\A55(\d{2})(\d{8,9})\z/', $digits, $matches) === 1) {
            $areaCode = $matches[1];
            $subscriber = $matches[2];
            $domestic = $areaCode.$subscriber;
            $formatted = '('.$areaCode.') '.substr($subscriber, 0, -4).'-'.substr($subscriber, -4);
            $variants[] = $domestic;
            $variants[] = $formatted;
            $variants[] = $areaCode.' '.substr($subscriber, 0, -4).'-'.substr($subscriber, -4);
            $variants[] = '+55 '.$formatted;
            $variants[] = '55 '.$formatted;

            if (strlen($subscriber) === 9 && $subscriber[0] === '9') {
                $legacySubscriber = substr($subscriber, 1);
                $legacyFormatted = '('.$areaCode.') '.substr($legacySubscriber, 0, 4).'-'.substr($legacySubscriber, 4);
                $variants[] = $areaCode.$legacySubscriber;
                $variants[] = $legacyFormatted;
                $variants[] = '+55 '.$legacyFormatted;
            }
        }

        return array_values(array_unique($variants));
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
