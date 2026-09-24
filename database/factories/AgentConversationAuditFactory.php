<?php

namespace Database\Factories;

use App\Models\AgentConversationAudit;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentConversationAudit>
 */
class AgentConversationAuditFactory extends Factory
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
            'previous_user_id' => null,
            'new_user_id' => null,
            'actor_user_id' => null,
            'action' => 'paused',
        ];
    }
}
