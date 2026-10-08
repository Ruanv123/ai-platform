<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\AgentConversationStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $contact_id
 * @property string $channel_id
 * @property bool $is_paused
 * @property int|null $assigned_user_id
 * @property Carbon|null $paused_at
 * @property Carbon|null $expires_at
 * @property int|null $pause_duration_minutes
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $return_requested_at
 * @property Carbon|null $return_started_at
 * @property int|null $return_requested_by_user_id
 * @property int|null $return_attempted_message_id
 * @property string|null $return_action
 */
#[Fillable([
    'team_id',
    'contact_id',
    'channel_id',
    'is_paused',
    'assigned_user_id',
    'paused_at',
    'expires_at',
    'pause_duration_minutes',
    'last_activity_at',
    'return_requested_at',
    'return_started_at',
    'return_requested_by_user_id',
    'return_attempted_message_id',
    'return_action',
    'follow_up_message_id',
])]
class AgentConversationState extends Model
{
    /** @use HasFactory<AgentConversationStateFactory> */
    use BelongsToTeam, HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_paused' => 'boolean',
            'paused_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'return_requested_at' => 'immutable_datetime',
            'return_started_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
