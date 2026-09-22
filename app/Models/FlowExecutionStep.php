<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\FlowExecutionStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $flow_execution_id
 * @property int $sequence
 * @property string $node_id
 * @property string $result
 * @property int $duration_ms
 * @property string|null $edge_id
 * @property string|null $edge_source_node_id
 * @property string|null $edge_target_node_id
 * @property string|null $edge_label
 * @property string|null $error
 * @property array<string, mixed> $variables_snapshot
 * @property array<string, mixed>|null $http_request
 * @property array<string, mixed>|null $http_response
 * @property int|null $http_status
 * @property int $retry_count
 * @property string|null $ai_prompt
 * @property string|null $ai_response
 * @property int|null $ai_input_tokens
 * @property int|null $ai_output_tokens
 * @property string|null $ai_cost_brl
 * @property array<int, array<string, mixed>>|null $ai_tool_calls
 * @property Carbon $started_at
 * @property-read Team $team
 * @property-read FlowExecution $execution
 */
#[Fillable([
    'team_id',
    'flow_execution_id',
    'sequence',
    'node_id',
    'result',
    'duration_ms',
    'edge_id',
    'edge_source_node_id',
    'edge_target_node_id',
    'edge_label',
    'error',
    'variables_snapshot',
    'http_request',
    'http_response',
    'http_status',
    'retry_count',
    'ai_prompt',
    'ai_response',
    'ai_input_tokens',
    'ai_output_tokens',
    'ai_cost_brl',
    'ai_tool_calls',
    'started_at',
])]
class FlowExecutionStep extends Model
{
    /** @use HasFactory<FlowExecutionStepFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return BelongsTo<FlowExecution, $this> */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(FlowExecution::class, 'flow_execution_id');
    }

    protected function casts(): array
    {
        return [
            'variables_snapshot' => 'array',
            'http_request' => 'array',
            'http_response' => 'array',
            'ai_tool_calls' => 'array',
            'ai_cost_brl' => 'decimal:4',
            'started_at' => 'immutable_datetime',
        ];
    }
}
