<?php

namespace App\Models;

use Database\Factories\WhatsAppPhoneNumberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'channel_id',
    'whatsapp_account_id',
    'phone_number_id',
    'display_phone_number',
    'verified_name',
    'quality_rating',
    'name_status',
    'messaging_limit_tier',
    'registration_pin',
    'registered_at',
])]
#[Hidden(['registration_pin'])]

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

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    protected function casts(): array
    {
        return [
            'registration_pin' => 'encrypted',
            'registered_at' => 'datetime',
        ];
    }
}
