<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\StoreOrder;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreOrderFactory extends Factory
{
    protected $model = StoreOrder::class;

    public function definition(): array
    {
        $team = Team::factory();
        $integration = Integration::factory()->for($team)->state([
            'type' => 'ecommerce',
            'provider' => 'shopify',
        ]);
        $shop = Shop::factory()->for($team)->for($integration);

        return [
            'team_id' => $team,
            'shop_id' => $shop,
            'store_customer_id' => StoreCustomer::factory()->for($team)->for($shop),
            'customer_external_id' => null,
            'external_id' => 'gid://shopify/Order/'.fake()->unique()->numerify('##########'),
            'order_number' => '#'.fake()->unique()->numerify('#####'),
            'total_amount' => fake()->randomFloat(2, 1, 5000),
            'currency_code' => 'USD',
            'financial_status' => 'PAID',
            'fulfillment_status' => 'FULFILLED',
            'ordered_at' => now(),
            'last_import_operation_id' => null,
        ];
    }
}
