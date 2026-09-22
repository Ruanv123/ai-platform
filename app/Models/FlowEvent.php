<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\FlowEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $id */
#[Fillable(['team_id', 'name', 'description', 'payload_schema'])]
class FlowEvent extends Model
{
    /** @use HasFactory<FlowEventFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return HasMany<FlowEventEmission, $this> */
    public function emissions(): HasMany
    {
        return $this->hasMany(FlowEventEmission::class);
    }

    /** @return HasMany<FlowEventWebhook, $this> */
    public function webhooks(): HasMany
    {
        return $this->hasMany(FlowEventWebhook::class);
    }

    protected function casts(): array
    {
        return ['payload_schema' => 'array'];
    }
}
