<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\AgentConversationAuditFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $contact_id
 * @property string $channel_id
 * @property int|null $previous_user_id
 * @property int|null $new_user_id
 * @property int|null $actor_user_id
 * @property string $action
 * @property Carbon|null $created_at
 */
#[Fillable([
    'team_id',
    'contact_id',
    'channel_id',
    'previous_user_id',
    'new_user_id',
    'actor_user_id',
    'action',
])]
class AgentConversationAudit extends Model
{
    /** @use HasFactory<AgentConversationAuditFactory> */
    use BelongsToTeam, HasFactory;

    public const UPDATED_AT = null;
}
