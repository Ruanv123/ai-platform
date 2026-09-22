<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\FlowEventWebhookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'flow_event_id', 'url', 'secret', 'active'])]
#[Hidden(['secret'])]
class FlowEventWebhook extends Model
{
    /** @use HasFactory<FlowEventWebhookFactory> */
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

    /** @return HasMany<FlowEventDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(FlowEventDelivery::class);
    }

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'active' => 'boolean',
        ];
    }
}
