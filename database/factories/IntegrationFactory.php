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
            'name' => 'Google Agenda',
            'type' => 'agenda',
            'provider' => 'google',
            'credentials' => [
                'access_token' => 'test-token',
                'email' => fake()->safeEmail(),
            ],
            'status' => 'never_tested',
            'status_message' => null,
            'last_tested_at' => null,
        ];
    }
}
