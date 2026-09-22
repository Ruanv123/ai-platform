<?php

namespace Database\Factories;

use App\Enums\WhatsAppTemplateCategory;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WhatsAppTemplate> */
class WhatsAppTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'whatsapp_account_id' => WhatsAppAccount::factory(),
            'meta_template_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'name' => 'consulta_'.Str::lower(Str::random(6)),
            'language' => 'pt_BR',
            'status' => WhatsAppTemplateStatus::Approved,
            'requested_category' => WhatsAppTemplateCategory::Utility,
            'assigned_category' => WhatsAppTemplateCategory::Utility,
            'body' => 'Olá {{1}}, seu atendimento está confirmado.',
            'header_type' => null,
            'header_text' => null,
            'footer' => null,
            'buttons' => [],
            'variables' => [['position' => 1, 'component' => 'body']],
            'rejection_reason' => null,
            'rejection_explanation' => null,
            'last_synced_at' => now(),
            'submitted_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'meta_template_id' => null,
            'status' => WhatsAppTemplateStatus::Draft,
            'assigned_category' => null,
            'submitted_at' => null,
        ]);
    }

    public function rejected(string $reason = 'INVALID_FORMAT'): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsAppTemplateStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }
}
