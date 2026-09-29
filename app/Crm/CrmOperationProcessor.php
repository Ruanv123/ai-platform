<?php

namespace App\Crm;

use App\Crm\Contracts\CrmProviderAdapter;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmLinkedDeal;
use App\Models\CrmOperation;
use App\Models\Integration;
use App\Support\E164PhoneNumber;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * @phpstan-import-type CrmDeal from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmPerson from \App\Crm\Contracts\CrmProviderAdapter
 */
class CrmOperationProcessor
{
    public function __construct(private CrmProviderAdapter $adapter) {}

    public function process(CrmOperation $operation): CrmLinkedDeal
    {
        $integration = $operation->integration;
        $contact = $operation->contact;
        if (! $integration instanceof Integration || ! $contact instanceof Contact) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O Contato ou a Integração do CRM não está mais disponível.');
        }

        if ($operation->type !== 'guarantee_deal') {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O tipo desta Operação do CRM não é compatível.');
        }

        try {
            return Cache::lock('crm:contact-link:'.$contact->team_id.':'.$contact->getKey(), 120)
                ->block(30, fn (): CrmLinkedDeal => $this->ensureDeal($operation, $integration, $contact));
        } catch (LockTimeoutException) {
            throw new CrmProviderException(CrmErrorCategory::Temporary, 'Outra gravação do CRM está sendo concluída para este Contato.', 5);
        }
    }

    private function ensureDeal(CrmOperation $operation, Integration $integration, Contact $contact): CrmLinkedDeal
    {
        $personLink = $this->ensurePersonLink($integration, $contact, $operation->payload);
        $person = $this->adapter->findPerson($integration, $personLink->external_person_id);
        $missingPersonAttributes = [];
        if ((! is_string($person['name'] ?? null) || trim($person['name']) === '')
            && is_string($contact->name)
            && trim($contact->name) !== '') {
            $missingPersonAttributes['name'] = $contact->name;
        }

        $phone = E164PhoneNumber::formatForCountry($contact->phone, 'BR');
        if ((! is_string($person['phone'] ?? null) || trim($person['phone']) === '') && $phone !== null) {
            $missingPersonAttributes['phone'] = $phone;
        }

        if ($missingPersonAttributes !== []) {
            $person = $this->adapter->updatePerson($integration, $personLink->external_person_id, $missingPersonAttributes);
        }

        $payload = $operation->payload;
        $openDeal = $this->matchingOpenDeal($integration, $personLink->external_person_id, (string) $payload['pipeline_id']);

        if ($openDeal !== null) {
            $attributes = ['name' => (string) $payload['title']];
            if (is_numeric($payload['estimated_value'] ?? null)) {
                $attributes['value'] = (float) $payload['estimated_value'];
            }

            $deal = $this->adapter->updateDeal($integration, $openDeal['id'], $attributes);
        } else {
            $personOwnerId = $person['owner_id'] ?? null;
            $ownerId = is_string($personOwnerId) && $personOwnerId !== ''
                ? $personOwnerId
                : ($payload['default_owner_id'] ?? null);
            $attributes = [
                'name' => (string) $payload['title'],
                'person_id' => $personLink->external_person_id,
                'organization_id' => $person['organization_id'] ?? null,
                'pipeline_id' => (string) $payload['pipeline_id'],
                'stage_id' => (string) $payload['initial_stage_id'],
                'status' => 'open',
                'value' => is_numeric($payload['estimated_value'] ?? null) ? (float) $payload['estimated_value'] : null,
                'owner_id' => is_string($ownerId) && $ownerId !== '' ? $ownerId : null,
            ];
            $deal = $this->adapter->createDeal($integration, $attributes);
        }

        return $this->saveLinkedDeal($operation, $integration, $personLink, $deal);
    }

    /** @param array<string, mixed> $payload */
    private function ensurePersonLink(Integration $integration, Contact $contact, array $payload): CrmContactLink
    {
        $existingLink = CrmContactLink::query()
            ->where('team_id', $contact->team_id)
            ->where('contact_id', $contact->getKey())
            ->where('integration_id', $integration->getKey())
            ->first();
        if ($existingLink !== null) {
            return $existingLink;
        }

        $phone = E164PhoneNumber::formatForCountry($contact->phone, 'BR');
        if ($phone === null) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O Contato precisa ter um telefone válido para ser vinculado ao CRM.');
        }

        $person = $this->newestMatchingPerson($integration, $phone);
        if ($person === null) {
            $personAttributes = [
                'name' => $contact->name,
                'phone' => $phone,
                'owner_id' => is_string($payload['default_owner_id'] ?? null) ? $payload['default_owner_id'] : null,
            ];
            $person = $this->adapter->createPerson($integration, $personAttributes);
        }

        return CrmContactLink::query()->create([
            'team_id' => $contact->team_id,
            'contact_id' => $contact->getKey(),
            'integration_id' => $integration->getKey(),
            'external_person_id' => $person['id'],
        ]);
    }

    /** @return CrmPerson|null */
    private function newestMatchingPerson(Integration $integration, string $phone): ?array
    {
        $people = array_values(array_filter(
            $this->adapter->findPeopleByPhone($integration, $phone),
            fn (array $person): bool => E164PhoneNumber::formatForCountry((string) ($person['phone'] ?? ''), 'BR') === $phone,
        ));
        usort($people, fn (array $left, array $right): int => strcmp(
            (string) ($right['updated_at'] ?? ''),
            (string) ($left['updated_at'] ?? ''),
        ));

        return $people[0] ?? null;
    }

    /** @return CrmDeal|null */
    private function matchingOpenDeal(Integration $integration, string $personId, string $pipelineId): ?array
    {
        $deals = array_values(array_filter(
            $this->adapter->findOpenDeals($integration, $personId),
            fn (array $deal): bool => ($deal['pipeline_id'] ?? null) === $pipelineId && ($deal['status'] ?? null) === 'open',
        ));
        usort($deals, fn (array $left, array $right): int => strcmp(
            (string) ($right['updated_at'] ?? ''),
            (string) ($left['updated_at'] ?? ''),
        ));

        return $deals[0] ?? null;
    }

    /** @param CrmDeal $deal */
    private function saveLinkedDeal(
        CrmOperation $operation,
        Integration $integration,
        CrmContactLink $personLink,
        array $deal,
    ): CrmLinkedDeal {
        if (($deal['id'] ?? '') === '' || ($deal['pipeline_id'] ?? '') === '' || ($deal['stage_id'] ?? '') === '') {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O CRM retornou uma negociação sem os identificadores esperados.');
        }

        $linkedDeal = CrmLinkedDeal::query()->updateOrCreate(
            [
                'integration_id' => $integration->getKey(),
                'external_deal_id' => $deal['id'],
            ],
            [
                'team_id' => $operation->team_id,
                'crm_contact_link_id' => $personLink->getKey(),
                'pipeline_id' => $deal['pipeline_id'],
                'stage_id' => $deal['stage_id'],
                'status' => $deal['status'],
                'value' => $deal['value'],
                'owner_id' => $deal['owner_id'],
                'provider_updated_at' => $deal['updated_at'],
            ],
        );

        return $linkedDeal;
    }
}
