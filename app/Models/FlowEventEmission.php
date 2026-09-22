<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\FlowEventEmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $id */
#[Fillable(['team_id', 'flow_event_id', 'flow_execution_id', 'flow_id', 'flow_version_id', 'event_name', 'payload', 'occurred_at'])]
class FlowEventEmission extends Model
{
    /** @use HasFactory<FlowEventEmissionFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return BelongsTo<FlowEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(FlowEvent::class, 'flow_event_id');
    }

    /** @return BelongsTo<FlowExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(FlowExecution::class, 'flow_execution_id');
    }

    /** @return BelongsTo<Flow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /** @return HasMany<FlowEventDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(FlowEventDelivery::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
