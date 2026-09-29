<?php

namespace App\Ecommerce;

use RuntimeException;

final class ShopifyIdTokenVerifier
{
    public function verify(string $token): string
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify é inválida.');
        }

        [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;
        $header = $this->decodeJson($encodedHeader);
        $claims = $this->decodeJson($encodedClaims);
        $signature = $this->decodeBase64Url($encodedSignature);
        $secret = config('services.shopify.client_secret');

        if (($header['alg'] ?? null) !== 'HS256' || ! is_string($secret) || $secret === '' || $signature === null) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify é inválida.');
        }

        $expectedSignature = hash_hmac('sha256', $encodedHeader.'.'.$encodedClaims, $secret, true);
        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify é inválida.');
        }

        $clientId = config('services.shopify.client_id');
        if (! is_string($clientId) || $clientId === '') {
            throw new RuntimeException('A configuração da aplicação Shopify está incompleta.');
        }

        $now = now()->timestamp;
        if (! is_numeric($claims['exp'] ?? null) || (int) $claims['exp'] <= $now) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify expirou.');
        }

        if (! is_numeric($claims['nbf'] ?? null) || (int) $claims['nbf'] > $now) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify ainda não é válida.');
        }

        if (($claims['aud'] ?? null) !== $clientId) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify pertence a outra aplicação.');
        }

        $issuerDomain = $this->domainFromClaim($claims['iss'] ?? null, '/admin');
        $destinationDomain = $this->domainFromClaim($claims['dest'] ?? null, '');

        if ($issuerDomain === null || $issuerDomain !== $destinationDomain) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify pertence a outra loja.');
        }

        return $issuerDomain;
    }

    private function decodeJson(string $value): array
    {
        $decoded = $this->decodeBase64Url($value);
        if ($decoded === null) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify é inválida.');
        }

        $data = json_decode($decoded, true);
        if (! is_array($data)) {
            throw new InvalidShopifyIdTokenException('A sessão da Shopify é inválida.');
        }

        return $data;
    }

    private function decodeBase64Url(string $value): ?string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding !== 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        return is_string($decoded) ? $decoded : null;
    }

    private function domainFromClaim(mixed $value, string $expectedPath): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts)
            || ($parts['scheme'] ?? null) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ($parts['path'] ?? '') !== $expectedPath
        ) {
            return null;
        }

        $domain = strtolower((string) ($parts['host'] ?? ''));
        if (preg_match('/\A[a-z0-9][a-z0-9-]*\.myshopify\.com\z/', $domain) !== 1) {
            return null;
        }

        return $domain;
    }
}
