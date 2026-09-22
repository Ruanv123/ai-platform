<?php

namespace App\Http\Controllers\Flows;

use App\Enums\FlowCategory;
use App\Enums\FlowStatus;
use App\Enums\FlowTriggerType;
use App\Flows\FlowExecutionAnalytics;
use App\Flows\FlowExecutionLister;
use App\Flows\FlowPublisher;
use App\Flows\FlowValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Flows\SaveFlowRequest;
use App\Models\Channel;
use App\Models\Flow;
use App\Models\FlowEvent;
use App\Models\FlowVersion;
use App\Models\Integration;
use App\Models\Team;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'channel' => ['nullable', 'uuid'],
            'trigger' => ['nullable', 'in:message,webhook,manual'],
            'status' => ['nullable', 'in:draft,published,paused'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $perPage = (int) ($filters['per_page'] ?? 20);
        $flows = $team->flows()
            ->with(['channel', 'versions'])
            ->withCount('executions')
            ->withCount([
                'executions as executions_last_7_days_count' => fn ($query) => $query->where('flow_executions.created_at', '>=', now()->subDays(7)),
                'executions as completed_executions_count' => fn ($query) => $query->where('flow_executions.status', 'completed'),
            ])
            ->withMax('executions', 'created_at')
            ->when(
                filled($filters['search'] ?? null),
                fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($filters['search']).'%']),
            )
            ->when(isset($filters['channel']), fn ($query) => $query->where('channel_id', $filters['channel']))
            ->when(isset($filters['trigger']), fn ($query) => $query->where('trigger_type', $filters['trigger']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('flows/index', [
            'flows' => [
                'data' => $flows->getCollection()
                    ->map(fn (Flow $flow): array => $this->serialize($flow))
                    ->values(),
                'currentPage' => $flows->currentPage(),
                'lastPage' => $flows->lastPage(),
                'from' => $flows->firstItem(),
                'to' => $flows->lastItem(),
                'total' => $flows->total(),
                'perPage' => $flows->perPage(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'channel' => $filters['channel'] ?? null,
                'trigger' => $filters['trigger'] ?? null,
                'status' => $filters['status'] ?? null,
            ],
            'channels' => $team->channels()
                ->orderBy('type')
                ->get(['id', 'type'])
                ->map(fn (Channel $channel): array => [
                    'id' => $channel->getKey(),
                    'label' => $channel->type->label(),
                    'type' => $channel->type->value,
                ])
                ->values(),
            'triggerTypes' => $this->triggerTypes(),
            'statuses' => $this->statuses(),
            'templates' => $this->templates($team),
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
    }

    public function show(Request $request, Flow $flow, FlowExecutionAnalytics $analytics, FlowExecutionLister $lister): Response
    {
        Gate::authorize('view', $flow);
        $team = $request->user()->company();
        $filters = $request->validate([
            'tab' => ['nullable', 'in:design,executions'],
            'period' => ['nullable', 'in:24h,7d,30d'],
            'execution_status' => ['nullable', 'in:failed,stuck,all'],
            'error' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);
        $flow->load(['channel', 'versions']);

        return Inertia::render('flows/show', [
            'flow' => $this->serialize($flow),
            'activeTab' => $filters['tab'] ?? 'design',
            'observability' => $analytics->dashboard($flow, $filters['period'] ?? '7d'),
            'executionFilters' => [
                'status' => $filters['execution_status'] ?? 'failed',
                'error' => $filters['error'] ?? null,
            ],
            'executions' => $lister->list($flow, $filters),
            'channels' => $team->channels()
                ->orderBy('type')
                ->get(['id', 'type'])
                ->map(fn (Channel $channel): array => [
                    'id' => $channel->getKey(),
                    'label' => $channel->type->label(),
                    'type' => $channel->type->value,
                ])
                ->values(),
            'nodeCatalog' => config('flows.node_catalog'),
            'integrations' => $team->integrations()->orderBy('name')->get()->map(fn (Integration $integration): array => [
                'id' => $integration->getKey(),
                'name' => $integration->name,
                'type' => $integration->type,
                'status' => $integration->status,
                'statusLabel' => match ($integration->status) {
                    'connected' => 'Conectada',
                    'error' => 'Com erro',
                    default => 'Nunca testada',
                },
            ])->values(),
            'events' => $team->flowEvents()->orderBy('name')->get(['id', 'name', 'description'])->map(fn (FlowEvent $event): array => [
                'id' => $event->getKey(),
                'name' => $event->name,
                'description' => $event->description,
            ])->values(),
            'templates' => $this->templates($team),
            'whatsappTemplates' => $this->whatsappTemplates($team),
            'permissions' => $request->user()->toTeamPermissions($team),
        ]);
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

    public function cloneTemplate(Request $request, string $template): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Flow::class);
        $team = $request->user()->company();
        $templateData = null;
        foreach ($this->configuredTemplates() as $candidate) {
            if (($candidate['id'] ?? null) === $template) {
                $templateData = $candidate;
                break;
            }
        }
        abort_if($templateData === null, 404);

        $validated = $request->validate(['channel_id' => ['nullable', 'uuid']]);
        $channelId = $validated['channel_id'] ?? null;
        $this->assertChannelBelongsToTeam($channelId, $team->getKey());

        $flow = $team->flows()->create([
            'name' => $templateData['name'],
            'channel_id' => $channelId,
            'status' => FlowStatus::Draft,
            'draft_dsl' => $templateData['dsl'],
            'trigger_type' => FlowTriggerType::Message,
            'trigger_event' => null,
            'collision_policy' => 'ignore',
            'allow_unknown_contact' => false,
        ]);

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

    public function destroy(Flow $flow): RedirectResponse
    {
        Gate::authorize('delete', $flow);

        if ($flow->executions()->exists()) {
            throw ValidationException::withMessages([
                'flow' => 'Este Fluxo possui Execuções e não pode ser excluído porque seu histórico precisa ser preservado.',
            ]);
        }

        $flow->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Fluxo excluído com sucesso.',
        ]);

        return to_route('flows.index');
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
            'type' => count($result['warnings']) > 0 ? 'warning' : 'success',
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

    /** @return list<array{value: string, label: string}> */
    private function triggerTypes(): array
    {
        return [
            ['value' => FlowTriggerType::Message->value, 'label' => 'Mensagem recebida'],
            ['value' => FlowTriggerType::Webhook->value, 'label' => 'Webhook'],
            ['value' => FlowTriggerType::Manual->value, 'label' => 'Manual'],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function statuses(): array
    {
        return [
            ['value' => FlowStatus::Draft->value, 'label' => 'Rascunho'],
            ['value' => FlowStatus::Published->value, 'label' => 'Publicado'],
            ['value' => FlowStatus::Paused->value, 'label' => 'Pausado'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function templates(Team $team): array
    {
        $channelTypes = $team->channels()->pluck('type')->map(fn ($type): string => $type->value)->all();
        $templates = [];

        foreach ($this->configuredTemplates() as $template) {
            $requiredChannelTypes = is_array($template['required_channel_types'] ?? null)
                ? array_values(array_filter($template['required_channel_types'], 'is_string'))
                : [];
            $templates[] = [
                'id' => $template['id'] ?? null,
                'name' => $template['name'] ?? null,
                'description' => $template['description'] ?? null,
                'category' => $template['category'] ?? null,
                'requiredChannelTypes' => $requiredChannelTypes,
                'hasRequiredChannel' => count(array_intersect($requiredChannelTypes, $channelTypes)) > 0,
            ];
        }

        return $templates;
    }

    /** @return list<array<string, mixed>> */
    private function whatsappTemplates(Team $team): array
    {
        return WhatsAppTemplate::query()
            ->where('status', 'approved')
            ->whereHas('account.phoneNumber.channel', fn ($query) => $query->where('team_id', $team->getKey())->where('type', 'whatsapp'))
            ->with('account.phoneNumber')
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppTemplate $template): array => [
                'id' => $template->getKey(),
                'name' => $template->name,
                'language' => $template->language,
                'channelId' => $template->account->phoneNumber->channel_id,
                'category' => $template->assigned_category?->value ?? $template->requested_category->value,
                'body' => $template->body,
                'variables' => $template->variableDefinitions(),
            ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function configuredTemplates(): array
    {
        $configuredTemplates = config('flows.templates', []);
        if (! is_array($configuredTemplates)) {
            return [];
        }

        $templates = [];
        foreach ($configuredTemplates as $template) {
            if (is_array($template)) {
                $templates[] = $template;
            }
        }

        return $templates;
    }

    /** @return array<string, mixed> */
    private function serialize(Flow $flow): array
    {
        $publishedVersion = $flow->relationLoaded('versions')
            ? $flow->versions->sortByDesc('number')->first()
            : $flow->publishedVersion();
        $executionCount = (int) ($flow->executions_count ?? 0);
        $completedCount = (int) ($flow->completed_executions_count ?? 0);
        $lastExecutionAt = $flow->getAttribute('executions_max_created_at');

        return [
            'id' => $flow->getKey(),
            'name' => $flow->name,
            'status' => $flow->status->value,
            'statusLabel' => match ($flow->status) {
                FlowStatus::Draft => 'Rascunho',
                FlowStatus::Published => 'Publicado',
                FlowStatus::Paused => 'Pausado',
            },
            'draft' => $flow->draft_dsl,
            'publishedDsl' => $publishedVersion?->dsl,
            'hasUnpublishedChanges' => $flow->hasUnpublishedChanges(),
            'channelId' => $flow->channel_id,
            'channelLabel' => $flow->channel?->type->label(),
            'triggerType' => $flow->trigger_type->value,
            'triggerEvent' => $flow->trigger_event,
            'collisionPolicy' => $flow->collision_policy->value,
            'category' => $publishedVersion?->category->value,
            'categoryLabel' => $publishedVersion?->category === FlowCategory::Conversation ? 'Conversa' : ($publishedVersion?->category === FlowCategory::Reaction ? 'Reação' : null),
            'executionCount' => $executionCount,
            'executionsLast7Days' => (int) ($flow->executions_last_7_days_count ?? 0),
            'completionRate' => $executionCount > 0 ? round(($completedCount / $executionCount) * 100) : null,
            'lastExecutionAt' => is_string($lastExecutionAt)
                ? Carbon::parse($lastExecutionAt)->toIso8601String()
                : null,
            'versions' => $flow->relationLoaded('versions')
                ? $flow->versions->sortByDesc('number')->values()->map(fn (FlowVersion $version): array => [
                    'id' => $version->getKey(),
                    'number' => $version->number,
                    'category' => $version->category->value,
                    'publishedAt' => $version->published_at?->toIso8601String(),
                ])->all()
                : [],
        ];
    }
}
