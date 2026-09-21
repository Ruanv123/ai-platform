<?php

namespace App\Http\Controllers\Flows;

use App\Flows\FlowDslValidator;
use App\Http\Controllers\Controller;
use App\Models\Flow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FlowPlaygroundController extends Controller
{
    public function run(Request $request, Flow $flow, FlowDslValidator $validator): JsonResponse
    {
        Gate::authorize('view', $flow);

        try {
            $dsl = $validator->normalize($flow->draft_dsl);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['dsl' => $exception->getMessage()]);
        }

        $trace = collect($dsl['nodes'])
            ->map(fn (array $node): array => [
                'nodeId' => $node['id'],
                'type' => $node['type'],
                'result' => 'simulated',
            ])
            ->values()
            ->all();

        return response()->json([
            'trace' => $trace,
            'variables' => $request->input('variables', []),
            'message' => $request->input('message'),
        ]);
    }
}
