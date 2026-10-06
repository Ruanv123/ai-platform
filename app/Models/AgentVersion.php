<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\AgentObjective;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['team_id', 'agent_id', 'number', 'objective', 'published_at'])]
class AgentVersion extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Uma Versão publicada do Agente é imutável.');
        });
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    protected function casts(): array
    {
        return [
            'objective' => AgentObjective::class,
            'published_at' => 'immutable_datetime',
        ];
    }
}
