<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $team = Team::factory();
        $shop = Shop::factory()->for($team);

        return [
            'team_id' => $team,
            'shop_id' => $shop,
            'product_id' => Product::factory()->for($team)->for($shop),
            'external_id' => 'gid://shopify/ProductVariant/'.fake()->unique()->numerify('##########'),
            'sku' => fake()->bothify('SKU-#####'),
            'title' => 'Default Title',
            'options' => [],
            'price' => fake()->randomFloat(2, 1, 500),
            'compare_at_price' => null,
            'inventory_quantity' => fake()->numberBetween(0, 50),
            'continues_selling_when_out_of_stock' => false,
            'available' => true,
            'last_import_operation_id' => null,
        ];
    }
}
