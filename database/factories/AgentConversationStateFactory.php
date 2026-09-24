<?php

namespace Database\Factories;

use App\Models\AgentConversationState;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConversationState>
 */
class AgentConversationStateFactory extends Factory
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
            'contact_id' => Contact::factory(),
            'channel_id' => Channel::factory(),
            'is_paused' => false,
            'assigned_user_id' => null,
            'paused_at' => null,
            'expires_at' => null,
            'pause_duration_minutes' => null,
            'last_activity_at' => null,
        ];
    }
}
