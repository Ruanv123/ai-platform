<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\FlowExecutionStatus;
use Database\Factories\FlowExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property Carbon|null $wake_at
 * @property Carbon $expires_at
 * @property int $attempts
 * @property int $depth
 * @property Carbon|null $last_activity_at
 * @property string|null $final_reason
 * @property-read Team $team
 * @property-read FlowVersion $flowVersion
 * @property-read Contact|null $contact
 * @property-read Channel|null $channel
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
    'wake_at',
    'expires_at',
    'attempts',
    'depth',
    'last_activity_at',
    'final_reason',
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

    /**
     * Scope executions whose wake-up is overdue enough to investigate.
     *
     * @param  Builder<FlowExecution>  $query
     * @return Builder<FlowExecution>
     */
    public function scopeStuck(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [FlowExecutionStatus::Waiting, FlowExecutionStatus::Paused])
            ->whereNotNull('wake_at')
            ->where('wake_at', '<', now()->subMinutes(5));
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
            'wake_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
        ];
    }
}
