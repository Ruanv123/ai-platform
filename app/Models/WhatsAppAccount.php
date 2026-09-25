<?php

namespace App\Models;

use App\Enums\WhatsAppConnectionMethod;
use Database\Factories\WhatsAppAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'team_id',
    'connection_method',
    'waba_id',
    'business_id',
    'name',
    'app_id',
    'access_token',
    'app_secret',
    'verify_token',
    'webhook_verified_at',
    'subscribed_at',
    'ban_state',
    'review_status',
    'last_tested_at',
])]
#[Hidden(['access_token', 'app_secret', 'verify_token'])]

class WhatsAppAccount extends Model
{
    protected $table = 'whatsapp_accounts';

    /** @use HasFactory<WhatsAppAccountFactory> */
    use HasFactory;

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(WhatsAppPhoneNumber::class, 'whatsapp_account_id');
    }

    /** @return HasMany<WhatsAppTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(WhatsAppTemplate::class, 'whatsapp_account_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connection_method' => WhatsAppConnectionMethod::class,
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'webhook_verified_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'last_tested_at' => 'datetime',
        ];
    }
}
