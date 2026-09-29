<?php

namespace App\Ecommerce;

use RuntimeException;

final class ShopifyThrottleException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Shopify API throttled the request.');
    }
}
