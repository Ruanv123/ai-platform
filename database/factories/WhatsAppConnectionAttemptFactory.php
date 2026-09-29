<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class WhatsAppConnectionAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
        ];
    }
}
