<?php

namespace App\Crm;

use App\Crm\Contracts\CrmProviderAdapter;
use App\Models\Integration;

abstract class AbstractCrmProviderAdapter implements CrmProviderAdapter
{
    public function registerWebhooks(Integration $integration, string $callbackUrl): array
    {
        return $this->unsupported();
    }

    public function checkWebhooks(Integration $integration): array
    {
        return $this->unsupported();
    }

    public function removeWebhooks(Integration $integration): void
    {
        $this->unsupported();
    }

    public function authenticateWebhook(array $headers, string $body): bool
    {
        return $this->unsupported();
    }

    public function identifyWebhook(array $headers): ?string
    {
        return $this->unsupported();
    }

    public function translateWebhook(array $payload): ?array
    {
        return $this->unsupported();
    }

    public function findPeopleByPhone(Integration $integration, string $phone, float $timeoutSeconds = 15.0): array
    {
        return $this->unsupported();
    }

    public function findPerson(Integration $integration, string $personId, float $timeoutSeconds = 15.0): array
    {
        return $this->unsupported();
    }

    public function findDeal(Integration $integration, string $dealId): array
    {
        return $this->unsupported();
    }

    public function findOpenDeals(Integration $integration, string $personId, float $timeoutSeconds = 15.0): array
    {
        return $this->unsupported();
    }

    public function createPerson(Integration $integration, array $attributes): array
    {
        return $this->unsupported();
    }

    public function updatePerson(Integration $integration, string $personId, array $attributes): array
    {
        return $this->unsupported();
    }

    public function createDeal(Integration $integration, array $attributes): array
    {
        return $this->unsupported();
    }

    public function updateDeal(Integration $integration, string $dealId, array $attributes): array
    {
        return $this->unsupported();
    }

    public function moveDeal(Integration $integration, string $dealId, string $stageId): array
    {
        return $this->unsupported();
    }

    public function createNote(Integration $integration, string $personId, string $body): array
    {
        return $this->unsupported();
    }

    public function personUrl(string $personId): ?string
    {
        return $this->unsupported();
    }

    protected function unsupported(): never
    {
        throw new CrmProviderException(CrmErrorCategory::Definitive, 'Esta operação ainda não está disponível neste provedor de CRM.');
    }
}
