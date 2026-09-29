<?php

namespace App\Crm;

use App\Ai\Tools\GuaranteeCrmDeal;
use App\Crm\Contracts\CrmProviderAdapter;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\CrmContactLink;
use App\Models\CrmOperation;
use App\Models\Integration;
use App\Models\Team;
use App\Support\E164PhoneNumber;

class CrmOperationDispatcher
{
    public function __construct(private ?CrmProviderAdapter $adapter = null) {}

    public function queueGuaranteeDeal(
        Team $team,
        Agent $agent,
        Contact $contact,
        string $title,
        ?float $estimatedValue,
        string $pipelineId,
        string $initialStageId,
    ): CrmOperation {
        $this->assertOwnership($team, $agent, $contact);
        $integration = $this->connectedIntegration($team);

        if (! $this->isConfiguredStage($integration, $pipelineId, $initialStageId)) {
            throw new CrmOperationException('O Funil de vendas ou a Etapa inicial não está mais disponível no CRM.');
        }

        $defaultOwnerId = data_get($integration->crm_settings, 'default_owner_id');

        return $this->createOperation($team, $integration, $contact, $agent, GuaranteeCrmDeal::OPERATION_TYPE, [
            'title' => $title,
            'estimated_value' => $estimatedValue,
            'pipeline_id' => $pipelineId,
            'initial_stage_id' => $initialStageId,
            'default_owner_id' => is_string($defaultOwnerId) ? $defaultOwnerId : null,
        ], [
            'title' => $title,
            'estimated_value' => $estimatedValue,
        ]);
    }

    public function queueMoveDeal(
        Team $team,
        Agent $agent,
        Contact $contact,
        string $pipelineId,
        string $stageId,
        array $allowedStages,
    ): CrmOperation {
        $this->assertOwnership($team, $agent, $contact);
        $integration = $this->connectedIntegration($team);
        if (! $this->isAllowedStage($allowedStages, $integration, $pipelineId, $stageId)) {
            throw new CrmOperationException('Essa Etapa do funil não está liberada para o Agente.');
        }

        $personId = $this->linkedOrMatchingPersonId($team, $integration, $contact);
        if ($personId === null) {
            throw new CrmOperationException('Não encontrei uma Pessoa do CRM com Negócio aberto neste Funil de vendas.');
        }

        $deal = $this->newestOpenDeal($integration, $personId, $pipelineId);
        if ($deal === null) {
            throw new CrmOperationException('Não encontrei um Negócio aberto deste Contato no Funil de vendas do Agente.');
        }

        $operation = $this->createOperation($team, $integration, $contact, $agent, 'move_deal', [
            'external_person_id' => $personId,
            'external_deal_id' => $deal['id'],
            'pipeline_id' => $pipelineId,
            'target_stage_id' => $stageId,
        ], [
            'external_deal_id' => $deal['id'],
            'pipeline_id' => $pipelineId,
            'target_stage_id' => $stageId,
        ]);

        return $operation;
    }

    public function queueRegisterNote(Team $team, Agent $agent, Contact $contact, string $body, ?string $pipelineId): CrmOperation
    {
        $this->assertOwnership($team, $agent, $contact);
        $integration = $this->connectedIntegration($team);
        $personId = $this->linkedOrMatchingPersonId($team, $integration, $contact);

        return $this->createOperation($team, $integration, $contact, $agent, 'register_note', [
            'external_person_id' => $personId,
            'pipeline_id' => $pipelineId,
            'body' => $body,
            'default_owner_id' => data_get($integration->crm_settings, 'default_owner_id'),
        ], [
            'note_registered' => false,
            'pipeline_id' => $pipelineId,
        ]);
    }

    private function createOperation(
        Team $team,
        Integration $integration,
        Contact $contact,
        Agent $agent,
        string $type,
        array $payload,
        array $summary,
    ): CrmOperation {
        $operation = CrmOperation::query()->create([
            'team_id' => $team->getKey(),
            'integration_id' => $integration->getKey(),
            'contact_id' => $contact->getKey(),
            'agent_id' => $agent->getKey(),
            'origin' => 'agent',
            'type' => $type,
            'status' => 'pending',
            'payload' => $payload,
            'summary' => $summary,
            'expires_at' => now()->addDays(30),
        ]);

        ProcessCrmOperation::dispatch($operation->getKey())
            ->onQueue('crm-operations')
            ->afterCommit();

        return $operation;
    }

