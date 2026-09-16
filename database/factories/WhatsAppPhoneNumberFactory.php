<?php

namespace Database\Factories;

use App\Models\Channel;
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
            'phone_number_id' => fake()->unique()->numerify('###############'),
            'display_phone_number' => null,
            'verified_name' => null,
            'quality_rating' => null,
        ];
    }

    /**
     * Attach a generated WhatsApp account to the number.
     */
    public function withAccount(): static
    {
        return $this->has(WhatsAppAccountFactory::new(), 'whatsappAccount');
    }
}
