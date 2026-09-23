<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\SchedulableCalendarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
 * @property string $integration_id
 * @property string $google_calendar_id
 * @property string $display_name
 * @property string|null $display_description
 * @property int $duration_minutes
 * @property int $minimum_notice_minutes
 * @property array<string, array{enabled?: bool, open?: string|null, close?: string|null}>|null $business_hours
 * @property-read Team $team
 * @property-read Integration $integration
 * @property-read Collection<int, Agent> $agents
 * @property-read Collection<int, Appointment> $appointments
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'team_id',
    'integration_id',
    'google_calendar_id',
    'display_name',
    'display_description',
    'duration_minutes',
    'minimum_notice_minutes',
    'business_hours',
])]
class SchedulableCalendar extends Model
{
    /** @use HasFactory<SchedulableCalendarFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsToMany<Agent, $this> */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_schedulable_calendar');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    protected function casts(): array
    {
        return ['business_hours' => 'array'];
    }
}
