<?php

namespace Tests\Support\Crm\Fakes;

use App\Crm\AbstractCrmProviderAdapter;
use App\Models\Integration;

class FakeCrmProviderAdapter extends AbstractCrmProviderAdapter
{
    public function provider(): string
    {
        return 'fake';
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://crm.example.test/oauth?state='.$state;
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        return [
            'access_token' => 'fake-access-'.$code,
            'refresh_token' => 'fake-refresh',
            'expires_at' => now()->addHours(2)->timestamp,
        ];
    }

    public function freshIntegration(Integration $integration): Integration
    {
        return $integration;
    }

    public function loadMetadata(Integration $integration): array
    {
        return [
            'pipelines' => [],
            'fields' => [],
            'owners' => [],
            'lost_reasons' => [],
        ];
    }

    public function findPeopleByPhone(Integration $integration, string $phone): array
    {
        return [];
    }

    public function findPerson(Integration $integration, string $personId): array
    {
        return ['id' => $personId];
    }

    public function findDeal(Integration $integration, string $dealId): array
    {
        return ['id' => $dealId];
    }

    public function findOpenDeals(Integration $integration, string $personId): array
    {
        return [];
    }

    public function createPerson(Integration $integration, array $attributes): array
    {
        return ['id' => 'person-1', ...$attributes];
    }

    public function updatePerson(Integration $integration, string $personId, array $attributes): array
    {
        return ['id' => $personId, ...$attributes];
    }

    public function createDeal(Integration $integration, array $attributes): array
    {
        return ['id' => 'deal-1', ...$attributes];
    }

    public function updateDeal(Integration $integration, string $dealId, array $attributes): array
    {
        return ['id' => $dealId, ...$attributes];
    }

    public function moveDeal(Integration $integration, string $dealId, string $stageId): array
    {
        return ['id' => $dealId, 'stage_id' => $stageId];
    }

    public function createNote(Integration $integration, string $personId, string $body): array
    {
        return ['person_id' => $personId, 'body' => $body];
    }

    public function personUrl(string $personId): ?string
    {
        return 'https://crm.example.test/people/'.$personId;
    }

    public function capabilities(): array
    {
        return ['people' => true, 'deals' => true, 'notes' => true];
    }

    public function rateLimit(): array
    {
        return ['requests' => 100, 'window_seconds' => 60];
    }
}
