<?php

namespace Tests\Support\Crm\Fakes;

use App\Crm\AbstractCrmProviderAdapter;
use App\Models\Integration;
use Throwable;

/**
 * @phpstan-import-type CrmActivity from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmCapabilities from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmDeal from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmDealAttributes from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmDealUpdateAttributes from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmMetadata from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmOAuthTokens from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmPerson from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmPersonAttributes from \App\Crm\Contracts\CrmProviderAdapter
 * @phpstan-import-type CrmRateLimit from \App\Crm\Contracts\CrmProviderAdapter
 */
class FakeCrmProviderAdapter extends AbstractCrmProviderAdapter
{
    public array $people = [];

    public array $openDeals = [];

    public ?Throwable $lookupFailure = null;

    public array $phoneSearches = [];

    public array $personReads = [];

    public array $dealSearches = [];

    public array $lookupBudgets = [];

    public function provider(): string
    {
        return 'fake';
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://crm.example.test/oauth?state='.$state;
    }

    /** @return CrmOAuthTokens */
    public function exchangeAuthorizationCode(string $code): array
    {
        return [
            'access_token' => 'fake-access-'.$code,
            'refresh_token' => 'fake-refresh',
            'expires_at' => now()->addHours(2)->getTimestamp(),
        ];
    }

    public function freshIntegration(Integration $integration, float $timeoutSeconds = 15.0): Integration
    {
        return $integration;
    }

    /** @return CrmMetadata */
    public function loadMetadata(Integration $integration): array
    {
        return [
            'account' => ['teams' => []],
            'pipelines' => [],
            'fields' => [],
            'owners' => [],
            'lost_reasons' => [],
        ];
    }

    /** @return list<CrmPerson> */
    public function findPeopleByPhone(Integration $integration, string $phone, float $timeoutSeconds = 15.0): array
    {
        $this->phoneSearches[] = $phone;
        $this->lookupBudgets[] = $timeoutSeconds;

        if ($this->lookupFailure !== null) {
            throw $this->lookupFailure;
        }

        return $this->people;
    }

    /** @return CrmPerson */
    public function findPerson(Integration $integration, string $personId, float $timeoutSeconds = 15.0): array
    {
        $this->personReads[] = $personId;
        $this->lookupBudgets[] = $timeoutSeconds;

        if ($this->lookupFailure !== null) {
            throw $this->lookupFailure;
        }

        return collect($this->people)->firstWhere('id', $personId) ?? $this->person($personId);
    }

    /** @return CrmDeal */
    public function findDeal(Integration $integration, string $dealId): array
    {
        return $this->deal($dealId);
    }

    /** @return list<CrmDeal> */
    public function findOpenDeals(Integration $integration, string $personId, float $timeoutSeconds = 15.0): array
    {
        $this->dealSearches[] = $personId;
        $this->lookupBudgets[] = $timeoutSeconds;

        if ($this->lookupFailure !== null) {
            throw $this->lookupFailure;
        }

        return $this->openDeals;
    }

    /** @param CrmPersonAttributes $attributes
     * @return CrmPerson
     */
    public function createPerson(Integration $integration, array $attributes): array
    {
        return $this->person('person-1', $attributes);
    }

    /** @param CrmPersonAttributes $attributes
     * @return CrmPerson
     */
    public function updatePerson(Integration $integration, string $personId, array $attributes): array
    {
        return $this->person($personId, $attributes);
    }

    /** @param CrmDealAttributes $attributes
     * @return CrmDeal
     */
    public function createDeal(Integration $integration, array $attributes): array
    {
        return $this->deal('deal-1', $attributes);
    }

    /** @param CrmDealUpdateAttributes $attributes
     * @return CrmDeal
     */
    public function updateDeal(Integration $integration, string $dealId, array $attributes): array
    {
        return $this->deal($dealId, $attributes);
    }

    /** @return CrmDeal */
    public function moveDeal(Integration $integration, string $dealId, string $stageId): array
    {
        return $this->deal($dealId, ['stage_id' => $stageId]);
    }

    /** @return CrmActivity */
    public function createNote(Integration $integration, string $personId, string $body): array
    {
        return [
            'id' => 'activity-1',
            'person_id' => $personId,
            'deal_id' => null,
            'type' => 'note',
            'body' => $body,
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function personUrl(string $personId): ?string
    {
        return 'https://crm.example.test/people/'.$personId;
    }

    /** @return CrmCapabilities */
    public function capabilities(): array
    {
        return [
            'metadata' => true,
            'loss_reason_required' => false,
            'won_and_lost_stages' => false,
            'webhooks' => false,
            'operations' => ['lookup', 'person_create', 'person_update', 'deal_create', 'deal_update', 'deal_move', 'note'],
        ];
    }

    /** @return CrmRateLimit */
    public function rateLimit(): array
    {
        return ['requests' => 100, 'window_seconds' => 60];
    }

    /** @param array<string, mixed> $attributes
     * @return CrmPerson
     */
    private function person(string $personId, array $attributes = []): array
    {
        return [
            'id' => $personId,
            'name' => $attributes['name'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'email' => $attributes['email'] ?? null,
            'organization_id' => $attributes['organization_id'] ?? null,
            'owner_id' => $attributes['owner_id'] ?? null,
            'fields' => $attributes['fields'] ?? [],
            'updated_at' => $attributes['updated_at'] ?? now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return CrmDeal
     */
    private function deal(string $dealId, array $attributes = []): array
    {
        return [
            'id' => $dealId,
            'person_id' => $attributes['person_id'] ?? null,
            'organization_id' => $attributes['organization_id'] ?? null,
            'pipeline_id' => $attributes['pipeline_id'] ?? 'pipeline-1',
            'stage_id' => $attributes['stage_id'] ?? 'stage-1',
            'status' => $attributes['status'] ?? 'open',
            'loss_reason_id' => $attributes['loss_reason_id'] ?? null,
            'value' => is_numeric($attributes['value'] ?? null) ? (float) $attributes['value'] : null,
            'owner_id' => $attributes['owner_id'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
