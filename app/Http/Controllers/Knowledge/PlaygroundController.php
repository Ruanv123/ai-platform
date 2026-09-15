<?php

namespace App\Http\Controllers\Knowledge;

use App\Ai\Agents\PlaygroundAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\PlaygroundRequest;
use App\Knowledge\AgentInstructionTemplate;
use App\Knowledge\KnowledgeBaseSearch;
use App\Models\Agent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Streaming\Events\Error;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PlaygroundController extends Controller
{
    /**
     * Show the disposable Playground for a saved Agent.
     */
    public function show(Agent $agent): Response
    {
        Gate::authorize('view', $agent);
        $agent->loadMissing(['team', 'collections']);

        return Inertia::render('agents/playground', [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'objectiveLabel' => $agent->objective->label(),
                'welcomeMessage' => $agent->welcome_message,
                'collectionCount' => $agent->collections->count(),
                'isActive' => $agent->is_active,
            ],
        ]);
    }

    /**
     * Stream one saved-Agent response without persisting the conversation.
     */
    public function stream(
        PlaygroundRequest $request,
        Agent $agent,
        AgentInstructionTemplate $instructionTemplate,
        KnowledgeBaseSearch $knowledgeBaseSearch,
    ): StreamedResponse {
        $agent->loadMissing(['team', 'collections']);
        $validated = $request->validated();

        return response()->stream(function () use (
            $agent,
            $validated,
            $instructionTemplate,
            $knowledgeBaseSearch,
        ): void {
            $invocationId = (string) Str::uuid7();

            try {
                $playgroundAgent = new PlaygroundAgent(
                    $agent,
                    $validated['history'] ?? [],
                    $instructionTemplate,
                    $knowledgeBaseSearch,
                );
                $stream = $playgroundAgent->stream(
                    $validated['message'],
                    provider: config('ai.playground.provider'),
                    model: config('ai.playground.model'),
                    timeout: (int) config('ai.playground.timeout', 60),
                );
                $invocationId = $stream->invocationId;

                foreach ($stream as $event) {
                    $this->writeEvent((string) $event);
                }

                $this->writeEvent('[DONE]');
            } catch (Throwable $exception) {
                report($exception);

                $this->writeEvent((string) (new Error(
                    $invocationId,
                    'error',
                    'Não foi possível obter uma resposta agora. Tente novamente em instantes.',
                    true,
                    time(),
                ))->withInvocationId($invocationId));
                $this->writeEvent('[DONE]');
            }
        }, headers: [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Write one event using the Laravel AI Server-Sent Events protocol.
     */
    private function writeEvent(string $event): void
    {
        echo "data: {$event}\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
