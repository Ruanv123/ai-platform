<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppPhoneNumber>
 */
class WhatsAppPhoneNumberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'whatsapp_account_id' => function (array $attributes): int {
                $channel = Channel::query()->with('team')->findOrFail($attributes['channel_id']);

                return (int) WhatsAppAccount::factory()
                    ->for($channel->team, 'team')
                    ->create()
                    ->getKey();
            },
            'phone_number_id' => fake()->unique()->numerify('###############'),
            'display_phone_number' => null,
            'verified_name' => null,
            'quality_rating' => null,
            'name_status' => null,
            'messaging_limit_tier' => null,
            'registration_pin' => null,
            'registered_at' => null,
        ];
    }
}
