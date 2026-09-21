<?php

namespace App\Http\Controllers\Flows;

use App\Enums\FlowExecutionStatus;
use App\Http\Controllers\Controller;
use App\Models\Flow;
use App\Models\FlowExecution;
use App\Models\FlowExecutionStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FlowExecutionController extends Controller
{
    public function show(Flow $flow, FlowExecution $execution): Response
    {
        Gate::authorize('view', $flow);
        $execution->load(['flowVersion.flow', 'contact', 'channel', 'steps']);
        abort_unless($execution->flowVersion->flow_id === $flow->getKey(), 404);

        $nodes = $this->nodes($execution->flowVersion->dsl);
        $nodesById = $nodes->keyBy('id');
        $highlightedStepId = $this->highlightedStepId($execution);
        $reachedNodeIds = $execution->steps->pluck('node_id')->unique();
        $endedAt = $execution->isActive() ? now() : ($execution->last_activity_at ?? now());

        return Inertia::render('flows/executions/show', [
            'execution' => [
                'id' => $execution->getKey(),
                'flowId' => $flow->getKey(),
                'flowName' => $flow->name,
                'contact' => $execution->contact === null ? null : [
                    'name' => $execution->contact->name,
                    'phone' => $execution->contact->phone,
                ],
                'channel' => $execution->channel === null ? null : [
                    'label' => $execution->channel->type->label(),
                    'type' => $execution->channel->type->value,
                ],
                'version' => $execution->flowVersion->number,
                'status' => $execution->status->value,
                'statusLabel' => $this->statusLabel($execution->status),
                'finalReason' => $execution->final_reason,
                'durationMs' => (int) $execution->created_at->diffInMilliseconds($endedAt),
                'startedAt' => $execution->created_at->toIso8601String(),
                'finishedAt' => $execution->isActive() ? null : $endedAt->toIso8601String(),
            ],
            'steps' => $execution->steps
                ->map(function (FlowExecutionStep $step) use ($nodesById, $highlightedStepId): array {
                    $node = $nodesById->get($step->node_id, []);
                    $nodeType = is_string($node['type'] ?? null) ? $node['type'] : 'unknown';

                    return [
                        'id' => $step->getKey(),
                        'sequence' => $step->sequence,
                        'nodeId' => $step->node_id,
                        'nodeName' => $this->nodeName($node),
                        'nodeType' => $nodeType,
                        'nodeTypeLabel' => $this->nodeTypeLabel($nodeType),
                        'result' => $step->result,
                        'resultLabel' => $this->resultLabel($step->result),
                        'durationMs' => $step->duration_ms,
                        'error' => $step->error,
                        'variablesSnapshot' => $step->variables_snapshot,
                        'startedAt' => $step->started_at->toIso8601String(),
                        'followedEdge' => $step->edge_id === null ? null : [
                            'id' => $step->edge_id,
                            'source' => $step->edge_source_node_id,
                            'target' => $step->edge_target_node_id,
                            'label' => $step->edge_label,
                        ],
                        'highlighted' => $step->getKey() === $highlightedStepId,
                    ];
                })
                ->values(),
            'unreachedNodes' => $nodes
                ->reject(fn (array $node): bool => $reachedNodeIds->contains($node['id'] ?? null))
                ->map(function (array $node): array {
                    $nodeType = is_string($node['type'] ?? null) ? $node['type'] : 'unknown';

                    return [
                        'nodeId' => $node['id'],
                        'nodeName' => $this->nodeName($node),
                        'nodeType' => $nodeType,
                        'nodeTypeLabel' => $this->nodeTypeLabel($nodeType),
                        'reached' => false,
                    ];
                })
                ->values(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $dsl
     * @return Collection<int, array<string, mixed>>
     */
    private function nodes(array $dsl): Collection
    {
        $rawNodes = $dsl['nodes'] ?? [];
        if (! is_array($rawNodes)) {
            return collect();
        }

        $nodes = [];
        foreach ($rawNodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $normalizedNode = [];
            foreach ($node as $key => $value) {
                if (is_string($key)) {
                    $normalizedNode[$key] = $value;
                }
            }
            $nodes[] = $normalizedNode;
        }

        return collect($nodes);
    }

    private function highlightedStepId(FlowExecution $execution): ?string
    {
        if ($execution->status === FlowExecutionStatus::Completed) {
            return null;
        }

        if (in_array($execution->status, [FlowExecutionStatus::Failed, FlowExecutionStatus::Stuck], true)) {
            $failedStep = $execution->steps->where('result', 'failed')->last();
            if ($failedStep instanceof FlowExecutionStep) {
                return $failedStep->getKey();
            }
        }

        $stoppedStep = $execution->steps
            ->where('node_id', $execution->current_node_id)
            ->last();
        if ($stoppedStep instanceof FlowExecutionStep) {
            return $stoppedStep->getKey();
        }

        $lastStep = $execution->steps->last();

        return $lastStep instanceof FlowExecutionStep ? $lastStep->getKey() : null;
    }

    private function statusLabel(FlowExecutionStatus $status): string
    {
        return match ($status) {
            FlowExecutionStatus::Running => 'Em execução',
            FlowExecutionStatus::Waiting => 'Aguardando',
            FlowExecutionStatus::Paused => 'Pausada',
            FlowExecutionStatus::Completed => 'Concluída',
            FlowExecutionStatus::Failed => 'Falhou',
            FlowExecutionStatus::Stuck => 'Presa',
        };
    }

    private function resultLabel(string $result): string
    {
        return match ($result) {
            'running' => 'Em execução',
            'waiting' => 'Aguardando',
            'failed' => 'Falhou',
            default => 'Concluído',
        };
    }

    /** @param array<string, mixed> $node */
    private function nodeName(array $node): string
    {
        $configuredName = $node['label']
            ?? data_get($node, 'config.label')
            ?? data_get($node, 'config.title')
            ?? data_get($node, 'config.name');
        $type = is_string($node['type'] ?? null) ? $node['type'] : 'unknown';

        return is_string($configuredName) && filled($configuredName)
            ? $configuredName
            : $this->nodeTypeLabel($type);
    }

    private function nodeTypeLabel(string $type): string
    {
        return match ($type) {
            'trigger' => 'Gatilho',
            'message' => 'Mensagem',
            'capture_response' => 'Capturar resposta',
            'condition' => 'Condição',
            'wait' => 'Espera',
            'define_variable', 'set_variable', 'set_variable_or_tag' => 'Definir variável ou tag',
            'deliver_agent' => 'Entregar ao Agente',
            'transfer_human' => 'Transferir para humano',
            'emit_event' => 'Emitir Evento',
            'http_request' => 'Requisição HTTP',
            'send_template' => 'Enviar Template',
            'subflow', 'sub_flow' => 'Sub-fluxo',
            'ai_task' => 'Tarefa de IA',
            'intent_router', 'route_intent' => 'Roteamento por intenção',
            'ab_test', 'test_ab' => 'Teste A/B',
            'exception' => 'Exceção',
            'end', 'finish' => 'Encerrar',
            default => 'Nó',
        };
    }
}
