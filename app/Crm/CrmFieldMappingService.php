<?php

namespace App\Crm;

use App\Jobs\Integrations\ProcessCrmOperation;
use App\Models\Contact;
use App\Models\ContactField;
use App\Models\ContactFieldValue;
use App\Models\CrmContactLink;
use App\Models\CrmFieldMapping;
use App\Models\CrmOperation;
use App\Models\Integration;
use Illuminate\Support\Collection;

class CrmFieldMappingService
{
    public function fieldsForContact(Integration $integration, Contact $contact): array
    {
        $contactValues = $contact->fieldValues()->get()->keyBy('contact_field_id');

        return $this->mappedFields($integration, $contact, $contactValues)['crm'];
    }

    public function applyProviderFields(Integration $integration, Contact $contact, array $providerFields): void
    {
        $definitions = collect($integration->crm_metadata['fields'] ?? [])
            ->keyBy(fn (array $field): string => (string) ($field['id'] ?? ''));
        $mappings = $this->mappings($integration, $contact)
            ->where('direction', 'from_crm');

        foreach ($mappings as $mapping) {
            $contactField = $mapping->contactField;
            $crmField = $definitions->get($mapping->crm_field_id);
            if (! $contactField instanceof ContactField
                || ! is_array($crmField)
                || ($crmField['entity'] ?? null) !== 'contact'
                || ! is_string($crmField['slug'] ?? null)
                || ! array_key_exists($crmField['slug'], $providerFields)) {
                continue;
            }

            $providerValue = $providerFields[$crmField['slug']];
            if ($contactField->type === 'choice' && $providerValue !== null) {
                $localValue = array_search((string) $providerValue, $mapping->option_mappings ?? [], true);
                if (! is_string($localValue)) {
                    continue;
                }

                $providerValue = $localValue;
            }

            if ($providerValue !== null && ! is_scalar($providerValue)) {
                continue;
            }

            $contact->fieldValues()->updateOrCreate(
                ['contact_field_id' => $contactField->getKey()],
                ['value' => $providerValue === null ? null : (string) $providerValue],
            );
        }
    }

    public function isCompatible(ContactField $contactField, array $crmField): bool
    {
        if (($crmField['entity'] ?? null) !== 'contact') {
            return false;
        }

        $crmType = strtolower((string) ($crmField['type'] ?? ''));

        return match ($contactField->type) {
            'text' => $crmType === 'text',
            'number' => $crmType === 'number',
            'date' => $crmType === 'date',
            'choice' => in_array($crmType, ['option', 'select'], true),
            default => false,
        };
    }

    public function hasCompleteOptionMapping(ContactField $contactField, array $crmField, array $optionMappings): bool
    {
        if ($contactField->type !== 'choice') {
            return $optionMappings === [];
        }

        $contactOptions = array_map('strval', $contactField->options ?? []);
        $crmOptions = array_map('strval', is_array($crmField['options'] ?? null) ? $crmField['options'] : []);
        $mappedOptions = array_map('strval', array_keys($optionMappings));
        $mappedValues = array_map('strval', array_values($optionMappings));

        sort($contactOptions);
        sort($mappedOptions);

        return $contactOptions !== []
            && $contactOptions === $mappedOptions
            && count($mappedValues) === count(array_unique($mappedValues))
            && count(array_diff($mappedValues, $crmOptions)) === 0;
    }

    public function queueChangedField(ContactFieldValue $contactFieldValue): ?CrmOperation
    {
        $contact = $contactFieldValue->contact;
        $field = $contactFieldValue->field;
        if (! $contact instanceof Contact || ! $field instanceof ContactField) {
            return null;
        }

        $integration = $contact->team->integrations()
            ->where('type', 'crm')
            ->whereIn('status', ['connected', 'error'])
            ->first();
        if (! $integration instanceof Integration) {
            return null;
        }

        $mapping = CrmFieldMapping::query()
            ->where('team_id', $contact->team_id)
            ->where('integration_id', $integration->getKey())
            ->where('contact_field_id', $field->getKey())
            ->where('direction', 'to_crm')
            ->with('contactField')
            ->first();
        if ($mapping === null) {
            return null;
        }

        $link = $this->contactLink($integration, $contact);
        if ($link === null) {
            return null;
        }

        return $this->queueFieldOperation($integration, $contact, $link, [$mapping], collect([
            $field->getKey() => $contactFieldValue,
        ]));
    }

