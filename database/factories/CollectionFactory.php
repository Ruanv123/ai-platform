<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collection>
 */
class CollectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'is_default' => false,
        ];
    }

    /**
     * Indicate that the collection is the default collection.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Geral',
            'is_default' => true,
        ]);
    }
}
