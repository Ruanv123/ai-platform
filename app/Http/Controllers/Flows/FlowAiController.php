<?php

namespace App\Http\Controllers\Flows;

use App\Flows\FlowAiService;
use App\Flows\FlowDslValidator;
use App\Flows\FlowValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Flows\ApplyFlowAiRequest;
use App\Http\Requests\Flows\FlowAiRequest;
use App\Models\Flow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class FlowAiController extends Controller
{
    public function propose(FlowAiRequest $request, Flow $flow, FlowAiService $service): JsonResponse
    {
        try {
            return response()->json($service->propose(
                $flow,
                $request->validated('message'),
                $request->validated('history', []),
            ));
        } catch (FlowValidationException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => $exception->errors], 422);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Não foi possível conversar com a IA agora. O desenho atual foi preservado.',
            ], 502);
        }
    }

    public function apply(ApplyFlowAiRequest $request, Flow $flow, FlowDslValidator $validator): JsonResponse
    {
        Gate::authorize('update', $flow);

        try {
            $dsl = $validator->normalize($request->validated('dsl'));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $flow->update(['draft_dsl' => $dsl]);

        return response()->json([
            'draft' => $dsl,
            'hasUnpublishedChanges' => $flow->fresh()->hasUnpublishedChanges(),
        ]);
    }
}
