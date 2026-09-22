<?php

namespace Database\Factories;

use App\Models\Flow;
use App\Models\FlowExecutionErrorAggregate;
use App\Models\FlowVersion;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlowExecutionErrorAggregate> */
class FlowExecutionErrorAggregateFactory extends Factory
{
    protected $model = FlowExecutionErrorAggregate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'flow_id' => Flow::factory(),
            'flow_version_id' => FlowVersion::factory(),
            'node_id' => 'node',
            'error_code' => 'node_failed',
            'period_date' => now()->toDateString(),
            'occurrences' => 1,
        ];
    }
}
