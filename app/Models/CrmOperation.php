<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\CrmOperationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $integration_id
 * @property int $contact_id
 * @property string|null $agent_id
 * @property string $origin
 * @property string $type
 * @property CrmOperationStatus $status
 * @property int $attempts
 * @property int $token_refresh_attempts
 * @property array<string, mixed> $payload
 * @property array<string, mixed> $summary
 * @property string|null $failure_message
 * @property Carbon|null $completed_at
 * @property Carbon $expires_at
 */
#[Fillable([
    'team_id',
    'integration_id',
    'contact_id',
    'agent_id',
    'origin',
    'type',
    'status',
    'attempts',
    'token_refresh_attempts',
    'payload',
    'summary',
    'failure_message',
    'completed_at',
    'expires_at',
])]
class CrmOperation extends Model
{
    use BelongsToTeam;

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Agent, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CrmOperationStatus::class,
            'attempts' => 'integer',
            'token_refresh_attempts' => 'integer',
            'payload' => 'encrypted:array',
            'summary' => 'array',
            'completed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
