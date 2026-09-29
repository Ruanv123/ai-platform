<?php

namespace App\Ecommerce;

use App\Models\Shop;
use RuntimeException;

final class ShopifyReplacementConfirmationRequired extends RuntimeException
{
    public function __construct(public Shop $shop)
    {
        parent::__construct('A Empresa já possui uma Loja Shopify conectada.');
    }
}
