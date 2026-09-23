<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string|null $schedulable_calendar_id
 * @property int $contact_id
 * @property string $google_event_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string|null $reason
 * @property string $status
 * @property-read Team $team
 * @property-read SchedulableCalendar|null $schedulableCalendar
 * @property-read Contact $contact
 */
#[Fillable([
    'team_id',
    'schedulable_calendar_id',
    'contact_id',
    'google_event_id',
    'starts_at',
    'ends_at',
    'reason',
    'status',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return BelongsTo<SchedulableCalendar, $this> */
    public function schedulableCalendar(): BelongsTo
    {
        return $this->belongsTo(SchedulableCalendar::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
