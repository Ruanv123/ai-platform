<?php

namespace App\Ecommerce\Data;

final readonly class EcommerceConnection
{
    public function __construct(
        public string $domain,
        public string $name,
        public string $currencyCode,
        public ?string $countryCode,
        public ?string $timezone,
        public array $credentials,
    ) {}
}
