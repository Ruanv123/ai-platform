<?php

namespace App\Console\Commands;

use App\Flows\FlowEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('flows:reconcile')]
#[Description('Acorda Execuções de Fluxo vencidas e identifica Execuções presas.')]
class ReconcileFlowExecutions extends Command
{
    public function handle(FlowEngine $engine): int
    {
        $this->info("{$engine->reconcile()} Execuções reconciliadas.");

        return self::SUCCESS;
    }
}
