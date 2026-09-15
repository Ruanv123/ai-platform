<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\WhatsAppConnectionMethod;
use App\Enums\WhatsAppConnectionStatus;
use Database\Factories\WhatsAppNumberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $agent_id
 * @property WhatsAppConnectionMethod $method
 * @property string $waba_id
 * @property string $phone_number_id
 * @property string $access_token
 * @property string $app_secret
 * @property string $verify_token
 * @property WhatsAppConnectionStatus $status
 * @property string|null $last_error
 * @property string|null $display_phone_number
 * @property string|null $verified_name
 * @property string|null $quality_rating
 * @property Carbon|null $last_tested_at
 * @property Carbon|null $webhook_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Agent|null $agent
 */
#[Fillable([
    'agent_id',
    'method',
    'waba_id',
    'phone_number_id',
    'access_token',
    'app_secret',
    'verify_token',
    'status',
    'last_error',
    'display_phone_number',
    'verified_name',
    'quality_rating',
    'last_tested_at',
    'webhook_verified_at',
])]
#[Hidden(['access_token', 'app_secret', 'verify_token'])]

class WhatsAppNumber extends Model
{
    /** @use HasFactory<WhatsAppNumberFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the Empresa that owns this WhatsApp number.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the Agent linked to this WhatsApp number.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
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
            'status' => WhatsAppConnectionStatus::class,
            'last_tested_at' => 'datetime',
            'webhook_verified_at' => 'datetime',
        ];
    }
}
