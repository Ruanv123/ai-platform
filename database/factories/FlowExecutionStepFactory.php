<?php

namespace Database\Factories;

use App\Models\FlowExecution;
use App\Models\FlowExecutionStep;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlowExecutionStep> */
class FlowExecutionStepFactory extends Factory
{
    protected $model = FlowExecutionStep::class;

    public function configure(): static
    {
        return $this->afterMaking(function (FlowExecutionStep $step): void {
            if ($step->flow_execution_id !== '') {
                $step->team_id = (int) FlowExecution::withoutGlobalScopes()
                    ->whereKey($step->flow_execution_id)
                    ->value('team_id');
            }
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'flow_execution_id' => FlowExecution::factory(),
            'sequence' => 1,
            'node_id' => 'message',
            'result' => 'completed',
            'duration_ms' => fake()->numberBetween(1, 500),
            'edge_id' => null,
            'edge_source_node_id' => null,
            'edge_target_node_id' => null,
            'edge_label' => null,
            'error' => null,
            'variables_snapshot' => [],
            'started_at' => now(),
        ];
    }

    public function failed(string $error = 'Falha ao executar o Nó.'): static
    {
        return $this->state([
            'result' => 'failed',
            'error' => $error,
        ]);
    }
}
