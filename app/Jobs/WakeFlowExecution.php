<?php

namespace App\Jobs;

use App\Flows\FlowEngine;
use App\Models\FlowExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WakeFlowExecution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $executionId) {}

    public function handle(FlowEngine $engine): void
    {
        $execution = FlowExecution::withoutGlobalScopes()->find($this->executionId);
        if ($execution !== null) {
            $engine->wake($execution);
        }
    }
}
