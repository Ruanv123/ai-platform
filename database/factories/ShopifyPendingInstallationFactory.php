<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ShopifyPendingInstallationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'domain' => Str::lower(fake()->unique()->slug()).'.myshopify.com',
            'shop_name' => fake()->company(),
            'currency_code' => 'BRL',
            'country_code' => 'BR',
            'timezone' => 'America/Sao_Paulo',
            'credentials' => [
                'access_token' => Str::random(48),
                'refresh_token' => Str::random(48),
                'access_token_expires_at' => now()->addHour()->timestamp,
                'refresh_token_expires_at' => now()->addDays(30)->timestamp,
                'scopes' => ['read_products', 'read_inventory', 'read_customers', 'read_orders'],
            ],
            'handoff_token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ];
    }
}
