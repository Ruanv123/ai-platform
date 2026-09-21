<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
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
 * @property string|null $content
 * @property MessageDeliveryStatus|null $delivery_status
 * @property bool $is_responded
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Contact $contact
 * @property-read Channel $channel
 */
#[Fillable([
    'team_id',
    'contact_id',
    'channel_id',
    'direction',
    'payload_type',
    'provider_message_id',
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
