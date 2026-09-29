<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\ConversationReadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $contact_id
 * @property string $channel_id
 * @property int $last_read_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'team_id',
    'contact_id',
    'channel_id',
    'last_read_message_id',
])]
class ConversationRead extends Model
{
    /** @use HasFactory<ConversationReadFactory> */
    use BelongsToTeam, HasFactory;
}
