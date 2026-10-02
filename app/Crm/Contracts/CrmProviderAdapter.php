<?php

namespace App\Crm\Contracts;

use App\Models\Integration;

/**
 * @phpstan-type CrmStage array{id: string, name: string, status: string|null, position: int|string|null}
 * @phpstan-type CrmPipeline array{id: string, name: string, stages: list<CrmStage>}
 * @phpstan-type CrmFieldDefinition array{id: string, name: string, type: string, options: list<mixed>, slug: string, entity: string}
 * @phpstan-type CrmOwner array{id: string, name: string, email: string|null}
 * @phpstan-type CrmLostReason array{id: string, name: string}
 * @phpstan-type CrmAccountContext array{teams: list<array{id: string, name: string}>}
 * @phpstan-type CrmMetadata array{account: CrmAccountContext, pipelines: list<CrmPipeline>, fields: list<CrmFieldDefinition>, owners: list<CrmOwner>, lost_reasons: list<CrmLostReason>}
 * @phpstan-type CrmPerson array{id: string, name: string|null, phone: string|null, email: string|null, organization_id: string|null, owner_id: string|null, fields: array<string, mixed>, updated_at: string|null}
 * @phpstan-type CrmOrganization array{id: string, name: string|null, fields: array<string, mixed>}
 * @phpstan-type CrmDeal array{id: string, person_id: string|null, organization_id: string|null, pipeline_id: string, stage_id: string, status: 'open'|'won'|'lost', loss_reason_id: string|null, value: float|null, owner_id: string|null, updated_at: string|null}
 * @phpstan-type CrmActivity array{id: string, person_id: string, deal_id: string|null, type: 'note', body: string, created_at: string|null}
 * @phpstan-type CrmWebhookSubscription array{id: string, event: string, callback_url: string, secret_hash: string|null}
 * @phpstan-type CrmWebhookNotice array{resource: 'person'|'deal', external_id: string, deleted: bool}
 * @phpstan-type CrmPersonAttributes array{name?: string|null, phone?: string|null, email?: string|null, organization_id?: string|null, owner_id?: string|null, fields?: array<string, mixed>}
 * @phpstan-type CrmDealAttributes array{name?: string|null, person_id: string, organization_id?: string|null, pipeline_id: string, stage_id: string, status?: 'open'|'won'|'lost', loss_reason_id?: string|null, value?: float|null, owner_id?: string|null, fields?: array<string, mixed>}
 * @phpstan-type CrmDealUpdateAttributes array{name?: string|null, person_id?: string|null, organization_id?: string|null, pipeline_id?: string, stage_id?: string, status?: 'open'|'won'|'lost', loss_reason_id?: string|null, value?: float|null, owner_id?: string|null, fields?: array<string, mixed>}
 * @phpstan-type CrmCapabilities array{metadata: bool, loss_reason_required: bool, won_and_lost_stages: bool, webhooks: bool, operations: list<'lookup'|'person_create'|'person_update'|'deal_create'|'deal_update'|'deal_move'|'note'>}
 * @phpstan-type CrmRateLimit array{requests: int, window_seconds: int}
 * @phpstan-type CrmOAuthTokens array{access_token: string, refresh_token: string, expires_at: int, expires_in?: int, token_type?: string, ...<string, mixed>}
 */
interface CrmProviderAdapter
{
    public function provider(): string;

    public function authorizationUrl(string $state): string;

    /** @return CrmOAuthTokens */
    public function exchangeAuthorizationCode(string $code): array;

    public function freshIntegration(Integration $integration, float $timeoutSeconds = 15.0): Integration;

    public function renewIntegration(Integration $integration, float $timeoutSeconds = 15.0): Integration;

    /** @return CrmMetadata */
    public function loadMetadata(Integration $integration): array;

    /** @return list<CrmWebhookSubscription> */
    public function registerWebhooks(Integration $integration, string $callbackUrl): array;

    /** @return list<CrmWebhookSubscription> */
    public function checkWebhooks(Integration $integration): array;

    public function removeWebhooks(Integration $integration): void;

    /** @param array<string, string|list<string>|null> $headers */
    public function authenticateWebhook(array $headers, string $body): bool;

    /** @param array<string, string|list<string>|null> $headers */
    public function identifyWebhook(array $headers): ?string;

    /** @param array<string, mixed> $payload
     * @return CrmWebhookNotice|null
     */
    public function translateWebhook(array $payload): ?array;

    /** @return list<CrmPerson> */
    public function findPeopleByPhone(Integration $integration, string $phone, float $timeoutSeconds = 15.0): array;

    /** @return CrmPerson */
    public function findPerson(Integration $integration, string $personId, float $timeoutSeconds = 15.0): array;

    /** @return CrmDeal */
    public function findDeal(Integration $integration, string $dealId): array;

    /** @return list<CrmDeal> */
    public function findOpenDeals(Integration $integration, string $personId, float $timeoutSeconds = 15.0): array;

    /** @param CrmPersonAttributes $attributes
     * @return CrmPerson
     */
    public function createPerson(Integration $integration, array $attributes): array;

    /** @param CrmPersonAttributes $attributes
     * @return CrmPerson
     */
    public function updatePerson(Integration $integration, string $personId, array $attributes): array;

    /** @param CrmDealAttributes $attributes
     * @return CrmDeal
     */
    public function createDeal(Integration $integration, array $attributes): array;

    /** @param CrmDealUpdateAttributes $attributes
     * @return CrmDeal
     */
    public function updateDeal(Integration $integration, string $dealId, array $attributes): array;

    /** @return CrmDeal */
    public function moveDeal(Integration $integration, string $dealId, string $stageId): array;

    /** @return CrmActivity */
    public function createNote(Integration $integration, string $personId, string $body, ?string $dealId = null): array;

    public function personUrl(string $personId): ?string;

    /** @return CrmCapabilities */
    public function capabilities(): array;

    /** @return CrmRateLimit */
    public function rateLimit(): array;
}
