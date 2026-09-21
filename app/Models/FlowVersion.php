<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\FlowCategory;
use Database\Factories\FlowVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $team_id
 * @property string $flow_id
 * @property int $number
 * @property array<string, mixed> $dsl
 * @property FlowCategory $category
 * @property Carbon|null $published_at
 * @property-read Flow $flow
 */
#[Fillable(['team_id', 'flow_id', 'number', 'dsl', 'category', 'published_at'])]
class FlowVersion extends Model
{
    /** @use HasFactory<FlowVersionFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Uma Versão de Fluxo publicada é imutável.');
        });
    }

    /** @return BelongsTo<Flow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /** @return HasMany<FlowExecution, $this> */
    public function executions(): HasMany
    {
        return $this->hasMany(FlowExecution::class);
    }

    protected function casts(): array
    {
        return [
            'dsl' => 'array',
            'category' => FlowCategory::class,
            'published_at' => 'immutable_datetime',
        ];
    }
}
