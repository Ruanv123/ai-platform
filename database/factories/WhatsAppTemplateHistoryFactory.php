<?php

namespace Database\Factories;

use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WhatsAppTemplateHistory> */
class WhatsAppTemplateHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'whatsapp_template_id' => WhatsAppTemplate::factory(),
            'event' => 'status_changed',
            'old_status' => 'pending',
            'new_status' => 'approved',
            'old_category' => null,
            'new_category' => null,
            'reason' => null,
            'explanation' => null,
            'details' => [],
            'created_at' => now(),
        ];
    }
}
