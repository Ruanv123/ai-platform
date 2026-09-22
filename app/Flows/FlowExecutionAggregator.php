<?php

namespace App\Flows;

use App\Enums\FlowExecutionStatus;
use App\Models\FlowExecution;
use App\Models\FlowExecutionAggregate;
use App\Models\FlowExecutionErrorAggregate;
use App\Models\FlowExecutionStep;
use Illuminate\Support\Str;

final class FlowExecutionAggregator
{
    public function recordStarted(FlowExecution $execution): void
    {
        $aggregate = $this->flowAggregate($execution, $this->periodDate($execution));
        $aggregate->increment('started_count');
    }

    public function recordStep(FlowExecution $execution, FlowExecutionStep $step): void
    {
        $aggregate = $this->nodeAggregate(
            $execution,
            $step->node_id,
            $step->started_at?->toDateString() ?? $this->periodDate($execution),
        );
        $aggregate->increment('entered_count');

        if ($step->result === 'completed') {
            $aggregate->increment('exited_count');
        }
        if ($step->result === 'failed') {
            $aggregate->increment('failed_count');
        }

        $cost = (float) ($step->ai_cost_brl ?? 0);
        if ($cost > 0) {
            $aggregate->increment('ai_cost_brl', $cost);
            $this->flowAggregate($execution, $this->periodDate($execution))->increment('ai_cost_brl', $cost);
        }

        if (filled($step->error)) {
            $error = FlowExecutionErrorAggregate::withoutGlobalScopes()->firstOrCreate([
                'team_id' => $execution->team_id,
                'flow_id' => $this->flowId($execution),
                'flow_version_id' => $execution->flow_version_id,
                'node_id' => $step->node_id,
                'error_code' => Str::limit((string) $step->error, 255, ''),
                'period_date' => $step->started_at?->toDateString() ?? $this->periodDate($execution),
            ], ['occurrences' => 0]);
            $error->increment('occurrences');
        }
    }

    public function recordFinished(FlowExecution $execution, FlowExecutionStatus $status): void
    {
        if ($execution->metrics_finalized_at !== null) {
            return;
        }

        $finalized = FlowExecution::withoutGlobalScopes()
            ->whereKey($execution->getKey())
            ->whereNull('metrics_finalized_at')
            ->update(['metrics_finalized_at' => now()]);
        if ($finalized === 0) {
            return;
        }

        $aggregate = $this->flowAggregate($execution, $this->periodDate($execution));
        $column = match ($status) {
            FlowExecutionStatus::Completed => 'completed_count',
            FlowExecutionStatus::Failed => 'failed_count',
            FlowExecutionStatus::Abandoned => 'abandoned_count',
            FlowExecutionStatus::Stuck => 'stuck_count',
            default => null,
        };
        if ($column !== null) {
            $aggregate->increment($column);
        }
    }

    public function recordStuck(FlowExecution $execution): void
    {
        $aggregate = $this->flowAggregate($execution, now()->toDateString());
        $aggregate->increment('stuck_count');
    }

    private function flowAggregate(FlowExecution $execution, string $periodDate): FlowExecutionAggregate
    {
        return FlowExecutionAggregate::withoutGlobalScopes()->firstOrCreate([
            'team_id' => $execution->team_id,
            'flow_id' => $this->flowId($execution),
            'flow_version_id' => $execution->flow_version_id,
            'node_id' => '',
            'period_date' => $periodDate,
        ]);
    }

    private function nodeAggregate(FlowExecution $execution, string $nodeId, string $periodDate): FlowExecutionAggregate
    {
        return FlowExecutionAggregate::withoutGlobalScopes()->firstOrCreate([
            'team_id' => $execution->team_id,
            'flow_id' => $this->flowId($execution),
            'flow_version_id' => $execution->flow_version_id,
            'node_id' => $nodeId,
            'period_date' => $periodDate,
        ]);
    }

    private function flowId(FlowExecution $execution): string
    {
        if ($execution->relationLoaded('flowVersion') && $execution->flowVersion !== null) {
            return (string) $execution->flowVersion->flow_id;
        }

        return (string) $execution->flowVersion()->withoutGlobalScopes()->value('flow_id');
    }

    private function periodDate(FlowExecution $execution): string
    {
        return $execution->created_at?->toDateString() ?? now()->toDateString();
    }
}
