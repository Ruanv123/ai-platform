<?php

namespace App\Console\Commands;

use App\Models\FlowEventDelivery;
use Illuminate\Console\Command;

class PruneFlowEventDeliveries extends Command
{
    protected $signature = 'flows:prune-event-deliveries';

    protected $description = 'Remove entregas de Eventos antigas conforme a retenção configurada.';

    public function handle(): int
    {
        $deleted = 0;
        $retentionDays = (int) config('flows.event_delivery_retention_days', 90);

        do {
            $ids = FlowEventDelivery::withoutGlobalScopes()
                ->where('created_at', '<', now()->subDays($retentionDays))
                ->limit((int) config('flows.trace_prune_batch_size', 500))
                ->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }

            $deleted += FlowEventDelivery::withoutGlobalScopes()->whereIn('id', $ids)->delete();
        } while (true);

        $this->info("{$deleted} entregas removidas.");

        return self::SUCCESS;
    }
}
