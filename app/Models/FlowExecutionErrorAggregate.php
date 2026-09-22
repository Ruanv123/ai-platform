<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\FlowExecutionErrorAggregateFactory;
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
 * @property string $error_code
 * @property Carbon $period_date
 * @property int $occurrences
 */
#[Fillable([
    'team_id',
    'flow_id',
    'flow_version_id',
    'node_id',
    'error_code',
    'period_date',
    'occurrences',
])]
class FlowExecutionErrorAggregate extends Model
{
    /** @use HasFactory<FlowExecutionErrorAggregateFactory> */
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
        return ['period_date' => 'date'];
    }
}
