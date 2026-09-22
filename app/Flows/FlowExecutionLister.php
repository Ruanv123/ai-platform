<?php

namespace App\Flows;

use App\Enums\FlowExecutionStatus;
use App\Models\Flow;
use App\Models\FlowExecution;

final class FlowExecutionLister
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function list(Flow $flow, array $filters): array
    {
        $status = (string) ($filters['execution_status'] ?? 'failed');
        $perPage = (int) ($filters['per_page'] ?? 10);
        $applyErrorFilter = function ($query) use ($filters): void {
            if (! filled($filters['error'] ?? null)) {
                return;
            }

            $query->where(function ($query) use ($filters): void {
                $query->where('final_reason', $filters['error'])
                    ->orWhereHas('steps', fn ($steps) => $steps->where('error', $filters['error']));
            });
        };
        $baseQuery = $flow->executions();
        $counts = [
            'failed' => (clone $baseQuery)->where('flow_executions.status', FlowExecutionStatus::Failed)->tap($applyErrorFilter)->count(),
            'stuck' => (clone $baseQuery)->stuck()->tap($applyErrorFilter)->count(),
            'all' => (clone $baseQuery)->tap($applyErrorFilter)->count(),
        ];

        $executions = $flow->executions()
            ->with(['contact', 'flowVersion', 'steps'])
            ->when($status === 'failed', fn ($query) => $query->where('status', 'failed'))
            ->when($status === 'stuck', fn ($query) => $query->stuck())
            ->tap($applyErrorFilter)
            ->latest('flow_executions.created_at')
            ->latest('flow_executions.id')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'data' => $executions->getCollection()->map(fn (FlowExecution $execution): array => $this->serialize($execution))->values(),
            'currentPage' => $executions->currentPage(),
            'lastPage' => $executions->lastPage(),
            'from' => $executions->firstItem(),
            'to' => $executions->lastItem(),
            'total' => $executions->total(),
            'perPage' => $executions->perPage(),
            'counts' => $counts,
        ];
    }

    /** @return array<string, mixed> */
    public function serialize(FlowExecution $execution): array
    {
        $isStuck = $execution->status->value === 'stuck' || $execution->isActive() && $execution->wake_at?->isPast() && $execution->wake_at?->lte(now()->subMinutes(5));
        $lastStep = $execution->steps->last();
        $nodeId = $lastStep?->node_id ?? $execution->current_node_id;
        $node = collect($execution->flowVersion->dsl['nodes'] ?? [])->firstWhere('id', $nodeId);
        $reason = $lastStep?->error ?? $execution->final_reason;

        return [
            'id' => $execution->getKey(),
            'contact' => $execution->contact === null ? null : [
                'name' => $execution->contact->name,
                'phone' => $execution->contact->phone,
            ],
            'status' => $isStuck ? 'stuck' : $execution->status->value,
            'statusLabel' => $isStuck ? 'Presa' : match ($execution->status) {
                FlowExecutionStatus::Completed => 'Concluída',
                FlowExecutionStatus::Failed => 'Falhou',
                FlowExecutionStatus::Abandoned => 'Abandonada',
                FlowExecutionStatus::Waiting => 'Aguardando',
                FlowExecutionStatus::Paused => 'Pausada',
                default => 'Em execução',
            },
            'nodeId' => $nodeId,
            'nodeLabel' => is_array($node) ? ($node['label'] ?? data_get($node, 'config.label') ?? data_get($node, 'config.title') ?? 'Nó') : null,
            'reason' => $reason,
            'version' => $execution->flowVersion->number,
            'createdAt' => $execution->created_at?->toIso8601String(),
            'traceUrl' => route('flows.executions.show', [$execution->flowVersion->flow_id, $execution]),
        ];
    }
}
