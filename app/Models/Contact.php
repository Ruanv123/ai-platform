<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $phone
 * @property string|null $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Message> $messages
 */
#[Fillable(['team_id', 'phone', 'name'])]
class Contact extends Model
{
    public const int MAX_HISTORY_MESSAGES = 50;

    /** @use HasFactory<ContactFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the messages exchanged with this contact.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the recent history for this contact in a channel.
     *
     * History starts at the most recent message and stops at the first gap
     * longer than 24 hours, then returns messages in chronological order.
     *
     * @return Collection<int, Message>
     */
    public function history(Channel $channel, int $limit = self::MAX_HISTORY_MESSAGES): Collection
    {
        if ($this->team_id !== $channel->team_id || $limit <= 0) {
            return (new Message)->newCollection();
        }

        $messages = $this->messages()
            ->where('channel_id', $channel->getKey())
            ->latest('created_at')
            ->latest('id')
            ->get();
        $history = (new Message)->newCollection();
        $previousMessageTime = null;

        foreach ($messages as $message) {
            if (
                $previousMessageTime !== null
                && $previousMessageTime->diffInSeconds($message->created_at, true) > 24 * 60 * 60
            ) {
                break;
            }

            $history->push($message);
            $previousMessageTime = $message->created_at;
        }

        return $history
            ->take(min($limit, self::MAX_HISTORY_MESSAGES))
            ->reverse()
            ->values();
    }
}
