<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\FlowExecutionStatus;
use Database\Factories\FlowExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $flow_version_id
 * @property int|null $contact_id
 * @property string|null $channel_id
 * @property string|null $current_node_id
 * @property array<string, mixed> $variables
 * @property array<int, array<string, mixed>> $trace
 * @property array<int, string> $emitted_events
 * @property array<int, array<string, mixed>> $queued_triggers
 * @property FlowExecutionStatus $status
 * @property string|null $waiting_for
 * @property int|null $human_assigned_user_id
 * @property Carbon|null $human_assigned_at
 * @property Carbon|null $human_last_activity_at
 * @property string|null $human_transfer_reason
 * @property Carbon|null $wake_at
 * @property Carbon $expires_at
 * @property int $attempts
 * @property int $depth
 * @property Carbon|null $last_activity_at
 * @property string|null $final_reason
 * @property string $ai_cost_brl
 * @property Carbon|null $metrics_finalized_at
 * @property Carbon|null $trace_pruned_at
 * @property-read Team $team
 * @property-read FlowVersion $flowVersion
 * @property-read Contact|null $contact
 * @property-read Channel|null $channel
 * @property-read Collection<int, FlowExecutionStep> $steps
 * @property-read User|null $humanAssignee
 */
#[Fillable([
    'team_id',
    'flow_version_id',
    'contact_id',
    'channel_id',
    'current_node_id',
    'variables',
    'trace',
    'emitted_events',
    'queued_triggers',
    'status',
    'waiting_for',
    'human_assigned_user_id',
    'human_assigned_at',
    'human_last_activity_at',
    'human_transfer_reason',
    'wake_at',
    'expires_at',
    'attempts',
    'depth',
    'last_activity_at',
    'final_reason',
    'ai_cost_brl',
    'metrics_finalized_at',
    'trace_pruned_at',
])]
class FlowExecution extends Model
{
    /** @use HasFactory<FlowExecutionFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return BelongsTo<FlowVersion, $this> */
    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function humanAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'human_assigned_user_id');
    }

    /** @return HasMany<FlowExecutionStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(FlowExecutionStep::class)->orderBy('sequence');
    }

    /**
     * Scope executions whose wake-up is overdue enough to investigate.
     *
     * @param  Builder<FlowExecution>  $query
     * @return Builder<FlowExecution>
     */
    public function scopeStuck(Builder $query): Builder
    {
        $executionTable = $query->getModel()->getTable();

        return $query->where(function (Builder $query) use ($executionTable): void {
            $query->where($executionTable.'.status', FlowExecutionStatus::Stuck)
                ->orWhere(function (Builder $query) use ($executionTable): void {
                    $query->whereIn($executionTable.'.status', [FlowExecutionStatus::Waiting, FlowExecutionStatus::Paused])
                        ->whereNotNull($executionTable.'.wake_at')
                        ->where($executionTable.'.wake_at', '<', now()->subMinutes(5));
                });
        });
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            FlowExecutionStatus::Running,
            FlowExecutionStatus::Waiting,
            FlowExecutionStatus::Paused,
        ], true);
    }

    public function finish(FlowExecutionStatus $status, string $reason): void
    {
        $this->forceFill([
            'status' => $status,
            'final_reason' => $reason,
            'wake_at' => null,
            'waiting_for' => null,
            'last_activity_at' => now(),
        ])->save();
    }

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'trace' => 'array',
            'emitted_events' => 'array',
            'queued_triggers' => 'array',
            'status' => FlowExecutionStatus::class,
            'human_assigned_at' => 'immutable_datetime',
            'human_last_activity_at' => 'immutable_datetime',
            'wake_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'ai_cost_brl' => 'decimal:4',
            'metrics_finalized_at' => 'immutable_datetime',
            'trace_pruned_at' => 'immutable_datetime',
        ];
    }
}
