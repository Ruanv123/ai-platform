<?php

namespace App\Flows;

use App\Models\Flow;
use App\Models\FlowEventEmission;
use App\Models\FlowExecutionAggregate;
use App\Models\FlowExecutionErrorAggregate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class FlowExecutionAnalytics
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(Flow $flow, string $period): array
    {
        $from = $this->periodStart($period);
        $aggregates = FlowExecutionAggregate::withoutGlobalScopes()
            ->where('team_id', $flow->team_id)
            ->where('flow_id', $flow->getKey())
            ->whereDate('period_date', '>=', $from->toDateString())
            ->get();
        $flowAggregates = $aggregates->where('node_id', '');
        $started = (int) $flowAggregates->sum('started_count');

        return [
            'period' => $period,
            'periodLabel' => $this->periodLabel($period),
            'from' => $from->toIso8601String(),
            'tiles' => [
                'started' => $started,
                'completed' => (int) $flowAggregates->sum('completed_count'),
                'abandoned' => (int) $flowAggregates->sum('abandoned_count'),
                'failed' => (int) $flowAggregates->sum('failed_count'),
                'stuck' => $this->stuckCount($flow, $from),
                'aiCostBrl' => round((float) $flowAggregates->sum('ai_cost_brl'), 4),
            ],
            'daily' => $this->daily($flowAggregates),
            'errors' => $this->errors($flow, $from),
            'funnel' => $this->funnel($flow, $aggregates, $started, $from),
        ];
    }

    private function periodStart(string $period): CarbonImmutable
    {
        return match ($period) {
            '24h' => now()->subHours(24),
            '30d' => now()->subDays(30)->startOfDay(),
            default => now()->subDays(7)->startOfDay(),
        };
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            '24h' => 'Últimas 24 horas',
            '30d' => 'Últimos 30 dias',
            default => 'Últimos 7 dias',
        };
    }

    /** @return list<array{date: string, label: string, started: int, failed: int}> */
    private function daily(Collection $aggregates): array
    {
        return $aggregates
            ->groupBy(fn (FlowExecutionAggregate $aggregate): string => $aggregate->period_date->toDateString())
            ->map(function (Collection $day, string $date): array {
                return [
                    'date' => $date,
                    'label' => now()->parse($date)->isoFormat('DD/MM'),
                    'started' => (int) $day->sum('started_count'),
                    'failed' => (int) $day->sum('failed_count'),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function errors(Flow $flow, CarbonImmutable $from): array
    {
        $errorQuery = FlowExecutionErrorAggregate::withoutGlobalScopes()
            ->where('team_id', $flow->team_id)
            ->where('flow_id', $flow->getKey())
            ->whereDate('period_date', '>=', $from->toDateString());
        $total = max(1, (int) (clone $errorQuery)->sum('occurrences'));
        $errors = $errorQuery
            ->selectRaw('error_code, node_id, SUM(occurrences) as occurrences')
            ->groupBy('error_code', 'node_id')
            ->orderByDesc('occurrences')
            ->limit(10)
            ->get()
            ->map(fn (FlowExecutionErrorAggregate $error): array => [
                'code' => $error->error_code,
                'label' => $this->errorLabel($error->error_code),
                'nodeId' => $error->node_id,
                'occurrences' => (int) $error->occurrences,
            ])
            ->values();

        return $errors->map(fn (array $error): array => [
            ...$error,
            'share' => round(($error['occurrences'] / $total) * 100, 1),
        ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function funnel(Flow $flow, Collection $aggregates, int $started, CarbonImmutable $from): array
    {
        $nodes = $this->funnelNodes($flow);
        $nodeAggregates = $aggregates
            ->reject(fn (FlowExecutionAggregate $aggregate): bool => $aggregate->node_id === '')
            ->groupBy('node_id');
        $funnelEdges = $this->funnelEdges($flow);
        $funnel = [];

        foreach ($nodes as $node) {
            $nodeId = (string) ($node['id'] ?? '');
            if ($nodeId === '') {
                continue;
            }
            $nodeRows = $nodeAggregates->get($nodeId, collect());
            $entered = (int) $nodeRows->sum('entered_count');
            $exited = (int) $nodeRows->sum('exited_count');
            $incoming = collect($funnelEdges)
                ->filter(fn (array $edge): bool => ($edge['target'] ?? null) === $nodeId)
                ->map(fn (array $edge): string => (string) ($edge['source'] ?? ''))
                ->filter()
                ->values();
            $previous = $incoming->isEmpty()
                ? $started
                : (int) $incoming->sum(fn (string $source): int => (int) $nodeAggregates->get($source, collect())->sum('exited_count'));
            $drop = max(0, $previous - $entered);
            $dropPercent = $previous === 0 ? 0 : round(($drop / $previous) * 100, 1);
            $funnel[] = [
                'nodeId' => $nodeId,
                'label' => $this->nodeLabel($node),
                'entered' => $entered,
                'exited' => $exited,
                'percentOfTotal' => $started === 0 ? 0 : round(($entered / $started) * 100, 1),
                'drop' => $drop,
                'dropPercent' => $dropPercent,
            ];
        }

        $largestDrop = collect($funnel)->sortByDesc('drop')->first();

        return [
            'nodes' => $funnel,
            'largestDrop' => $largestDrop === null ? null : [
                'label' => $largestDrop['label'],
                'drop' => $largestDrop['drop'],
                'dropPercent' => $largestDrop['dropPercent'],
            ],
            'events' => $this->eventFunnel($flow, $from),
        ];
    }

    /** @return list<array{name: string, count: int}> */
    private function eventFunnel(Flow $flow, CarbonImmutable $from): array
    {
        return FlowEventEmission::withoutGlobalScopes()
            ->where('team_id', $flow->team_id)
            ->where('flow_id', $flow->getKey())
            ->where('occurred_at', '>=', $from)
            ->selectRaw('event_name, COUNT(*) as emissions_count')
            ->groupBy('event_name')
            ->orderByDesc('emissions_count')
            ->get()
            ->map(fn (FlowEventEmission $emission): array => [
                'name' => $emission->event_name,
                'count' => (int) $emission->getAttribute('emissions_count'),
            ])
            ->values()
            ->all();

    }

    /** @return list<array<string, mixed>> */
    private function funnelNodes(Flow $flow): array
    {
        $version = $flow->relationLoaded('versions')
            ? $flow->versions->sortByDesc('number')->first()
            : $flow->versions()->latest('number')->first();
        $nodes = is_array($version?->dsl['nodes'] ?? null)
            ? array_values(array_filter($version->dsl['nodes'], 'is_array'))
            : (is_array($flow->draft_dsl['nodes'] ?? null) ? array_values(array_filter($flow->draft_dsl['nodes'], 'is_array')) : []);
        $edges = $version?->dsl['edges'] ?? $flow->draft_dsl['edges'] ?? [];
        $nodesById = collect($nodes)->keyBy(fn (array $node): string => (string) ($node['id'] ?? ''));
        $incoming = collect($edges)
            ->filter(fn (mixed $edge): bool => is_array($edge))
            ->groupBy(fn (array $edge): string => (string) ($edge['target'] ?? ''));
        $queue = $nodesById
            ->keys()
            ->filter(fn (string $nodeId): bool => $incoming->get($nodeId, collect())->isEmpty())
            ->values()
            ->all();
        $orderedIds = [];

        while ($queue !== []) {
            $nodeId = array_shift($queue);
            if (! is_string($nodeId) || in_array($nodeId, $orderedIds, true)) {
                continue;
            }
            $orderedIds[] = $nodeId;
            foreach (collect($edges)->filter(fn (mixed $edge): bool => is_array($edge) && ($edge['source'] ?? null) === $nodeId) as $edge) {
                $target = $edge['target'] ?? null;
                if (is_string($target) && ! in_array($target, $orderedIds, true)) {
                    $queue[] = $target;
                }
            }
        }

        foreach ($nodesById->keys() as $nodeId) {
            if (! in_array($nodeId, $orderedIds, true)) {
                $orderedIds[] = $nodeId;
            }
        }

        return array_values(array_filter(array_map(
            fn (string $nodeId): mixed => $nodesById->get($nodeId),
            $orderedIds,
        ), 'is_array'));
    }

    /** @return list<array<string, mixed>> */
    private function funnelEdges(Flow $flow): array
    {
        $version = $flow->relationLoaded('versions')
            ? $flow->versions->sortByDesc('number')->first()
            : $flow->versions()->latest('number')->first();
        $edges = $version?->dsl['edges'] ?? $flow->draft_dsl['edges'] ?? [];

        return is_array($edges) ? array_values(array_filter($edges, 'is_array')) : [];
    }

    /** @param array<string, mixed> $node */
    private function nodeLabel(array $node): string
    {
        $label = $node['label'] ?? data_get($node, 'config.label') ?? data_get($node, 'config.title');

        return is_string($label) && filled($label) ? $label : Str::headline((string) ($node['type'] ?? 'Nó'));
    }

    private function errorLabel(string $error): string
    {
        $known = [
            'A Requisição HTTP falhou.' => 'Requisição HTTP falhou',
            'invalid_capture' => 'Resposta inválida',
            'agent_inactive' => 'Agente inativo',
        ];

        return $known[$error] ?? Str::headline(Str::replace('_', ' ', $error));
    }

    private function stuckCount(Flow $flow, CarbonImmutable $from): int
    {
        return (int) $flow->executions()
            ->where('flow_executions.created_at', '>=', $from)
            ->stuck()
            ->count();
    }
}
