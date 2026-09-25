<?php

namespace Database\Factories;

use App\Enums\WhatsAppConnectionMethod;
use App\Models\Team;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppAccount>
 */
class WhatsAppAccountFactory extends Factory
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
            'connection_method' => WhatsAppConnectionMethod::OwnApp,
            'waba_id' => fake()->unique()->numerify('##########'),
            'business_id' => null,
            'name' => null,
            'app_id' => null,
            'access_token' => 'access-token-'.Str::random(20),
            'app_secret' => 'app-secret-'.Str::random(20),
            'verify_token' => Str::random(64),
            'webhook_verified_at' => null,
            'subscribed_at' => null,
            'ban_state' => null,
            'review_status' => null,
            'last_tested_at' => null,
        ];
    }

    public function techProvider(): static
    {
        return $this->state(fn (): array => [
            'connection_method' => WhatsAppConnectionMethod::TechProvider,
            'app_id' => null,
            'app_secret' => null,
            'verify_token' => null,
        ]);
    }
}
