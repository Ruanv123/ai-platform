<?php

namespace Database\Factories;

use App\Models\Flow;
use App\Models\FlowExecutionAggregate;
use App\Models\FlowVersion;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlowExecutionAggregate> */
class FlowExecutionAggregateFactory extends Factory
{
    protected $model = FlowExecutionAggregate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'flow_id' => Flow::factory(),
            'flow_version_id' => FlowVersion::factory(),
            'node_id' => '',
            'period_date' => now()->toDateString(),
            'started_count' => 0,
            'completed_count' => 0,
            'abandoned_count' => 0,
            'failed_count' => 0,
            'stuck_count' => 0,
            'entered_count' => 0,
            'exited_count' => 0,
            'ai_cost_brl' => 0,
        ];
    }
}
