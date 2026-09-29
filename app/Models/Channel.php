<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ChannelStatus;
use App\Enums\ChannelType;
use Database\Factories\ChannelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string|null $agent_id
 * @property ChannelType $type
 * @property ChannelStatus $status
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Agent|null $agent
 * @property-read Collection<int, Message> $messages
 * @property-read Collection<int, Flow> $flows
 * @property-read WhatsAppPhoneNumber|null $whatsappPhoneNumber
 * @property-read WhatsAppTestConnection|null $whatsappTestConnection
 */
#[Fillable(['team_id', 'type', 'agent_id', 'status', 'last_error'])]

class Channel extends Model
{
    /** @use HasFactory<ChannelFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::deleting(function (Channel $channel): void {
            $channel->messages()
                ->whereNotNull('media_path')
                ->orderBy('id')
                ->chunkById(100, function (Collection $messages): void {
                    foreach ($messages as $message) {
                        $message->delete();
                    }
                });
        });
    }

    /**
     * Get the Agent that responds in this channel.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the messages that passed through this channel.
     *
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** @return HasMany<Flow, $this> */
    public function flows(): HasMany
    {
        return $this->hasMany(Flow::class);
    }

    /**
     * Get the WhatsApp number attached to this channel.
     *
     * @return HasOne<WhatsAppPhoneNumber, $this>
     */
    public function whatsappPhoneNumber(): HasOne
    {
        return $this->hasOne(WhatsAppPhoneNumber::class);
    }

    public function whatsappConnectionAttempt(): HasOne
    {
        return $this->hasOne(WhatsAppConnectionAttempt::class);
    }

    /**
     * Get the non-official WhatsApp connection attached to this channel.
     *
     * @return HasOne<WhatsAppTestConnection, $this>
     */
    public function whatsappTestConnection(): HasOne
    {
        return $this->hasOne(WhatsAppTestConnection::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'status' => ChannelStatus::class,
        ];
    }
}
