<?php

namespace Database\Factories;

use App\Enums\ConsentAction;
use App\Enums\ConsentPurpose;
use App\Models\Contact;
use App\Models\ContactConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContactConsent> */
class ContactConsentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => fn (array $attributes): int => Contact::withoutGlobalScopes()->find($attributes['contact_id'])->team_id,
            'contact_id' => Contact::factory(),
            'purpose' => ConsentPurpose::Utility,
            'action' => ConsentAction::Granted,
            'source' => 'import',
            'metadata' => [],
            'created_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['action' => ConsentAction::Revoked]);
    }

    public function forPurpose(ConsentPurpose $purpose): static
    {
        return $this->state(fn (): array => ['purpose' => $purpose]);
    }
}
