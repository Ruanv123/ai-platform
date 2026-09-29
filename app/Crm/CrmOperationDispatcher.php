<?php

namespace App\Crm;

use App\Ai\Tools\GuaranteeCrmDeal;
use App\Jobs\Integrations\ProcessCrmOperation;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\CrmOperation;
use App\Models\Integration;
use App\Models\Team;
use RuntimeException;

class CrmOperationDispatcher
{
    public function queueGuaranteeDeal(
        Team $team,
        Agent $agent,
        Contact $contact,
        string $title,
        ?float $estimatedValue,
        string $pipelineId,
        string $initialStageId,
    ): CrmOperation {
        if ($agent->team_id !== $team->getKey() || $contact->team_id !== $team->getKey()) {
            throw new RuntimeException('Este Contato não pertence à Empresa do Agente.');
        }

        $integration = $team->integrations()
            ->where('type', 'crm')
            ->where('status', 'connected')
            ->first();
        if (! $integration instanceof Integration) {
            throw new RuntimeException('O CRM não está conectado.');
        }

        if (! $this->isConfiguredStage($integration, $pipelineId, $initialStageId)) {
            throw new RuntimeException('O Funil de vendas ou a Etapa inicial não está mais disponível no CRM.');
        }

        $defaultOwnerId = data_get($integration->crm_settings, 'default_owner_id');
        $operation = CrmOperation::query()->create([
            'team_id' => $team->getKey(),
            'integration_id' => $integration->getKey(),
            'contact_id' => $contact->getKey(),
            'agent_id' => $agent->getKey(),
            'origin' => 'agent',
            'type' => GuaranteeCrmDeal::OPERATION_TYPE,
            'status' => 'pending',
            'payload' => [
                'title' => $title,
                'estimated_value' => $estimatedValue,
                'pipeline_id' => $pipelineId,
                'initial_stage_id' => $initialStageId,
                'default_owner_id' => is_string($defaultOwnerId) ? $defaultOwnerId : null,
            ],
            'summary' => [
                'title' => $title,
                'estimated_value' => $estimatedValue,
            ],
            'expires_at' => now()->addDays(30),
        ]);

        ProcessCrmOperation::dispatch($operation->getKey())
            ->onQueue('crm-operations')
            ->afterCommit();

        return $operation;
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
