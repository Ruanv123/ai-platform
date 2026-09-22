<?php

namespace App\Http\Controllers\Flows;

use App\Flows\FlowDslValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Flows\PlaygroundFlowRequest;
use App\Models\Flow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FlowPlaygroundController extends Controller
{
    public function run(PlaygroundFlowRequest $request, Flow $flow, FlowDslValidator $validator): JsonResponse
    {
        try {
            $dsl = $validator->normalize($flow->draft_dsl);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['dsl' => $exception->getMessage()]);
        }

        $message = $request->validated('message');
        $payload = $request->validated('payload', []);
        $traceNodes = $this->traceNodes($dsl);
        $trace = collect($traceNodes)
            ->map(fn (array $node): array => [
                'nodeId' => $node['id'],
                'type' => $node['type'],
                'result' => $node['type'] === 'trigger' ? 'manual_trigger' : 'simulated',
                'status' => 'completed',
            ])
            ->values()
            ->all();
        $outputs = collect($traceNodes)
            ->filter(fn (array $node): bool => $node['type'] === 'message')
            ->map(fn (array $node): string => $this->interpolate(
                (string) data_get($node, 'config.text', data_get($node, 'config.message', '')),
                $payload,
                $request->validated('variables', []),
            ))
            ->filter()
            ->values()
            ->all();
        $inputTokens = $this->tokens(is_string($message) ? $message : (string) json_encode($payload));
        $outputTokens = $this->tokens(implode(' ', $outputs));
        $totalTokens = $inputTokens + $outputTokens;

        return response()->json([
            'isPlayground' => true,
            'trace' => $trace,
            'variables' => $request->validated('variables', []),
            'message' => $message,
            'payload' => $payload,
            'outputs' => $outputs,
            'tokens' => [
                'input' => $inputTokens,
                'output' => $outputTokens,
                'total' => $totalTokens,
            ],
            'costInReais' => round($totalTokens * 0.00002, 4),
            'executionId' => null,
            'note' => 'Este turno foi simulado no rascunho e não aparece nas métricas do Fluxo.',
        ]);
    }

    /**
     * @param  array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}  $dsl
     * @return list<array<string, mixed>>
     */
    private function traceNodes(array $dsl): array
    {
        $nodes = collect($dsl['nodes'])->keyBy('id');
        $outgoing = collect($dsl['edges'])->groupBy('source');
        $incoming = collect($dsl['edges'])->countBy('target');
        $current = $nodes->first(fn (array $node): bool => ! $incoming->has($node['id']));
        $trace = [];
        $visited = [];

        while (is_array($current) && ! isset($visited[$current['id']])) {
            $visited[$current['id']] = true;
            $trace[] = $current;
            $next = $outgoing->get($current['id'])?->first();
            $nextId = is_array($next) ? ($next['target'] ?? null) : null;
            $current = is_string($nextId) ? $nodes->get($nextId) : null;
        }

        return $trace;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $variables
     */
    private function interpolate(string $value, array $payload, array $variables): string
    {
        $interpolated = preg_replace_callback('/{{\\s*([a-zA-Z0-9_.]+)\\s*}}/', function (array $matches) use ($payload, $variables): string {
            $path = $matches[1];
            $replacement = str_starts_with($path, 'payload.')
                ? data_get($payload, str_replace('payload.', '', $path))
                : data_get($variables, $path);

            return is_scalar($replacement) ? (string) $replacement : $matches[0];
        }, $value);

        return is_string($interpolated) ? $interpolated : $value;
    }

    private function tokens(?string $value): int
    {
        return $value === null || $value === '' ? 0 : max(1, (int) ceil(Str::length($value) / 4));
    }
}
