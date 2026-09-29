<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Shop;
use App\Models\StoreCustomer;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreCustomerFactory extends Factory
{
    protected $model = StoreCustomer::class;

    public function definition(): array
    {
        $team = Team::factory();
        $integration = Integration::factory()->for($team)->state([
            'type' => 'ecommerce',
            'provider' => 'shopify',
        ]);

        return [
            'team_id' => $team,
            'shop_id' => Shop::factory()->for($team)->for($integration),
            'contact_id' => null,
            'external_id' => 'gid://shopify/Customer/'.fake()->unique()->numerify('##########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone_e164' => '+1415555'.fake()->numerify('####'),
            'total_spent' => fake()->randomFloat(2, 0, 5000),
            'currency_code' => 'USD',
            'last_import_operation_id' => null,
        ];
    }
}
