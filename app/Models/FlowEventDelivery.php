<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\FlowEventDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'flow_event_id', 'flow_event_webhook_id', 'flow_event_emission_id', 'status', 'attempts', 'response_status', 'error', 'payload', 'next_attempt_at', 'last_attempt_at', 'delivered_at'])]
class FlowEventDelivery extends Model
{
    /** @use HasFactory<FlowEventDeliveryFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return BelongsTo<FlowEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(FlowEvent::class, 'flow_event_id');
    }

    /** @return BelongsTo<FlowEvent, $this> */
    public function flowEvent(): BelongsTo
    {
        return $this->event();
    }

    /** @return BelongsTo<FlowEventWebhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(FlowEventWebhook::class, 'flow_event_webhook_id');
    }

    /** @return BelongsTo<FlowEventWebhook, $this> */
    public function flowEventWebhook(): BelongsTo
    {
        return $this->webhook();
    }

    /** @return BelongsTo<FlowEventEmission, $this> */
    public function emission(): BelongsTo
    {
        return $this->belongsTo(FlowEventEmission::class, 'flow_event_emission_id');
    }

    /** @return BelongsTo<FlowEventEmission, $this> */
    public function flowEventEmission(): BelongsTo
    {
        return $this->emission();
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'next_attempt_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }
}
