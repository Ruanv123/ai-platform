<?php

namespace App\Crm;

use App\Crm\Contracts\CrmProviderAdapter;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmLinkedDeal;
use App\Models\Integration;
use App\Models\Scopes\TeamScope;
use App\Support\E164PhoneNumber;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-import-type CrmDeal from CrmProviderAdapter
 * @phpstan-import-type CrmPerson from CrmProviderAdapter
 */
class CrmWebhookProcessor
{
    public function __construct(private CrmProviderAdapter $adapter) {}

    public function process(string $integrationId, int $teamId, string $resource, string $externalId, bool $deleted): void
    {
        $integration = Integration::withoutGlobalScopes()
            ->withTrashed()
            ->whereKey($integrationId)
            ->where('team_id', $teamId)
            ->where('type', 'crm')
            ->where('provider', $this->adapter->provider())
            ->first();

        if ($integration === null || $integration->trashed() || $integration->status !== 'connected') {
            return;
        }

        if ($resource === 'person') {
            $this->processPersonNotice($integration, $teamId, $externalId, $deleted);

            return;
        }

        if ($resource === 'deal') {
            $this->processDealNotice($integration, $teamId, $externalId, $deleted);
        }
    }

    private function processPersonNotice(Integration $integration, int $teamId, string $personId, bool $deleted): void
    {
        $link = $this->findPersonLink($integration, $teamId, $personId);
        if ($deleted) {
            $link?->delete();

            return;
        }

        if ($link === null) {
            return;
        }

        try {
            $this->adapter->findPerson($integration, $personId);
        } catch (CrmProviderException $exception) {
            if ($exception->category !== CrmErrorCategory::MissingReference) {
                throw $exception;
            }

            $link->delete();
        }
    }

    private function processDealNotice(Integration $integration, int $teamId, string $dealId, bool $deleted): void
    {
        if ($deleted) {
            $this->deleteLinkedDeal($integration, $teamId, $dealId);

            return;
        }

        try {
            $deal = $this->adapter->findDeal($integration, $dealId);
        } catch (CrmProviderException $exception) {
            if ($exception->category !== CrmErrorCategory::MissingReference) {
                throw $exception;
            }

            $this->deleteLinkedDeal($integration, $teamId, $dealId);

            return;
        }

        $personId = $deal['person_id'] ?? null;
        if (! is_string($personId) || $personId === '') {
            $this->deleteLinkedDeal($integration, $teamId, $dealId);

            return;
        }

        $personLink = $this->findPersonLink($integration, $teamId, $personId);
        if ($personLink === null) {
            try {
                $person = $this->adapter->findPerson($integration, $personId);
            } catch (CrmProviderException $exception) {
                if ($exception->category !== CrmErrorCategory::MissingReference) {
                    throw $exception;
                }

                $this->deleteLinkedDeal($integration, $teamId, $dealId);

                return;
            }

            $personLink = $this->findOrCreateContactLink($integration, $teamId, $person);
        }

        if ($personLink === null) {
            $this->deleteLinkedDeal($integration, $teamId, $dealId);

            return;
        }

        $this->saveLinkedDeal($integration, $teamId, $personLink, $deal);
    }

    private function findPersonLink(Integration $integration, int $teamId, string $personId): ?CrmContactLink
    {
        return CrmContactLink::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('integration_id', $integration->getKey())
            ->where('external_person_id', $personId)
            ->first();
    }

    /** @param CrmPerson $person */
    private function findOrCreateContactLink(Integration $integration, int $teamId, array $person): ?CrmContactLink
    {
        $personId = $person['id'] ?? null;
        $phone = E164PhoneNumber::formatForCountry((string) ($person['phone'] ?? ''), 'BR');
        if (! is_string($personId) || $personId === '' || $phone === null) {
            return null;
        }

        $existingLink = $this->findPersonLink($integration, $teamId, $personId);
        if ($existingLink !== null) {
            return $existingLink;
        }

        $contact = Contact::withoutGlobalScope(TeamScope::class)
            ->where('team_id', $teamId)
            ->where('phone', $phone)
            ->first();
        if ($contact === null) {
            return null;
        }

        try {
            return Cache::lock('crm:contact-link:'.$teamId.':'.$contact->getKey(), 120)
                ->block(10, function () use ($contact, $integration, $personId, $teamId): ?CrmContactLink {
                    $personLink = $this->findPersonLink($integration, $teamId, $personId);
                    if ($personLink !== null) {
                        return $personLink;
                    }

                    $contactLink = CrmContactLink::withoutGlobalScopes()
                        ->where('team_id', $teamId)
                        ->where('integration_id', $integration->getKey())
                        ->where('contact_id', $contact->getKey())
                        ->first();
                    if ($contactLink !== null) {
                        return $contactLink->external_person_id === $personId ? $contactLink : null;
                    }

                    return CrmContactLink::query()->create([
                        'team_id' => $teamId,
                        'contact_id' => $contact->getKey(),
                        'integration_id' => $integration->getKey(),
                        'external_person_id' => $personId,
                    ]);
                });
        } catch (LockTimeoutException) {
            throw new CrmProviderException(CrmErrorCategory::Temporary, 'O Vínculo do Contato ainda está sendo atualizado.', 5);
        }
    }

    /** @param CrmDeal $deal */
    private function saveLinkedDeal(Integration $integration, int $teamId, CrmContactLink $personLink, array $deal): void
    {
        if (($deal['id'] ?? '') === '' || ($deal['pipeline_id'] ?? '') === '' || ($deal['stage_id'] ?? '') === '') {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O CRM retornou uma negociação sem os identificadores esperados.');
        }

        CrmLinkedDeal::withoutGlobalScopes()->updateOrCreate(
            [
                'integration_id' => $integration->getKey(),
                'external_deal_id' => $deal['id'],
            ],
            [
                'team_id' => $teamId,
                'crm_contact_link_id' => $personLink->getKey(),
                'pipeline_id' => $deal['pipeline_id'],
                'stage_id' => $deal['stage_id'],
                'status' => $deal['status'],
                'value' => $deal['value'],
                'owner_id' => $deal['owner_id'],
                'provider_updated_at' => $deal['updated_at'],
            ],
        );
    }

    private function deleteLinkedDeal(Integration $integration, int $teamId, string $dealId): void
    {
        CrmLinkedDeal::withoutGlobalScopes()
            ->where('team_id', $teamId)
            ->where('integration_id', $integration->getKey())
            ->where('external_deal_id', $dealId)
            ->delete();
    }
}
