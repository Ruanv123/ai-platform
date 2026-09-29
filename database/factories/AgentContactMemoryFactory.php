<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentContactMemoryFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'agent_id' => Agent::factory()->for($team),
            'contact_id' => Contact::factory()->for($team),
            'content' => fake()->sentence(),
        ];
    }
}
