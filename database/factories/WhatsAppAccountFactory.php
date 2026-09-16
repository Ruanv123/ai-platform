<?php

namespace Database\Factories;

use App\Enums\WhatsAppConnectionMethod;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppPhoneNumber;
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
            'whatsapp_phone_number_id' => WhatsAppPhoneNumber::factory(),
            'method' => WhatsAppConnectionMethod::Manual,
            'waba_id' => fake()->numerify('##########'),
            'access_token' => 'access-token-'.Str::random(20),
            'app_secret' => 'app-secret-'.Str::random(20),
            'verify_token' => Str::random(64),
            'webhook_verified_at' => null,
            'last_tested_at' => null,
        ];
    }
}
