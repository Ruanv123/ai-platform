<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Integration;
use App\Models\Shop;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

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
            'external_id' => 'gid://shopify/Collection/'.fake()->unique()->numerify('##########'),
            'title' => fake()->words(2, true),
        ];
    }
}
