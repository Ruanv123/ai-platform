<?php

namespace Database\Factories;

use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use App\Models\Agent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
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
            'name' => fake()->firstName(),
            'description' => fake()->sentence(),
            'objective' => AgentObjective::Support,
            'tone' => AgentTone::Friendly,
            'uses_emojis' => false,
            'response_length' => AgentResponseLength::Short,
            'welcome_message' => 'Olá! Como posso ajudar?',
            'instructions' => ['Seja objetiva.'],
            'rules' => ['Não invente informações.'],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the Agent is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
