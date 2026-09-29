<?php

namespace App\Ecommerce;

use RuntimeException;

final class ShopifyAccessRevokedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A autorização da Shopify foi revogada. Reconecte a Loja para continuar.');
    }
}
