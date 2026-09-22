<?php

namespace Database\Factories;

use App\Models\Flow;
use App\Models\FlowEvent;
use App\Models\FlowEventEmission;
use App\Models\FlowExecution;
use App\Models\FlowVersion;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlowEventEmission> */
class FlowEventEmissionFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();
        $flow = Flow::factory()->for($team);
        $version = FlowVersion::factory()->for($flow);
        $execution = FlowExecution::factory()->for($version);
        $event = FlowEvent::factory()->for($team);

        return [
            'team_id' => $team,
            'flow_event_id' => $event,
            'flow_execution_id' => $execution,
            'flow_id' => $flow,
            'flow_version_id' => $version,
            'event_name' => 'lead.qualified',
            'payload' => ['contact_id' => 1],
            'occurred_at' => now(),
        ];
    }
}
