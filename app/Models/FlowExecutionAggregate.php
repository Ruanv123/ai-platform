<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\FlowExecutionAggregateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $flow_id
 * @property string $flow_version_id
 * @property string $node_id
 * @property Carbon $period_date
 * @property int $started_count
 * @property int $completed_count
 * @property int $abandoned_count
 * @property int $failed_count
 * @property int $stuck_count
 * @property int $entered_count
 * @property int $exited_count
 * @property string $ai_cost_brl
 */
#[Fillable([
    'team_id',
    'flow_id',
    'flow_version_id',
    'node_id',
    'period_date',
    'started_count',
    'completed_count',
    'abandoned_count',
    'failed_count',
    'stuck_count',
    'entered_count',
    'exited_count',
    'ai_cost_brl',
])]
class FlowExecutionAggregate extends Model
{
    /** @use HasFactory<FlowExecutionAggregateFactory> */
    use BelongsToTeam, HasFactory;

    /** @return BelongsTo<Flow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /** @return BelongsTo<FlowVersion, $this> */
    public function flowVersion(): BelongsTo
    {
        return $this->belongsTo(FlowVersion::class);
    }

    protected function casts(): array
    {
        return [
            'period_date' => 'date',
            'ai_cost_brl' => 'decimal:4',
        ];
    }
}
