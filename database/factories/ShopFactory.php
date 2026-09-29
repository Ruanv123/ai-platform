<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Shop;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'integration_id' => Integration::factory()->for($team)->state(['type' => 'ecommerce', 'provider' => 'shopify']),
            'domain' => fake()->unique()->slug().'.myshopify.com',
            'name' => fake()->company(),
            'currency_code' => 'USD',
            'country_code' => 'US',
            'timezone' => 'America/New_York',
            'sync_status' => 'importing',
            'sync_progress' => 0,
            'last_synced_at' => null,
            'sync_error' => null,
            'suggested_action' => null,
            'reconciliation_cursor' => null,
        ];
    }
}
