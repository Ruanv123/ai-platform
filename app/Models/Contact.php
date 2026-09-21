<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $phone
 * @property string|null $name
 * @property bool $human_waiting
 * @property Carbon|null $human_waiting_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Message> $messages
 * @property-read Collection<int, ContactFieldValue> $fieldValues
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, FlowExecution> $flowExecutions
 */
#[Fillable(['team_id', 'phone', 'name', 'human_waiting', 'human_waiting_at'])]
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

    /** @return HasMany<ContactFieldValue, $this> */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(ContactFieldValue::class);
    }

    /** @return BelongsToMany<Tag, $this, Pivot, 'pivot'> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tag');
    }

    /** @return HasMany<FlowExecution, $this> */
    public function flowExecutions(): HasMany
    {
        return $this->hasMany(FlowExecution::class);
    }

    protected function casts(): array
    {
        return [
            'human_waiting' => 'boolean',
            'human_waiting_at' => 'immutable_datetime',
        ];
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
