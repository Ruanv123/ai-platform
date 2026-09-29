<?php

namespace App\Crm\Contracts;

use App\Models\Integration;

interface CrmProviderAdapter
{
    public function provider(): string;

    public function authorizationUrl(string $state): string;

    public function exchangeAuthorizationCode(string $code): array;

    public function freshIntegration(Integration $integration): Integration;

    public function loadMetadata(Integration $integration): array;

    public function registerWebhooks(Integration $integration, string $callbackUrl): array;

    public function checkWebhooks(Integration $integration): array;

    public function removeWebhooks(Integration $integration): void;

    public function authenticateWebhook(array $headers, string $body): bool;

    public function identifyWebhook(array $headers): ?string;

    public function translateWebhook(array $payload): ?array;

    public function findPeopleByPhone(Integration $integration, string $phone): array;

    public function findPerson(Integration $integration, string $personId): array;

    public function findDeal(Integration $integration, string $dealId): array;

    public function findOpenDeals(Integration $integration, string $personId): array;

    public function createPerson(Integration $integration, array $attributes): array;

    public function updatePerson(Integration $integration, string $personId, array $attributes): array;

    public function createDeal(Integration $integration, array $attributes): array;

    public function updateDeal(Integration $integration, string $dealId, array $attributes): array;

    public function moveDeal(Integration $integration, string $dealId, string $stageId): array;

    public function createNote(Integration $integration, string $personId, string $body): array;

    public function personUrl(string $personId): ?string;

    public function capabilities(): array;

    public function rateLimit(): array;
}
