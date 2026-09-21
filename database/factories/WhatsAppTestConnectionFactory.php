<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\WhatsAppTestConnection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsAppTestConnection>
 */
class WhatsAppTestConnectionFactory extends Factory
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
            'service_url' => 'http://localhost:8080',
            'instance_name' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'access_token' => 'access-token-'.Str::random(20),
            'qrcode' => null,
        ];
    }
}
