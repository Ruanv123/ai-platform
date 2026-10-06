<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property AgentObjective $objective
 * @property AgentTone $tone
 * @property bool $uses_emojis
 * @property AgentResponseLength $response_length
 * @property string $welcome_message
 * @property array<int, string> $instructions
 * @property array<int, string> $rules
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collection> $collections
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SchedulableCalendar> $schedulableCalendars
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Channel> $channels
 */
#[Fillable([
    'team_id',
    'name',
    'description',
    'objective',
    'tone',
    'uses_emojis',
    'response_length',
    'welcome_message',
    'instructions',
    'rules',
    'is_active',
])]

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /**
     * Get the Empresa that owns the Agent.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the knowledge collections available to the Agent.
     *
     * @return BelongsToMany<Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'agent_collection');
    }

    /** @return BelongsToMany<SchedulableCalendar, $this> */
    public function schedulableCalendars(): BelongsToMany
    {
        return $this->belongsToMany(SchedulableCalendar::class, 'agent_schedulable_calendar');
    }

    /**
     * Get the Channels served by this Agent.
     *
     * @return HasMany<Channel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class);
    }

    public function contactMemories(): HasMany
    {
        return $this->hasMany(AgentContactMemory::class);
    }

    public function publishedVersion(): ?AgentVersion
    {
        return $this->versions()->latest('number')->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'objective' => AgentObjective::class,
            'tone' => AgentTone::class,
            'uses_emojis' => 'boolean',
            'response_length' => AgentResponseLength::class,
            'instructions' => 'array',
            'rules' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
