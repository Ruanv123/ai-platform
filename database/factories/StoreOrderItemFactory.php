<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreOrderItemFactory extends Factory
{
    protected $model = StoreOrderItem::class;

    public function definition(): array
    {
        $team = Team::factory();
        $integration = Integration::factory()->for($team)->state([
            'type' => 'ecommerce',
            'provider' => 'shopify',
        ]);
        $shop = Shop::factory()->for($team)->for($integration);
        $product = Product::factory()->for($team)->for($shop);

        return [
            'team_id' => $team,
            'shop_id' => $shop,
            'store_order_id' => StoreOrder::factory()->for($team)->for($shop),
            'product_variant_id' => ProductVariant::factory()->for($team)->for($shop)->for($product),
            'external_id' => 'gid://shopify/LineItem/'.fake()->unique()->numerify('##########'),
            'title' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 1, 500),
            'last_import_operation_id' => null,
        ];
    }
}
