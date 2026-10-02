<?php

namespace App\Console\Commands;

use App\Models\CrmOperation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('crm:prune-operations')]
#[Description('Remove Operações do CRM com mais de 30 dias.')]
class PruneCrmOperations extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(30);
        $deleted = 0;

        CrmOperation::withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->chunkById(500, function (Collection $operations) use (&$deleted): void {
                $deleted += CrmOperation::withoutGlobalScopes()
                    ->whereIn('id', $operations->pluck('id'))
                    ->delete();
            });

        $this->info("{$deleted} Operações do CRM removidas.");

        return self::SUCCESS;
    }
}
