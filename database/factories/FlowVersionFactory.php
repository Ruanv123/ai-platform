<?php

namespace Database\Factories;

use App\Enums\FlowCategory;
use App\Models\Flow;
use App\Models\FlowVersion;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlowVersion> */
class FlowVersionFactory extends Factory
{
    protected $model = FlowVersion::class;

    public function configure(): static
    {
        return $this->afterMaking(function (FlowVersion $version): void {
            if ($version->flow_id !== '') {
                $version->team_id = (int) Flow::withoutGlobalScopes()->whereKey($version->flow_id)->value('team_id');
            }
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'flow_id' => Flow::factory(),
            'number' => 1,
            'dsl' => [
                'version' => 1,
                'nodes' => [['id' => 'message', 'type' => 'message', 'config' => ['text' => 'Olá.']]],
                'edges' => [],
            ],
            'category' => FlowCategory::Reaction,
            'published_at' => now(),
        ];
    }

    public function conversation(): static
    {
        return $this->state(['category' => FlowCategory::Conversation]);
    }

    public function reaction(): static
    {
        return $this->state(['category' => FlowCategory::Reaction]);
    }
}
