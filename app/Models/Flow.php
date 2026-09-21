<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\FlowCollisionPolicy;
use App\Enums\FlowStatus;
use App\Enums\FlowTriggerType;
use Database\Factories\FlowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $team_id
 * @property string|null $channel_id
 * @property string $name
 * @property FlowStatus $status
 * @property array<string, mixed> $draft_dsl
 * @property FlowTriggerType $trigger_type
 * @property string|null $trigger_event
 * @property string|null $trigger_token_hash
 * @property FlowCollisionPolicy $collision_policy
 * @property bool $allow_unknown_contact
 * @property bool $is_trivial
 * @property-read Team $team
 * @property-read Channel|null $channel
 * @property-read Collection<int, FlowVersion> $versions
 * @property-read Collection<int, FlowExecution> $executions
 */
#[Fillable([
    'team_id',
    'channel_id',
    'name',
    'status',
    'draft_dsl',
    'trigger_type',
    'trigger_event',
    'trigger_token_hash',
    'collision_policy',
    'allow_unknown_contact',
    'is_trivial',
])]
class Flow extends Model
{
    /** @use HasFactory<FlowFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return HasMany<FlowVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FlowVersion::class);
    }

    /** @return HasMany<FlowExecution, $this> */
    public function executions(): HasMany
    {
        return $this->hasMany(FlowExecution::class);
    }

    public function publishedVersion(): ?FlowVersion
    {
        return $this->versions()->latest('number')->first();
    }

    public function hasUnpublishedChanges(): bool
    {
        $version = $this->publishedVersion();

        return $version === null || $version->dsl !== $this->draft_dsl;
    }

    protected function casts(): array
    {
        return [
            'status' => FlowStatus::class,
            'draft_dsl' => 'array',
            'trigger_type' => FlowTriggerType::class,
            'collision_policy' => FlowCollisionPolicy::class,
            'allow_unknown_contact' => 'boolean',
            'is_trivial' => 'boolean',
        ];
    }
}
