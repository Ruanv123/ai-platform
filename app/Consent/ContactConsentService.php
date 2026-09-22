<?php

namespace App\Consent;

use App\Enums\ConsentAction;
use App\Enums\ConsentPurpose;
use App\Models\Contact;
use App\Models\ContactConsent;
use Illuminate\Support\Collection;

final class ContactConsentService
{
    /**
     * Record a new immutable consent decision.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Contact $contact,
        ConsentPurpose $purpose,
        ConsentAction $action,
        string $source,
        array $metadata = [],
    ): ContactConsent {
        return ContactConsent::withoutGlobalScopes()->create([
            'team_id' => $contact->team_id,
            'contact_id' => $contact->getKey(),
            'purpose' => $purpose,
            'action' => $action,
            'source' => $source,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function grant(Contact $contact, ConsentPurpose $purpose, string $source, array $metadata = []): ContactConsent
    {
        return $this->record($contact, $purpose, ConsentAction::Granted, $source, $metadata);
    }

    /** @param array<string, mixed> $metadata */
    public function revoke(Contact $contact, ConsentPurpose $purpose, string $source, array $metadata = []): ContactConsent
    {
        return $this->record($contact, $purpose, ConsentAction::Revoked, $source, $metadata);
    }

    public function isGranted(Contact $contact, ConsentPurpose $purpose): bool
    {
        return $this->latest($contact, $purpose)?->action === ConsentAction::Granted;
    }

    /**
     * @return array<string, array{purpose: string, label: string, granted: bool, action: string|null, source: string|null, at: string|null}>
     */
    public function snapshot(Contact $contact): array
    {
        $events = ContactConsent::withoutGlobalScopes()
            ->where('team_id', $contact->team_id)
            ->where('contact_id', $contact->getKey())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (ContactConsent $consent): string => $consent->purpose->value);

        return collect(ConsentPurpose::cases())
            ->mapWithKeys(function (ConsentPurpose $purpose) use ($events): array {
                $consent = $events->first(fn (ContactConsent $candidate): bool => $candidate->purpose === $purpose);

                return [$purpose->value => [
                    'purpose' => $purpose->value,
                    'label' => $purpose->label(),
                    'granted' => $consent?->action === ConsentAction::Granted,
                    'action' => $consent?->action?->value,
                    'source' => $consent?->source,
                    'at' => $consent?->created_at?->toIso8601String(),
                ]];
            })
            ->all();
    }

    /** @return Collection<int, ContactConsent> */
    public function history(Contact $contact): Collection
    {
        return ContactConsent::withoutGlobalScopes()
            ->where('team_id', $contact->team_id)
            ->where('contact_id', $contact->getKey())
            ->latest('created_at')
            ->latest('id')
            ->get();
    }

    private function latest(Contact $contact, ConsentPurpose $purpose): ?ContactConsent
    {
        return ContactConsent::withoutGlobalScopes()
            ->where('team_id', $contact->team_id)
            ->where('contact_id', $contact->getKey())
            ->where('purpose', $purpose)
            ->latest('created_at')
            ->latest('id')
            ->first();
    }
}
