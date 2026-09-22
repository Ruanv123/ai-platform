<?php

namespace Database\Factories;

use App\Models\FlowEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlowEvent> */
class FlowEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'payload_schema' => ['type' => 'object'],
        ];
    }
}
