<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'channel_id',
    'authorization_code',
    'access_token',
    'debug_token_data',
    'phone_numbers_by_waba',
    'completed_phone_number_waba_ids',
    'waba_id',
    'phone_number_id',
])]
#[Hidden(['authorization_code', 'access_token'])]
class WhatsAppConnectionAttempt extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_connection_attempts';

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    protected function casts(): array
    {
        return [
            'authorization_code' => 'encrypted',
            'access_token' => 'encrypted',
            'debug_token_data' => 'array',
            'phone_numbers_by_waba' => 'array',
            'completed_phone_number_waba_ids' => 'array',
        ];
    }
}
