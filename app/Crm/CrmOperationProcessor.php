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
    public function __construct(
        private CrmProviderAdapter $adapter,
        private CrmFieldMappingService $fieldMappings,
    ) {}

    public function process(CrmOperation $operation): ?CrmLinkedDeal
    {
        $integration = $operation->integration;
        $contact = $operation->contact;
        if (! $integration instanceof Integration || ! $contact instanceof Contact) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O Contato ou a Integração do CRM não está mais disponível.');
        }

        if (! in_array($operation->type, ['ensure_person', 'guarantee_deal', 'move_deal', 'register_note', 'update_person_fields'], true)) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O tipo desta Operação do CRM não é compatível.');
        }

        try {
            return Cache::lock('crm:contact-link:'.$contact->team_id.':'.$contact->getKey(), 120)
                ->block(30, function () use ($operation, $integration, $contact): ?CrmLinkedDeal {
                    if ($operation->type === 'ensure_person') {
                        $this->ensurePerson($operation, $integration, $contact);

                        return null;
                    }
                    if ($operation->type === 'update_person_fields') {
                        $this->updatePersonFields($operation, $integration, $contact);

                        return null;
                    }

                    return match ($operation->type) {
                        'guarantee_deal' => $this->ensureDeal($operation, $integration, $contact),
                        'move_deal' => $this->moveDeal($operation, $integration, $contact),
                        'register_note' => $this->registerNote($operation, $integration, $contact),
                        default => throw new CrmProviderException(CrmErrorCategory::Definitive, 'O tipo desta Operação do CRM não é compatível.'),
                    };
                });
        } catch (LockTimeoutException) {
            throw new CrmProviderException(CrmErrorCategory::Temporary, 'Outra gravação do CRM está sendo concluída para este Contato.', 5);
        }
    }

    private function ensureDeal(CrmOperation $operation, Integration $integration, Contact $contact): CrmLinkedDeal
    {
        $personLink = $this->ensurePersonLink($operation, $integration, $contact, $operation->payload);
        $person = $this->updateMissingPersonIdentity($integration, $contact, $personLink);
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

    private function moveDeal(CrmOperation $operation, Integration $integration, Contact $contact): CrmLinkedDeal
    {
        $payload = $operation->payload;
        $personLink = $this->ensurePersonLink(
            $operation,
            $integration,
            $contact,
            $payload,
            is_string($payload['external_person_id'] ?? null) ? $payload['external_person_id'] : null,
        );
        $dealId = is_string($payload['external_deal_id'] ?? null) ? $payload['external_deal_id'] : '';
        $pipelineId = is_string($payload['pipeline_id'] ?? null) ? $payload['pipeline_id'] : '';
        $stageId = is_string($payload['target_stage_id'] ?? null) ? $payload['target_stage_id'] : '';
        if ($dealId === '' || $pipelineId === '' || $stageId === '') {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'A Operação não contém os dados necessários para mover o Negócio.');
        }

        $deal = $this->adapter->findDeal($integration, $dealId);
        if (($deal['status'] ?? null) !== 'open'
            || ($deal['pipeline_id'] ?? null) !== $pipelineId
            || ($deal['person_id'] ?? null) !== $personLink->external_person_id) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O Negócio aberto deste Contato não está mais disponível no Funil de vendas do Agente.');
        }

        $movedDeal = $this->adapter->moveDeal($integration, $dealId, $stageId);

        return $this->saveLinkedDeal($operation, $integration, $personLink, $movedDeal);
    }

    private function registerNote(CrmOperation $operation, Integration $integration, Contact $contact): ?CrmLinkedDeal
    {
        $payload = $operation->payload;
        $personLink = $this->ensurePersonLink(
            $operation,
            $integration,
            $contact,
            $payload,
            is_string($payload['external_person_id'] ?? null) ? $payload['external_person_id'] : null,
        );
        $body = is_string($payload['body'] ?? null) ? trim($payload['body']) : '';
        if ($body === '') {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'A Operação não contém o texto da nota.');
        }

        $pipelineId = is_string($payload['pipeline_id'] ?? null) ? $payload['pipeline_id'] : null;
        $deal = $pipelineId === null
            ? null
            : $this->matchingOpenDeal($integration, $personLink->external_person_id, $pipelineId);

        $this->adapter->createNote(
            $integration,
            $personLink->external_person_id,
            $body,
            is_array($deal) && is_string($deal['id'] ?? null) ? $deal['id'] : null,
        );

        if ($deal === null) {
            return null;
        }

        return $this->saveLinkedDeal($operation, $integration, $personLink, $deal);
    }

    private function ensurePerson(CrmOperation $operation, Integration $integration, Contact $contact): void
    {
        $personLink = $this->ensurePersonLink($operation, $integration, $contact, $operation->payload);
        $this->updateMissingPersonIdentity($integration, $contact, $personLink);
    }

    private function updateMissingPersonIdentity(
        Integration $integration,
        Contact $contact,
        CrmContactLink $personLink,
    ): array {
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

        return $person;
    }

    /** @param array<string, mixed> $payload */
    private function ensurePersonLink(
        CrmOperation $operation,
        Integration $integration,
        Contact $contact,
        array $payload,
        ?string $preferredPersonId = null,
    ): CrmContactLink {
        $existingLink = CrmContactLink::query()
            ->where('team_id', $contact->team_id)
            ->where('contact_id', $contact->getKey())
            ->where('integration_id', $integration->getKey())
            ->first();
        if ($existingLink !== null) {
            return $existingLink;
        }

        $mappedFields = $this->fieldMappings->fieldsForContact($integration, $contact);
        if ($preferredPersonId !== null && $preferredPersonId !== '') {
            if ($mappedFields !== []) {
                $this->adapter->updatePerson($integration, $preferredPersonId, ['fields' => $mappedFields]);
            }

            return CrmContactLink::query()->create([
                'team_id' => $contact->team_id,
                'contact_id' => $contact->getKey(),
                'integration_id' => $integration->getKey(),
                'external_person_id' => $preferredPersonId,
            ]);
        }

        $phone = E164PhoneNumber::formatForCountry($contact->phone, 'BR');
        if ($phone === null) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O Contato precisa ter um telefone válido para ser vinculado ao CRM.');
        }

        $matchingPeople = $this->matchingPeople($integration, $phone);
        $matchCount = count($matchingPeople);
        $operation->forceFill([
            'summary' => [
                ...$operation->summary,
                'crm_person_phone_match_count' => $matchCount,
                'crm_person_phone_match_duplicates' => $matchCount > 1,
            ],
        ])->save();

        $person = $matchingPeople[0] ?? null;
        if ($person === null) {
            $personAttributes = [
                'name' => $contact->name,
                'phone' => $phone,
                'owner_id' => is_string($payload['default_owner_id'] ?? null) ? $payload['default_owner_id'] : null,
                'fields' => $mappedFields,
            ];
            $person = $this->adapter->createPerson($integration, $personAttributes);
        } elseif ($mappedFields !== []) {
            $person = $this->adapter->updatePerson($integration, $person['id'], ['fields' => $mappedFields]);
        }

        return CrmContactLink::query()->create([
            'team_id' => $contact->team_id,
            'contact_id' => $contact->getKey(),
            'integration_id' => $integration->getKey(),
            'external_person_id' => $person['id'],
        ]);
    }

    private function updatePersonFields(CrmOperation $operation, Integration $integration, Contact $contact): void
    {
        $personId = $operation->payload['external_person_id'] ?? null;
        $fields = $operation->payload['fields'] ?? null;
        if (! is_string($personId) || $personId === '' || ! is_array($fields) || $fields === []) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'A Operação não contém os Campos necessários para atualizar a Pessoa do CRM.');
        }

        $isLinked = CrmContactLink::query()
            ->where('team_id', $contact->team_id)
            ->where('contact_id', $contact->getKey())
            ->where('integration_id', $integration->getKey())
            ->where('external_person_id', $personId)
            ->exists();
        if (! $isLinked) {
            throw new CrmProviderException(CrmErrorCategory::Definitive, 'O Vínculo com a Pessoa do CRM não está mais disponível.');
        }

        $this->adapter->updatePerson($integration, $personId, ['fields' => $fields]);
    }

    /** @return list<CrmPerson> */
    private function matchingPeople(Integration $integration, string $phone): array
    {
        $people = array_values(array_filter(
            $this->adapter->findPeopleByPhone($integration, $phone),
            fn (array $person): bool => E164PhoneNumber::formatForCountry((string) ($person['phone'] ?? ''), 'BR') === $phone,
        ));
        usort($people, fn (array $left, array $right): int => strcmp(
            (string) ($right['updated_at'] ?? ''),
            (string) ($left['updated_at'] ?? ''),
        ));

        return $people;
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

        app(CrmCommercialStageService::class)->reconcileDealChange($linkedDeal, null, $deal['loss_reason_id'] ?? null);

        return $linkedDeal;
    }
}
