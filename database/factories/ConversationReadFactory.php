<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\ConversationRead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationRead>
 */
class ConversationReadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => null,
            'contact_id' => Contact::factory(),
            'channel_id' => Channel::factory(),
            'last_read_message_id' => 0,
        ];
    }
}
