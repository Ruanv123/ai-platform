<?php

namespace App\Ecommerce;

use App\Ecommerce\Data\EcommerceConnection;
use App\Enums\EcommerceProvider;
use App\Models\Integration;
use App\Models\Scopes\TeamScope;
use App\Models\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class ShopifyAdapter implements EcommerceAdapter
{
    private const SHOP_DOMAIN_PATTERN = '/\A[a-zA-Z0-9][a-zA-Z0-9-]*\.myshopify\.com\z/i';

    private const SHOP_QUERY = <<<'GRAPHQL'
        query ShopifyShopDetails {
          shop {
            name
            myshopifyDomain
            currencyCode
            ianaTimezone
            shopAddress {
              countryCodeV2
            }
          }
        }
        GRAPHQL;

    public function provider(): EcommerceProvider
    {
        return EcommerceProvider::Shopify;
    }

    public function authorizationUrl(string $storeDomain, string $state): string
    {
        if (! $this->isValidShopDomain($storeDomain)) {
            throw new RuntimeException('Informe o domínio myshopify.com da loja.');
        }

        $clientId = config('services.shopify.client_id');
        $redirectUri = config('services.shopify.redirect');

        if (! is_string($clientId) || $clientId === '' || ! is_string($redirectUri) || $redirectUri === '') {
            throw new RuntimeException('A configuração de conexão da Shopify está incompleta.');
        }

        $query = http_build_query([
            'client_id' => $clientId,
            'scope' => implode(',', $this->scopes()),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://'.$storeDomain.'/admin/oauth/authorize?'.$query;
    }

    public function validateAuthorizationCallback(array $parameters): bool
    {
        $signature = $parameters['hmac'] ?? null;
        $clientSecret = config('services.shopify.client_secret');

        if (! is_string($signature) || ! preg_match('/\A[a-f0-9]{64}\z/i', $signature) || ! is_string($clientSecret) || $clientSecret === '') {
            return false;
        }

        unset($parameters['hmac']);
        ksort($parameters, SORT_STRING);

        $parts = [];
        foreach ($parameters as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                return false;
            }

            $parts[] = $key.'='.$value;
        }

        return hash_equals(hash_hmac('sha256', implode('&', $parts), $clientSecret), $signature);
    }

    public function completeAuthorization(string $storeDomain, string $authorizationCode): EcommerceConnection
    {
        if (! $this->isValidShopDomain($storeDomain)) {
            throw new RuntimeException('O domínio retornado pela Shopify é inválido.');
        }

        $clientId = config('services.shopify.client_id');
        $clientSecret = config('services.shopify.client_secret');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException('A configuração de conexão da Shopify está incompleta.');
        }

        $tokenResponse = Http::connectTimeout(3)
            ->timeout(15)
            ->asForm()
            ->acceptJson()
            ->post('https://'.$storeDomain.'/admin/oauth/access_token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $authorizationCode,
                'expiring' => '1',
            ]);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('A Shopify não concluiu a autorização da loja. Tente conectar novamente.');
        }

        $token = $tokenResponse->json();
        if (! is_array($token)) {
            throw new RuntimeException('A Shopify retornou uma resposta de autorização inválida.');
        }

        $accessToken = data_get($token, 'access_token');
        $refreshToken = data_get($token, 'refresh_token');
        $accessTokenLifetime = data_get($token, 'expires_in');
        $refreshTokenLifetime = data_get($token, 'refresh_token_expires_in');
        $grantedScopes = data_get($token, 'scope');

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($refreshToken) || $refreshToken === '' || ! is_numeric($accessTokenLifetime) || ! is_numeric($refreshTokenLifetime) || ! is_string($grantedScopes)) {
            throw new RuntimeException('A Shopify não retornou as credenciais temporárias esperadas.');
        }

        $scopes = array_values(array_filter(array_map('trim', explode(',', $grantedScopes))));
        if (array_diff($this->scopes(), $scopes) !== [] || array_diff($scopes, $this->scopes()) !== []) {
            throw new RuntimeException('A autorização da Shopify precisa conceder somente as permissões de leitura solicitadas.');
        }

        $shopData = data_get($this->requestGraphql($storeDomain, $accessToken, self::SHOP_QUERY), 'shop');
        if (! is_array($shopData)) {
            throw new RuntimeException('A Shopify não retornou os dados necessários para conectar esta loja.');
        }

        $shopDomain = data_get($shopData, 'myshopifyDomain');
        $shopName = data_get($shopData, 'name');
        $currencyCode = data_get($shopData, 'currencyCode');
        $countryCode = data_get($shopData, 'shopAddress.countryCodeV2');
        $timezone = data_get($shopData, 'ianaTimezone');

        if (! is_string($shopDomain) || strtolower($shopDomain) !== strtolower($storeDomain) || ! is_string($shopName) || ! is_string($currencyCode) || ! is_string($timezone) || ($countryCode !== null && ! is_string($countryCode))) {
            throw new RuntimeException('A Shopify não retornou os dados necessários para conectar esta loja.');
        }

        $connectedAt = now();

        return new EcommerceConnection(
            domain: strtolower($shopDomain),
            name: $shopName,
            currencyCode: $currencyCode,
            countryCode: $countryCode,
            timezone: $timezone,
            credentials: [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'access_token_expires_at' => $connectedAt->copy()->addSeconds((int) $accessTokenLifetime)->timestamp,
                'refresh_token_expires_at' => $connectedAt->copy()->addSeconds((int) $refreshTokenLifetime)->timestamp,
                'scopes' => $scopes,
            ],
        );
    }

    public function completeEmbeddedAuthorization(string $storeDomain, string $idToken): EcommerceConnection
    {
        if (! $this->isValidShopDomain($storeDomain)) {
            throw new InvalidShopifyIdTokenException('O domínio retornado pela Shopify é inválido.');
        }

        $clientId = config('services.shopify.client_id');
        $clientSecret = config('services.shopify.client_secret');
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException('A configuração de conexão da Shopify está incompleta.');
        }

        $tokenResponse = Http::connectTimeout(3)
            ->timeout(15)
            ->asForm()
            ->acceptJson()
            ->post('https://'.$storeDomain.'/admin/oauth/access_token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
                'subject_token' => $idToken,
                'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token',
                'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
                'expiring' => '1',
            ]);

        if ($tokenResponse->status() === 400) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify expirou.');
        }

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('A Shopify não concluiu a autorização da loja. Tente novamente.');
        }

        $token = $tokenResponse->json();
        if (! is_array($token)) {
            throw new RuntimeException('A Shopify retornou uma resposta de autorização inválida.');
        }

        $accessToken = data_get($token, 'access_token');
        $refreshToken = data_get($token, 'refresh_token');
        $accessTokenLifetime = data_get($token, 'expires_in');
        $refreshTokenLifetime = data_get($token, 'refresh_token_expires_in');
        $grantedScopes = data_get($token, 'scope');

        if (! is_string($accessToken) || $accessToken === '' || ! is_string($refreshToken) || $refreshToken === '' || ! is_numeric($accessTokenLifetime) || ! is_numeric($refreshTokenLifetime) || ! is_string($grantedScopes)) {
            throw new RuntimeException('A Shopify não retornou as credenciais temporárias esperadas.');
        }

        $scopes = array_values(array_filter(array_map('trim', explode(',', $grantedScopes))));
        if (array_diff($this->scopes(), $scopes) !== [] || array_diff($scopes, $this->scopes()) !== []) {
            throw new RuntimeException('A autorização da Shopify precisa conceder somente as permissões de leitura solicitadas.');
        }

        $shopData = data_get($this->requestGraphql($storeDomain, $accessToken, self::SHOP_QUERY), 'shop');
        if (! is_array($shopData)) {
            throw new RuntimeException('A Shopify não retornou os dados necessários para conectar esta loja.');
        }

        $shopDomain = data_get($shopData, 'myshopifyDomain');
        $shopName = data_get($shopData, 'name');
        $currencyCode = data_get($shopData, 'currencyCode');
        $countryCode = data_get($shopData, 'shopAddress.countryCodeV2');
        $timezone = data_get($shopData, 'ianaTimezone');

        if (! is_string($shopDomain) || strtolower($shopDomain) !== strtolower($storeDomain) || ! is_string($shopName) || ! is_string($currencyCode) || ! is_string($timezone) || ($countryCode !== null && ! is_string($countryCode))) {
            throw new RuntimeException('A Shopify não retornou os dados necessários para conectar esta loja.');
        }

        $connectedAt = now();

        return new EcommerceConnection(
            domain: strtolower($shopDomain),
            name: $shopName,
            currencyCode: $currencyCode,
            countryCode: $countryCode,
            timezone: $timezone,
            credentials: [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'access_token_expires_at' => $connectedAt->copy()->addSeconds((int) $accessTokenLifetime)->timestamp,
                'refresh_token_expires_at' => $connectedAt->copy()->addSeconds((int) $refreshTokenLifetime)->timestamp,
                'scopes' => $scopes,
            ],
        );
    }

    public function refreshAccessToken(Shop $shop): string
    {
        $accessToken = DB::transaction(function () use ($shop): ?string {
            $integration = Integration::withTrashed()
                ->whereKey($shop->integration_id)
                ->where('team_id', $shop->team_id)
                ->lockForUpdate()
                ->first();

            if ($integration === null || $integration->trashed() || $integration->status === 'error') {
                return null;
            }

            $credentials = $integration->credentials;
            $currentToken = $integration->credential('access_token');
            $expiresAt = $integration->credential('access_token_expires_at');

            if (is_string($currentToken) && is_numeric($expiresAt) && (int) $expiresAt > now()->addSeconds(60)->timestamp) {
                return $currentToken;
            }

            $refreshToken = $integration->credential('refresh_token');
            if (! is_string($refreshToken) || $refreshToken === '') {
                $this->markConnectionAsExpired($shop, $integration);

                return null;
            }

            $clientId = config('services.shopify.client_id');
            $clientSecret = config('services.shopify.client_secret');
            if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
                $this->markConnectionAsExpired($shop, $integration);

                return null;
            }

            try {
                $response = Http::connectTimeout(3)
                    ->timeout(15)
                    ->asForm()
                    ->acceptJson()
                    ->post('https://'.$shop->domain.'/admin/oauth/access_token', [
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refreshToken,
                    ]);
            } catch (Throwable) {
                $this->markConnectionAsExpired($shop, $integration);

                return null;
            }

            $token = $response->json();
            $newAccessToken = is_array($token) ? data_get($token, 'access_token') : null;
            $newRefreshToken = is_array($token) ? data_get($token, 'refresh_token') : null;
            $accessTokenLifetime = is_array($token) ? data_get($token, 'expires_in') : null;
            $refreshTokenLifetime = is_array($token) ? data_get($token, 'refresh_token_expires_in') : null;

            if (! $response->successful() || ! is_string($newAccessToken) || $newAccessToken === '' || ! is_string($newRefreshToken) || $newRefreshToken === '' || ! is_numeric($accessTokenLifetime) || ! is_numeric($refreshTokenLifetime)) {
                $this->markConnectionAsExpired($shop, $integration);

                return null;
            }

            $refreshedAt = now();
            $credentials = array_replace($credentials, [
                'access_token' => $newAccessToken,
                'refresh_token' => $newRefreshToken,
                'access_token_expires_at' => $refreshedAt->copy()->addSeconds((int) $accessTokenLifetime)->timestamp,
                'refresh_token_expires_at' => $refreshedAt->copy()->addSeconds((int) $refreshTokenLifetime)->timestamp,
            ]);

            $integration->forceFill([
                'credentials' => $credentials,
                'status' => 'connected',
                'status_message' => null,
            ])->save();

            return $newAccessToken;
        });

        if (! is_string($accessToken)) {
            throw new RuntimeException('A autorização da Shopify expirou. Reconecte a Loja para continuar.');
        }

        return $accessToken;
    }

    public function queryShop(Shop $shop, string $query, array $variables = []): array
    {
        return $this->requestGraphql($shop->domain, $this->refreshAccessToken($shop), $query, $variables);
    }

    public function validateWebhookSignature(string $body, string $signature): bool
    {
        $clientSecret = config('services.shopify.client_secret');

        return is_string($clientSecret) && $clientSecret !== '' && hash_equals(
            base64_encode(hash_hmac('sha256', $body, $clientSecret, true)),
            $signature,
        );
    }

    public function identifyShopByDomain(string $domain): ?Shop
    {
        return Shop::withoutGlobalScope(TeamScope::class)
            ->where('domain', strtolower($domain))
            ->first();
    }

    private function isValidShopDomain(string $domain): bool
    {
        return preg_match(self::SHOP_DOMAIN_PATTERN, $domain) === 1;
    }

    private function scopes(): array
    {
        return config('services.shopify.scopes', []);
    }

    private function requestGraphql(string $domain, string $accessToken, string $query, array $variables = []): array
    {
        $apiVersion = config('services.shopify.api_version');
        if (! is_string($apiVersion) || ! preg_match('/\A\d{4}-\d{2}\z/', $apiVersion)) {
            throw new RuntimeException('A versão da API Admin da Shopify está inválida.');
        }

        $response = Http::connectTimeout(3)
            ->timeout(15)
            ->acceptJson()
            ->withHeaders(['X-Shopify-Access-Token' => $accessToken])
            ->post('https://'.$domain.'/admin/api/'.$apiVersion.'/graphql.json', [
                'query' => $query,
                'variables' => $variables,
            ]);

        return $this->extractGraphqlData($response);
    }

    private function extractGraphqlData(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException('A Shopify não respondeu à consulta da loja. Tente novamente.');
        }

        $body = $response->json();
        if (! is_array($body) || data_get($body, 'errors') !== null || ! is_array(data_get($body, 'data'))) {
            throw new RuntimeException('A Shopify não retornou os dados solicitados para a loja.');
        }

        return data_get($body, 'data');
    }

    private function markConnectionAsExpired(Shop $shop, Integration $integration): void
    {
        $message = 'A autorização da Shopify expirou. Reconecte a Loja.';
        $integration->forceFill([
            'status' => 'error',
            'status_message' => $message,
        ])->save();
        $shop->forceFill([
            'sync_status' => 'error',
            'sync_error' => $message,
            'suggested_action' => 'Reconecte a Loja pela tela de Integrações.',
        ])->save();
    }
}
