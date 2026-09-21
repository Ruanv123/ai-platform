<?php

namespace App\Http\Controllers\Flows;

use App\Enums\FlowStatus;
use App\Enums\FlowTriggerType;
use App\Flows\FlowPublisher;
use App\Flows\FlowValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Flows\SaveFlowRequest;
use App\Models\Channel;
use App\Models\Flow;
use App\Models\FlowVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FlowController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Flow::class);
        $team = $request->user()->company();

        return Inertia::render('flows/index', [
            'flows' => $team->flows()->with(['channel', 'versions'])->latest()->get()->map(fn (Flow $flow): array => $this->serialize($flow)),
        ]);
    }

    public function show(Request $request, Flow $flow): Response
    {
        Gate::authorize('view', $flow);

        return Inertia::render('flows/show', ['flow' => $this->serialize($flow->load(['channel', 'versions']))]);
    }

    public function store(SaveFlowRequest $request): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Flow::class);
        $team = $request->user()->company();
        $data = $request->validated();
        $this->assertChannelBelongsToTeam($data['channel_id'] ?? null, $team->getKey());
        $flow = $team->flows()->create($this->attributes($data, $team->getKey()));

        if ($request->expectsJson()) {
            return response()->json($this->serialize($flow), 201);
        }

        return to_route('flows.show', $flow);
    }

    public function update(SaveFlowRequest $request, Flow $flow): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $flow);
        $team = $request->user()->company();
        $data = $request->validated();
        $this->assertChannelBelongsToTeam($data['channel_id'] ?? $flow->channel_id, $team->getKey());
        $flow->update($this->attributes($data, $team->getKey(), partial: true));
        $flow->refresh();

        if ($request->expectsJson()) {
            return response()->json($this->serialize($flow));
        }

        return to_route('flows.show', $flow);
    }

    public function publish(Request $request, Flow $flow, FlowPublisher $publisher): RedirectResponse|JsonResponse
    {
        Gate::authorize('publish', $flow);

        try {
            $result = $publisher->publish($flow);
        } catch (FlowValidationException $exception) {
            throw ValidationException::withMessages(['dsl' => $exception->errors]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'flow' => $this->serialize($flow->fresh(['versions'])),
                'version' => $result['version'],
                'warnings' => $result['warnings'],
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Fluxo publicado com sucesso.'.(count($result['warnings']) > 0 ? ' '.$result['warnings'][0] : ''),
        ]);

        return to_route('flows.show', $flow);
    }

    public function rollback(Request $request, Flow $flow, FlowVersion $version): RedirectResponse|JsonResponse
    {
        Gate::authorize('publish', $flow);
        abort_unless($version->flow_id === $flow->getKey() && $version->team_id === $flow->team_id, 404);

        $newVersion = DB::transaction(function () use ($flow, $version): FlowVersion {
            $number = ((int) $flow->versions()->max('number')) + 1;
            $newVersion = $flow->versions()->create([
                'team_id' => $flow->team_id,
                'number' => $number,
                'dsl' => $version->dsl,
                'category' => $version->category,
                'published_at' => now(),
            ]);
            $flow->update(['status' => FlowStatus::Published, 'draft_dsl' => $version->dsl]);

            return $newVersion;
        });

        if ($request->expectsJson()) {
            return response()->json(['version' => $newVersion, 'flow' => $this->serialize($flow->fresh(['versions']))]);
        }

        return to_route('flows.show', $flow);
    }

    public function pause(Request $request, Flow $flow): RedirectResponse|JsonResponse
    {
        Gate::authorize('pause', $flow);
        if ($flow->versions()->doesntExist()) {
            throw ValidationException::withMessages(['status' => 'O Fluxo precisa ser publicado antes de ser pausado.']);
        }

        $flow->update(['status' => FlowStatus::Paused]);

        if ($request->expectsJson()) {
            return response()->json($this->serialize($flow->fresh()));
        }

        return to_route('flows.show', $flow);
    }

    public function resume(Request $request, Flow $flow): RedirectResponse|JsonResponse
    {
        Gate::authorize('publish', $flow);
        if ($flow->versions()->doesntExist()) {
            throw ValidationException::withMessages(['status' => 'O Fluxo precisa ser publicado antes de ser ativado.']);
        }

        $flow->update(['status' => FlowStatus::Published]);

        if ($request->expectsJson()) {
            return response()->json($this->serialize($flow->fresh()));
        }

        return to_route('flows.show', $flow);
    }

    private function assertChannelBelongsToTeam(?string $channelId, int $teamId): void
    {
        if ($channelId !== null && ! Channel::withoutGlobalScopes()->where('team_id', $teamId)->whereKey($channelId)->exists()) {
            throw ValidationException::withMessages(['channel_id' => 'O Canal não pertence à Empresa.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, int $teamId, bool $partial = false): array
    {
        $attributes = [];
        foreach (['name', 'channel_id', 'trigger_type', 'trigger_event', 'collision_policy', 'allow_unknown_contact'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = $data[$key];
            }
        }
        if (array_key_exists('dsl', $data)) {
            $attributes['draft_dsl'] = $data['dsl'];
        } elseif (! $partial) {
            $attributes['draft_dsl'] = ['version' => 1, 'nodes' => [], 'edges' => []];
        }
        if (array_key_exists('trigger_token', $data)) {
            $attributes['trigger_token_hash'] = filled($data['trigger_token']) ? hash('sha256', (string) $data['trigger_token']) : null;
        }
        $attributes['team_id'] = $teamId;

        if (! $partial) {
            $attributes['status'] = FlowStatus::Draft;
            $attributes['trigger_type'] ??= FlowTriggerType::Message;
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function serialize(Flow $flow): array
    {
        return [
            'id' => $flow->getKey(),
            'name' => $flow->name,
            'status' => $flow->status->value,
            'draft' => $flow->draft_dsl,
            'hasUnpublishedChanges' => $flow->hasUnpublishedChanges(),
            'channelId' => $flow->channel_id,
            'triggerType' => $flow->trigger_type->value,
            'triggerEvent' => $flow->trigger_event,
            'collisionPolicy' => $flow->collision_policy->value,
            'versions' => $flow->relationLoaded('versions')
                ? $flow->versions->map(fn ($version): array => [
                    'id' => $version->getKey(),
                    'number' => $version->number,
                    'category' => $version->category->value,
                    'publishedAt' => $version->published_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }
}
