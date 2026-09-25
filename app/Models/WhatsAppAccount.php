<?php

namespace App\Models;

use App\Enums\WhatsAppConnectionMethod;
use Database\Factories\WhatsAppAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $whatsapp_phone_number_id
 * @property WhatsAppConnectionMethod $method
 * @property string $waba_id
 * @property string|null $app_id
 * @property string $access_token
 * @property string $app_secret
 * @property string $verify_token
 * @property Carbon|null $webhook_verified_at
 * @property Carbon|null $last_tested_at
 * @property-read WhatsAppPhoneNumber $phoneNumber
 * @property-read Collection<int, WhatsAppTemplate> $templates
 */
#[Fillable([
    'whatsapp_phone_number_id',
    'method',
    'waba_id',
    'app_id',
    'access_token',
    'app_secret',
    'verify_token',
    'webhook_verified_at',
    'last_tested_at',
])]
#[Hidden(['access_token', 'app_secret', 'verify_token'])]

class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    /** @use HasFactory<WhatsAppAccountFactory> */
    use HasFactory;

    /**
     * Get the WhatsApp number owned by this Meta account.
     *
     * @return BelongsTo<WhatsAppPhoneNumber, $this>
     */
    public function phoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppPhoneNumber::class, 'whatsapp_phone_number_id');
    }

    /** @return HasMany<WhatsAppTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => WhatsAppConnectionMethod::class,
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'webhook_verified_at' => 'datetime',
            'last_tested_at' => 'datetime',
        ];
    }
}
