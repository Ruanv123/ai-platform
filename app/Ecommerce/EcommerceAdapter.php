<?php

namespace App\Ecommerce;

use App\Ecommerce\Data\EcommerceConnection;
use App\Enums\EcommerceProvider;
use App\Models\Shop;

interface EcommerceAdapter
{
    public function provider(): EcommerceProvider;

    public function authorizationUrl(string $storeDomain, string $state): string;

    public function validateAuthorizationCallback(array $parameters): bool;

    public function completeAuthorization(string $storeDomain, string $authorizationCode): EcommerceConnection;

    public function refreshAccessToken(Shop $shop): string;

    public function queryShop(Shop $shop, string $query, array $variables = []): array;

    public function validateWebhookSignature(string $body, string $signature): bool;

    public function identifyShopByDomain(string $domain): ?Shop;
}
