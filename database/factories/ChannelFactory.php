<?php

namespace Database\Factories;

use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
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
            'type' => ChannelType::WhatsApp,
            'status' => ChannelStatus::NotConfigured,
            'last_error' => null,
        ];
    }

    /**
     * Indicate that the Channel is connected.
     */
    public function connected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ChannelStatus::Connected,
            'last_error' => null,
        ]);
    }

    /**
     * Indicate that the Channel is in error.
     */
    public function error(string $message = 'Falha na conexão.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ChannelStatus::Error,
            'last_error' => $message,
        ]);
    }

    /**
     * Indicate that the Channel has no Agent assigned.
     */
    public function withoutAgent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'agent_id' => null,
        ]);
    }
}
