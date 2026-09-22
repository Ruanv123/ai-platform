<?php

namespace Database\Factories;

use App\Enums\FlowExecutionStatus;
use App\Models\Contact;
use App\Models\FlowExecution;
use App\Models\FlowVersion;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FlowExecution> */
class FlowExecutionFactory extends Factory
{
    protected $model = FlowExecution::class;

    public function configure(): static
    {
        return $this->afterMaking(function (FlowExecution $execution): void {
            if ($execution->flow_version_id !== '') {
                $execution->team_id = (int) FlowVersion::withoutGlobalScopes()
                    ->whereKey($execution->flow_version_id)
                    ->value('team_id');
            }
        });
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'flow_version_id' => FlowVersion::factory(),
            'contact_id' => Contact::factory(),
            'channel_id' => null,
            'current_node_id' => 'message',
            'variables' => [],
            'trace' => [],
            'emitted_events' => [],
            'queued_triggers' => [],
            'status' => FlowExecutionStatus::Running,
            'waiting_for' => null,
            'human_assigned_user_id' => null,
            'human_assigned_at' => null,
            'human_last_activity_at' => null,
            'human_transfer_reason' => null,
            'wake_at' => null,
            'expires_at' => now()->addDays(30),
            'attempts' => 0,
            'depth' => 0,
            'last_activity_at' => now(),
            'final_reason' => null,
        ];
    }

    public function waiting(): static
    {
        return $this->state([
            'status' => FlowExecutionStatus::Waiting,
            'waiting_for' => 'contact_response',
        ]);
    }

    public function waitingForHuman(?Contact $contact = null): static
    {
        return $this->state([
            'contact_id' => $contact?->getKey(),
            'status' => FlowExecutionStatus::Paused,
            'waiting_for' => 'human',
            'human_transfer_reason' => 'contact_requested',
            'human_last_activity_at' => now(),
            'wake_at' => now()->addDay(),
        ]);
    }

    public function failed(string $reason = 'node_failed'): static
    {
        return $this->state([
            'status' => FlowExecutionStatus::Failed,
            'final_reason' => $reason,
        ]);
    }
}
