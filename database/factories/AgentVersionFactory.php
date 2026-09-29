<?php

namespace Database\Factories;

use App\Enums\AgentObjective;
use App\Models\Agent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentVersionFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'agent_id' => Agent::factory()->for($team),
            'number' => 1,
            'objective' => AgentObjective::Support,
            'ecommerce_tools' => [
                'search_products' => null,
                'send_purchase_link' => null,
            ],
            'published_at' => now(),
        ];
    }
}