    private function assertOwnership(Team $team, Agent $agent, Contact $contact): void
    {
        if ($agent->team_id !== $team->getKey() || $contact->team_id !== $team->getKey()) {
            throw new CrmOperationException('Este Contato não pertence à Empresa do Agente.');
        }
    }

    private function connectedIntegration(Team $team): Integration
    {
        $integration = $team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->first();
        if (! $integration instanceof Integration) {
            throw new CrmOperationException('O CRM não está conectado.');
        }

        return $integration;
    }

    private function isAllowedStage(array $allowedStages, Integration $integration, string $pipelineId, string $stageId): bool
    {
        $allowed = collect($allowedStages)
            ->contains(fn (array $stage): bool => (string) ($stage['stage_id'] ?? '') === $stageId
                && trim((string) ($stage['description'] ?? '')) !== '');
        if (! $allowed) {
            return false;
        }

        foreach (($integration->crm_metadata['pipelines'] ?? []) as $pipeline) {
            if (! is_array($pipeline) || (string) ($pipeline['id'] ?? '') !== $pipelineId) {
                continue;
            }

            foreach (($pipeline['stages'] ?? []) as $stage) {
                if (is_array($stage)
                    && (string) ($stage['id'] ?? '') === $stageId
                    && ! in_array(strtolower((string) ($stage['status'] ?? '')), ['won', 'lost'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function linkedOrMatchingPersonId(Team $team, Integration $integration, Contact $contact): ?string
    {
        $linkedPersonId = CrmContactLink::query()
            ->where('team_id', $team->getKey())
            ->where('contact_id', $contact->getKey())
            ->where('integration_id', $integration->getKey())
            ->value('external_person_id');
        if (is_string($linkedPersonId)) {
            return $linkedPersonId;
        }

        $phone = E164PhoneNumber::formatForCountry($contact->phone, 'BR');
        if ($phone === null) {
            return null;
        }

        $people = array_values(array_filter(
            $this->adapter()->findPeopleByPhone($integration, $phone, 5),
            fn (array $person): bool => E164PhoneNumber::formatForCountry((string) ($person['phone'] ?? ''), 'BR') === $phone,
        ));
        usort($people, fn (array $left, array $right): int => strcmp(
            (string) ($right['updated_at'] ?? ''),
            (string) ($left['updated_at'] ?? ''),
        ));
        $personId = $people[0]['id'] ?? null;

        return is_string($personId) && $personId !== '' ? $personId : null;
    }

    private function newestOpenDeal(Integration $integration, string $personId, string $pipelineId): ?array
    {
        $deals = array_values(array_filter(
            $this->adapter()->findOpenDeals($integration, $personId, 5),
            fn (array $deal): bool => ($deal['pipeline_id'] ?? null) === $pipelineId && ($deal['status'] ?? null) === 'open',
        ));
        usort($deals, fn (array $left, array $right): int => strcmp(
            (string) ($right['updated_at'] ?? ''),
            (string) ($left['updated_at'] ?? ''),
        ));
        $deal = $deals[0] ?? null;

        return is_array($deal) && is_string($deal['id'] ?? null) && $deal['id'] !== '' ? $deal : null;
    }

    private function adapter(): CrmProviderAdapter
    {
        return $this->adapter ?? app(CrmProviderAdapter::class);
    }

    private function isConfiguredStage(Integration $integration, string $pipelineId, string $stageId): bool
    {
        foreach (($integration->crm_metadata['pipelines'] ?? []) as $pipeline) {
            if (! is_array($pipeline) || (string) ($pipeline['id'] ?? '') !== $pipelineId) {
                continue;
            }

            foreach (($pipeline['stages'] ?? []) as $stage) {
                if (is_array($stage) && (string) ($stage['id'] ?? '') === $stageId) {
                    return true;
                }
            }
        }

        return false;
    }
}
