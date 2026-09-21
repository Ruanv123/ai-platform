<?php

namespace Database\Factories;

use App\Enums\FlowCollisionPolicy;
use App\Enums\FlowStatus;
use App\Enums\FlowTriggerType;
use App\Models\Channel;
use App\Models\Flow;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Flow> */
class FlowFactory extends Factory
{
    protected $model = Flow::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Flow $flow): void {
            if ($flow->channel_id !== null) {
                $flow->team_id = (int) Channel::withoutGlobalScopes()->whereKey($flow->channel_id)->value('team_id');
            }
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->unique()->words(2, true),
            'status' => FlowStatus::Draft,
            'draft_dsl' => [
                'version' => 1,
                'nodes' => [['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Olá.']]],
                'edges' => [],
            ],
            'trigger_type' => FlowTriggerType::Message,
            'trigger_event' => null,
            'trigger_token_hash' => null,
            'collision_policy' => FlowCollisionPolicy::Ignore,
            'allow_unknown_contact' => false,
            'is_trivial' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => FlowStatus::Published]);
    }

    public function paused(): static
    {
        return $this->state(['status' => FlowStatus::Paused]);
    }
}
