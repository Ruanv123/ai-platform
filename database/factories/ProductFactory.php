<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

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
            'external_id' => 'gid://shopify/Product/'.fake()->unique()->numerify('##########'),
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'handle' => fake()->slug(),
            'url' => fake()->url(),
            'status' => 'ACTIVE',
            'online_store_published' => true,
            'embedding' => null,
            'source_hash' => null,
            'last_import_operation_id' => null,
        ];
    }
}
