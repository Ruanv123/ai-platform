<?php

namespace Database\Factories;

use App\Models\FlowEventDelivery;
use App\Models\FlowEventEmission;
use App\Models\FlowEventWebhook;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlowEventDelivery> */
class FlowEventDeliveryFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();
        $event = FlowEventWebhook::factory()->for($team);
        $emission = FlowEventEmission::factory()->for($team);

        return [
            'team_id' => $team,
            'flow_event_id' => $event->flow_event_id,
            'flow_event_webhook_id' => $event,
            'flow_event_emission_id' => $emission,
            'status' => 'failed',
            'attempts' => 4,
            'response_status' => 503,
            'error' => 'O destino não respondeu.',
            'payload' => ['event' => 'lead.qualified', 'payload' => []],
            'next_attempt_at' => null,
            'last_attempt_at' => now()->subMinute(),
            'delivered_at' => null,
        ];
    }
}
