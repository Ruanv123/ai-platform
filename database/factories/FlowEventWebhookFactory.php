<?php

namespace Database\Factories;

use App\Models\FlowEvent;
use App\Models\FlowEventWebhook;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FlowEventWebhook> */
class FlowEventWebhookFactory extends Factory
{
    public function definition(): array
    {
        $team = Team::factory();
        $event = FlowEvent::factory()->for($team);

        return [
            'team_id' => $team,
            'flow_event_id' => $event,
            'url' => fake()->unique()->url(),
            'secret' => Str::random(64),
            'active' => true,
        ];
    }
}
