<?php

namespace App\Console\Commands;

use App\Models\FlowExecution;
use App\Models\FlowExecutionStep;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('flows:prune-traces')]
#[Description('Remove os detalhes de Traço vencidos, preservando os agregados.')]
class PruneFlowTraces extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) config('flows.trace_retention_days', 30)));
        $batchSize = max(50, (int) config('flows.trace_prune_batch_size', 500));
        $deletedSteps = 0;
        $prunedExecutions = 0;

        FlowExecutionStep::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->chunkById($batchSize, function ($steps) use (&$deletedSteps): void {
                $deletedSteps += FlowExecutionStep::withoutGlobalScopes()
                    ->whereIn('id', $steps->pluck('id'))
                    ->delete();
            });

        FlowExecution::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->whereNull('trace_pruned_at')
            ->chunkById($batchSize, function ($executions) use (&$prunedExecutions): void {
                $prunedExecutions += FlowExecution::withoutGlobalScopes()
                    ->whereIn('id', $executions->pluck('id'))
                    ->update([
                        'variables' => [],
                        'trace' => [],
                        'emitted_events' => [],
                        'queued_triggers' => [],
                        'trace_pruned_at' => now(),
                    ]);
            });

        $this->info("{$deletedSteps} passos removidos; {$prunedExecutions} Execuções podadas.");

        return self::SUCCESS;
    }
}
