<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Events\InboxMessageCreated;
use Database\Factories\MessageFactory;
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
 * @property MessageDirection $direction
 * @property string $payload_type
 * @property string|null $provider_message_id
 * @property int|null $sent_by_user_id
 * @property string|null $content
 * @property MessageDeliveryStatus|null $delivery_status
 * @property bool $is_responded
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Contact $contact
 * @property-read Channel $channel
 * @property-read User|null $sentBy
 */
#[Fillable([
    'team_id',
    'contact_id',
    'channel_id',
    'direction',
    'payload_type',
    'provider_message_id',
    'sent_by_user_id',
    'content',
    'delivery_status',
    'is_responded',
])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Fill the tenant from the contact when a message is created outside HTTP.
     */
    protected static function booted(): void
    {
        static::creating(function (Message $message): void {
            $teamId = $message->getAttribute('team_id');
            $contactId = $message->getAttribute('contact_id');

            if ($teamId !== null || $contactId === null) {
                return;
            }

            $message->setAttribute(
                'team_id',
                Contact::withoutGlobalScopes()->whereKey($contactId)->value('team_id'),
            );
        });

        static::created(function (Message $message): void {
            if ($message->team_id === null || $message->contact_id === null || $message->channel_id === null) {
                return;
            }

            InboxMessageCreated::dispatch(
                (int) $message->team_id,
                (int) $message->contact_id,
                (string) $message->channel_id,
                (int) $message->getKey(),
            );
        });
    }

    /**
     * Get the contact who exchanged this message.
     *
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Get the channel through which this message passed.
     *
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'delivery_status' => MessageDeliveryStatus::class,
            'is_responded' => 'boolean',
        ];
    }
}
