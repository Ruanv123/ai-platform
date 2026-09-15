<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property AgentObjective $objective
 * @property AgentTone $tone
 * @property bool $uses_emojis
 * @property AgentResponseLength $response_length
 * @property string $welcome_message
 * @property array<int, string> $instructions
 * @property array<int, string> $rules
 * @property array<int, string> $transfer_triggers
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Collection> $collections
 * @property-read WhatsAppNumber|null $whatsappNumber
 */
#[Fillable([
    'team_id',
    'name',
    'objective',
    'tone',
    'uses_emojis',
    'response_length',
    'welcome_message',
    'instructions',
    'rules',
    'transfer_triggers',
    'is_active',
])]

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the Empresa that owns the Agent.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the knowledge collections available to the Agent.
     *
     * @return BelongsToMany<Collection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'agent_collection');
    }

    /**
     * Get the WhatsApp number linked to this Agent, if any.
     *
     * @return HasOne<WhatsAppNumber, $this>
     */
    public function whatsappNumber(): HasOne
    {
        return $this->hasOne(WhatsAppNumber::class);
    }

    /**
     * Unlink this Agent from configured channels before deleting it.
     */
    protected static function booted(): void
    {
        static::deleting(function (Agent $agent): void {
            $agent->whatsappNumber()->update(['agent_id' => null]);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'objective' => AgentObjective::class,
            'tone' => AgentTone::class,
            'uses_emojis' => 'boolean',
            'response_length' => AgentResponseLength::class,
            'instructions' => 'array',
            'rules' => 'array',
            'transfer_triggers' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
