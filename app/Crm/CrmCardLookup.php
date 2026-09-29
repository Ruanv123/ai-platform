<?php

namespace App\Crm;

use App\Crm\Contracts\CrmProviderAdapter;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\Integration;
use App\Models\Team;
use App\Support\E164PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CrmCardLookup
{
    private const LOOKUP_TIMEOUT_SECONDS = 5.0;

    public function __construct(private CrmProviderAdapter $adapter) {}

    public function lookup(Team $team, ?Contact $contact, ?string $phone = null): array
    {
        if ($contact !== null && ($contact->team_id !== $team->getKey() || ! $contact->exists)) {
            return $this->unavailable();
        }

        $integration = $team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->first();
        if ($integration === null) {
            return $this->unavailable();
        }

        $normalizedPhone = E164PhoneNumber::formatForCountry($contact?->phone ?? $phone ?? '', 'BR');
        $deadline = hrtime(true) + (int) (self::LOOKUP_TIMEOUT_SECONDS * 1_000_000_000);

        try {
            $card = $contact === null
                ? $this->lookupCard($integration, $team, null, $normalizedPhone, $deadline)
                : Cache::lock('crm:contact-link:'.$team->getKey().':'.$contact->getKey(), 10)
                    ->block(2, fn (): array => $this->lookupCard($integration, $team, $contact, $normalizedPhone, $deadline));

            return $card;
        } catch (Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }
    }

    private function lookupCard(Integration $integration, Team $team, ?Contact $contact, ?string $phone, int $deadline): array
    {
        $link = $contact === null ? null : CrmContactLink::query()
            ->where('team_id', $team->getKey())
            ->where('contact_id', $contact->getKey())
            ->where('integration_id', $integration->getKey())
            ->first();

        if ($link === null && $phone === null) {
            return ['ok' => false, 'message' => 'Informe um telefone válido para consultar a ficha no CRM.'];
        }

        $person = $link === null
            ? $this->newestMatchingPerson($integration, $phone, $this->remainingSeconds($deadline))
            : $this->adapter->findPerson($integration, $link->external_person_id, $this->remainingSeconds($deadline));

        if ($person === null) {
            return ['ok' => true, 'found' => false, 'message' => 'Nenhuma ficha encontrada no CRM.'];
        }

        if ($contact !== null && $link === null) {
            CrmContactLink::query()->create([
                'team_id' => $team->getKey(),
                'contact_id' => $contact->getKey(),
                'integration_id' => $integration->getKey(),
                'external_person_id' => $person['id'],
            ]);
        }

        $metadata = is_array($integration->crm_metadata) ? $integration->crm_metadata : [];
        $visibleFieldIds = data_get($integration->crm_settings, 'agent_visible_field_ids', []);
        $visibleFields = $this->visibleFields($person['fields'] ?? [], $metadata, is_array($visibleFieldIds) ? $visibleFieldIds : []);
        $ownerName = $this->ownerName($person['owner_id'] ?? null, $metadata);
        $openDeals = $this->adapter->findOpenDeals($integration, $person['id'], $this->remainingSeconds($deadline));

        return [
            'ok' => true,
            'found' => true,
            'fields' => $visibleFields,
            'open_deals' => array_map(fn (array $deal): array => [
                'pipeline' => $this->metadataName($metadata, 'pipelines', $deal['pipeline_id'] ?? null),
                'stage' => $this->stageName($metadata, $deal['pipeline_id'] ?? null, $deal['stage_id'] ?? null),
                'value' => $deal['value'] ?? null,
                'owner_name' => $this->ownerName($deal['owner_id'] ?? null, $metadata),
            ], $openDeals),
            'owner_name' => $ownerName,
        ];
    }

    private function newestMatchingPerson(Integration $integration, string $phone, float $timeoutSeconds): ?array
    {
        if ($timeoutSeconds <= 0) {
            throw new \RuntimeException('The CRM lookup exceeded its time limit.');
        }

        $people = array_values(array_filter(
            $this->adapter->findPeopleByPhone($integration, $phone, $timeoutSeconds),
            fn (array $person): bool => E164PhoneNumber::formatForCountry((string) ($person['phone'] ?? ''), 'BR') === $phone,
        ));
        usort($people, fn (array $left, array $right): int => strcmp(
            (string) ($right['updated_at'] ?? ''),
            (string) ($left['updated_at'] ?? ''),
        ));

        return $people[0] ?? null;
    }

    private function remainingSeconds(int $deadline): float
    {
        $remainingSeconds = max(0, ($deadline - hrtime(true)) / 1_000_000_000);

        if ($remainingSeconds <= 0) {
            throw new \RuntimeException('The CRM lookup exceeded its time limit.');
        }

        return $remainingSeconds;
    }

    private function visibleFields(array $personFields, array $metadata, array $visibleFieldIds): array
    {
        if ($visibleFieldIds === []) {
            return [];
        }

        $visibleIds = array_fill_keys(array_map('strval', $visibleFieldIds), true);
        $fields = [];

        foreach (($metadata['fields'] ?? []) as $field) {
            if (! is_array($field) || ! isset($visibleIds[(string) ($field['id'] ?? '')])) {
                continue;
            }

            if (isset($field['entity']) && $field['entity'] !== '' && $field['entity'] !== 'contact') {
                continue;
            }

            $key = (string) ($field['slug'] ?? $field['id'] ?? '');
            if ($key === '' || ! array_key_exists($key, $personFields)) {
                continue;
            }

            $fields[] = [
                'name' => (string) ($field['name'] ?? $key),
                'value' => $personFields[$key],
            ];
        }

        return $fields;
    }

    private function ownerName(mixed $ownerId, array $metadata): ?string
    {
        if (! is_string($ownerId) || $ownerId === '') {
            return null;
        }

        foreach (($metadata['owners'] ?? []) as $owner) {
            if (is_array($owner) && (string) ($owner['id'] ?? '') === $ownerId && is_string($owner['name'] ?? null)) {
                return $owner['name'];
            }
        }

        return null;
    }

    private function metadataName(array $metadata, string $collection, mixed $id): ?string
    {
        if (! is_string($id)) {
            return null;
        }

        foreach (($metadata[$collection] ?? []) as $item) {
            if (is_array($item) && (string) ($item['id'] ?? '') === $id && is_string($item['name'] ?? null)) {
                return $item['name'];
            }
        }

        return null;
    }

    private function stageName(array $metadata, mixed $pipelineId, mixed $stageId): ?string
    {
        if (! is_string($pipelineId) || ! is_string($stageId)) {
            return null;
        }

        foreach (($metadata['pipelines'] ?? []) as $pipeline) {
            if (! is_array($pipeline) || (string) ($pipeline['id'] ?? '') !== $pipelineId) {
                continue;
            }

            foreach (($pipeline['stages'] ?? []) as $stage) {
                if (is_array($stage) && (string) ($stage['id'] ?? '') === $stageId && is_string($stage['name'] ?? null)) {
                    return $stage['name'];
                }
            }
        }

        return null;
    }

    private function unavailable(): array
    {
        return ['ok' => false, 'message' => 'Ficha indisponível'];
    }
}