    public function queueInitialFieldsForLink(CrmContactLink $link): ?CrmOperation
    {
        $contact = $link->contact;
        $integration = $link->integration;
        if (! $contact instanceof Contact || ! $integration instanceof Integration) {
            return null;
        }

        $mappings = $this->mappings($integration, $contact)
            ->where('direction', 'to_crm')
            ->values();
        if ($mappings->isEmpty()) {
            return null;
        }

        return $this->queueFieldOperation(
            $integration,
            $contact,
            $link,
            $mappings->all(),
            $contact->fieldValues()->get()->keyBy('contact_field_id'),
        );
    }

    public function queueExistingValuesForMapping(CrmFieldMapping $mapping): void
    {
        if ($mapping->direction !== 'to_crm') {
            return;
        }

        $mapping->loadMissing(['contactField', 'integration']);
        $field = $mapping->contactField;
        $integration = $mapping->integration;
        if (! $field instanceof ContactField || ! $integration instanceof Integration) {
            return;
        }

        $links = CrmContactLink::query()
            ->where('team_id', $mapping->team_id)
            ->where('integration_id', $integration->getKey())
            ->with(['contact.fieldValues'])
            ->get();

        foreach ($links as $link) {
            $contact = $link->contact;
            if (! $contact instanceof Contact) {
                continue;
            }

            $contactFieldValue = $contact->fieldValues->firstWhere('contact_field_id', $field->getKey());
            if (! $contactFieldValue instanceof ContactFieldValue) {
                continue;
            }

            $this->queueFieldOperation(
                $integration,
                $contact,
                $link,
                [$mapping],
                collect([$field->getKey() => $contactFieldValue]),
            );
        }
    }

    private function queueFieldOperation(
        Integration $integration,
        Contact $contact,
        CrmContactLink $link,
        array $mappings,
        Collection $contactValues,
    ): ?CrmOperation {
        $mappedFields = $this->mappedFields($integration, $contact, $contactValues, $mappings);
        if ($mappedFields['crm'] === []) {
            return null;
        }

        $operation = CrmOperation::query()->create([
            'team_id' => $contact->team_id,
            'integration_id' => $integration->getKey(),
            'contact_id' => $contact->getKey(),
            'origin' => 'automatic',
            'type' => 'update_person_fields',
            'status' => 'pending',
            'payload' => [
                'external_person_id' => $link->external_person_id,
                'fields' => $mappedFields['crm'],
            ],
            'summary' => ['contact_fields' => $mappedFields['contact']],
            'expires_at' => now()->addDays(30),
        ]);

        ProcessCrmOperation::dispatch($operation->getKey())
            ->onQueue('crm-operations')
            ->afterCommit();

        return $operation;
    }

    private function mappedFields(
        Integration $integration,
        Contact $contact,
        Collection $contactValues,
        ?array $mappings = null,
    ): array {
        $mappings ??= $this->mappings($integration, $contact)
            ->where('direction', 'to_crm')
            ->all();
        $definitions = collect($integration->crm_metadata['fields'] ?? [])
            ->keyBy(fn (array $field): string => (string) ($field['id'] ?? ''));
        $crmFields = [];
        $contactFields = [];

        foreach ($mappings as $mapping) {
            if (! $mapping instanceof CrmFieldMapping) {
                continue;
            }

            $contactField = $mapping->contactField;
            $crmField = $definitions->get($mapping->crm_field_id);
            $contactFieldValue = $contactValues->get($mapping->contact_field_id);
            if (! $contactField instanceof ContactField
                || ! is_array($crmField)
                || ($crmField['entity'] ?? null) !== 'contact'
                || ! is_string($crmField['slug'] ?? null)
                || $crmField['slug'] === ''
                || ! $contactFieldValue instanceof ContactFieldValue) {
                continue;
            }

            $value = $contactFieldValue->value;
            if ($contactField->type === 'choice') {
                if ($value === null) {
                    $mappedValue = null;
                } else {
                    $mappedValue = $mapping->option_mappings[$value] ?? null;
                    if (! is_string($mappedValue)) {
                        continue;
                    }
                }
            } else {
                $mappedValue = $value;
            }

            $crmFields[$crmField['slug']] = $mappedValue;
            $contactFields[(string) $contactField->getKey()] = $contactField->is_sensitive ? '***' : $value;
        }

        return ['crm' => $crmFields, 'contact' => $contactFields];
    }

    private function mappings(Integration $integration, Contact $contact): Collection
    {
        return CrmFieldMapping::query()
            ->where('team_id', $contact->team_id)
            ->where('integration_id', $integration->getKey())
            ->with('contactField')
            ->get();
    }

    private function contactLink(Integration $integration, Contact $contact): ?CrmContactLink
    {
        return CrmContactLink::query()
            ->where('team_id', $contact->team_id)
            ->where('integration_id', $integration->getKey())
            ->where('contact_id', $contact->getKey())
            ->first();
    }
}
