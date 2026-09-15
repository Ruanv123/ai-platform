<?php

namespace Database\Factories;

use App\Enums\WhatsAppConnectionMethod;
use App\Enums\WhatsAppConnectionStatus;
use App\Models\Agent;
use App\Models\Team;
use App\Models\WhatsAppNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppNumber>
 */
class WhatsAppNumberFactory extends Factory
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
            'agent_id' => null,
            'method' => WhatsAppConnectionMethod::Manual,
            'waba_id' => fake()->numerify('##########'),
            'phone_number_id' => fake()->unique()->numerify('###############'),
            'access_token' => 'access-token-'.Str::random(20),
            'app_secret' => 'app-secret-'.Str::random(20),
            'verify_token' => Str::random(64),
            'status' => WhatsAppConnectionStatus::Unverified,
            'last_error' => null,
            'display_phone_number' => null,
            'verified_name' => null,
            'quality_rating' => null,
            'last_tested_at' => null,
            'webhook_verified_at' => null,
        ];
    }

    /**
     * Link this number to an Agent.
     */
    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (array $attributes): array => [
            'team_id' => $agent->team_id,
            'agent_id' => $agent->id,
        ]);
    }
}
