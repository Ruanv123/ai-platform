<?php

namespace App\Models;

use Database\Factories\WhatsAppPhoneNumberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $channel_id
 * @property string $phone_number_id
 * @property string|null $display_phone_number
 * @property string|null $verified_name
 * @property string|null $quality_rating
 * @property-read Channel $channel
 * @property-read WhatsAppAccount|null $whatsappAccount
 */
#[Fillable([
    'channel_id',
    'phone_number_id',
    'display_phone_number',
    'verified_name',
    'quality_rating',
])]

class WhatsAppPhoneNumber extends Model
{
    protected $table = 'whatsapp_phone_numbers';

    /** @use HasFactory<WhatsAppPhoneNumberFactory> */
    use HasFactory;

    /**
     * Get the Channel served by this WhatsApp number.
     *
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the Meta account that owns this number.
     *
     * @return HasOne<WhatsAppAccount, $this>
     */
    public function whatsappAccount(): HasOne
    {
        return $this->hasOne(WhatsAppAccount::class, 'whatsapp_phone_number_id');
    }
}
