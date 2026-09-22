<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Integration> */
class IntegrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->company().' API',
            'type' => 'http',
            'provider' => null,
            'credentials' => [
                'base_url' => 'https://api.example.test',
                'auth_type' => 'bearer',
                'token' => 'test-token',
            ],
            'status' => 'never_tested',
            'status_message' => null,
            'last_tested_at' => null,
        ];
    }
}
