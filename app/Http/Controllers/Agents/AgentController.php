<?php

namespace App\Http\Controllers\Agents;

use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTemplateUsage;
use App\Enums\AgentTone;
use App\Enums\WhatsAppTemplateFormat;
use App\Enums\WhatsAppTemplateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\CreateAgentRequest;
use App\Http\Requests\Agents\SaveAgentRequest;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Collection;
use App\Models\SchedulableCalendar;
use App\Models\Team;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    /**
     * Display the current Empresa's Agents.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user?->company();

        abort_if($team === null, 404);
        Gate::authorize('viewAny', Agent::class);

        return Inertia::render('agents/index', [
            'agents' => $team->agents()
                ->withCount(['collections', 'channels'])
                ->with(['collections', 'schedulableCalendars', 'channels' => fn ($query) => $query->orderBy('id')])
                ->orderBy('name')
                ->get()
                ->map(fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'objective' => $agent->objective->value,
                    'objectiveLabel' => $agent->objective->label(),
                    'tone' => $agent->tone->value,
                    'toneLabel' => $agent->tone->label(),
                    'usesEmojis' => $agent->uses_emojis,
                    'responseLength' => $agent->response_length->value,
                    'responseLengthLabel' => $agent->response_length->label(),
                    'welcomeMessage' => $agent->welcome_message,
                    'instructions' => $agent->instructions,
                    'rules' => $agent->rules,
                    'isActive' => $agent->is_active,
                    'collectionIds' => $agent->collections->modelKeys(),
                    'schedulableCalendarIds' => $agent->schedulableCalendars->modelKeys(),
                    'collectionCount' => $agent->collections_count,
                    'channelCount' => $agent->channels_count,
                    'channels' => $agent->channels
                        ->map(fn (Channel $channel): array => [
                            'id' => $channel->id,
                            'type' => $channel->type->value,
                            'typeLabel' => $channel->type->label(),
                            'status' => $channel->status->value,
                            'statusLabel' => $channel->status->label(),
                        ])
                        ->values(),
                ])
                ->values(),
            'collections' => $team->collections()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Collection $collection): array => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                ])
                ->values(),
            'schedulableCalendars' => $team->schedulableCalendars()
                ->whereHas('integration', fn ($query) => $query->where('type', 'agenda'))
                ->orderBy('display_name')
                ->get()
                ->map(fn (SchedulableCalendar $calendar): array => [
                    'id' => $calendar->id,
                    'name' => $calendar->display_name,
                    'description' => $calendar->display_description,
                ])
                ->values(),
            'agendaConnected' => $team->integrations()->where('type', 'agenda')->where('status', 'connected')->exists(),
            'permissions' => $user->toTeamPermissions($team),
            'objectives' => AgentObjective::options(),
            'tones' => AgentTone::options(),
            'responseLengths' => AgentResponseLength::options(),
        ]);
    }

    /**
     * Show the editor for an existing Agent.
     */
    public function edit(Request $request, Agent $agent): Response
    {
        Gate::authorize('view', $agent);

        $agent->loadMissing(['collections', 'schedulableCalendars', 'whatsappTemplates']);

        return Inertia::render('agents/edit', $this->editorProps(
            $agent->team,
            $agent,
            Gate::allows('update', $agent),
            $request->query('panel') === 'playground' ? 'playground' : 'config',
        ));
    }

    /**
     * Quickly create a new Agent from a name and description.
     *
     * The remaining configuration is filled in afterwards on the editor.
     */
    public function store(CreateAgentRequest $request): RedirectResponse
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);

        $agent = $team->agents()->create([
            ...$request->validated(),
            'objective' => AgentObjective::Support,
            'tone' => AgentTone::Friendly,
            'uses_emojis' => false,
            'response_length' => AgentResponseLength::Short,
            'welcome_message' => 'Olá! Como posso ajudar você hoje?',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Agente criado com sucesso.',
        ]);

        return to_route('agents.edit', $agent);
    }

    /**
     * Update an Agent.
     */
    public function update(SaveAgentRequest $request, Agent $agent): RedirectResponse
    {
        $this->saveDraft($agent, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Agente atualizado com sucesso.',
        ]);

        return to_route('agents.edit', $agent);
    }

    public function publish(SaveAgentRequest $request, Agent $agent): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($agent, $validated): void {
            $lockedAgent = $agent->newQuery()
                ->lockForUpdate()
                ->whereKey($agent->getKey())
                ->firstOrFail();

            $this->saveDraft($lockedAgent, $validated);
            $number = ((int) $lockedAgent->versions()->max('number')) + 1;

            $lockedAgent->versions()->create([
                'team_id' => $lockedAgent->team_id,
                'number' => $number,
                'objective' => $lockedAgent->objective,
                'published_at' => now(),
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Versão do Agente publicada para os Canais.',
        ]);

        return to_route('agents.edit', $agent);
    }

    /**
     * Toggle an Agent's active status.
     */
    public function toggle(Agent $agent): RedirectResponse
    {
        Gate::authorize('update', $agent);

        $agent->update(['is_active' => ! $agent->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $agent->is_active
                ? 'Agente ativado com sucesso.'
                : 'Agente desativado com sucesso.',
        ]);

        return to_route('agents.index');
    }

    /**
     * Delete an Agent.
     */
    public function destroy(Agent $agent): RedirectResponse
    {
        Gate::authorize('delete', $agent);

        $agent->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Agente excluído com sucesso.',
        ]);

        return to_route('agents.index');
    }

    /**
     * Get the props shared by the editor screen.
     *
     * @return array<string, mixed>
     */
    private function editorProps(
        Team $team,
        Agent $agent,
        bool $canManageAgents = false,
        string $initialPanel = 'config',
    ): array {
        $publishedVersion = $agent->publishedVersion();

        return [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'description' => $agent->description,
                'objective' => $agent->objective->value,
                'tone' => $agent->tone->value,
                'usesEmojis' => $agent->uses_emojis,
                'responseLength' => $agent->response_length->value,
                'welcomeMessage' => $agent->welcome_message,
                'instructions' => $agent->instructions,
                'rules' => $agent->rules,
                'collectionIds' => $agent->collections->modelKeys(),
                'schedulableCalendarIds' => $agent->schedulableCalendars->modelKeys(),
                'templateLinks' => $agent->whatsappTemplates
                    ->map(fn (WhatsAppTemplate $template): array => [
                        'templateId' => $template->getKey(),
                        'usage' => $template->pivot->usage,
                    ])
                    ->values()
                    ->all(),
                'reminderLeadHours' => $agent->reminder_lead_hours ?? 24,
                'followUpAfterHours' => $agent->follow_up_after_hours ?? 24,
            ],
            'whatsappTemplates' => $this->templateOptions($team, $agent),
            'templateUsages' => collect(AgentTemplateUsage::cases())
                ->map(fn (AgentTemplateUsage $usage): array => [
                    'value' => $usage->value,
                    'label' => $usage->label(),
                    'automatic' => $usage->isAutomatic(),
                ])
                ->values()
                ->all(),
            'initialPanel' => $initialPanel,
            'collections' => $team->collections()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Collection $collection): array => [
                    'id' => $collection->id,
                    'name' => $collection->name,
                ])
                ->values(),
            'schedulableCalendars' => $team->schedulableCalendars()
                ->whereHas('integration', fn ($query) => $query->where('type', 'agenda'))
                ->orderBy('display_name')
                ->get()
                ->map(fn (SchedulableCalendar $calendar): array => [
                    'id' => $calendar->id,
                    'name' => $calendar->display_name,
                    'description' => $calendar->display_description,
                ])
                ->values(),
            'agendaConnected' => $team->integrations()->where('type', 'agenda')->where('status', 'connected')->exists(),
            'publishedVersion' => $publishedVersion === null ? null : [
                'number' => $publishedVersion->number,
            ],
            'objectives' => AgentObjective::options(),
            'tones' => AgentTone::options(),
            'responseLengths' => AgentResponseLength::options(),
            'permissions' => [
                'canManageAgents' => $canManageAgents,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function templateOptions(Team $team, Agent $agent): array
    {
        $linkedIds = $agent->relationLoaded('whatsappTemplates') ? $agent->whatsappTemplates->modelKeys() : [];

        return WhatsAppTemplate::query()
            ->whereHas('account', fn ($query) => $query->where('team_id', $team->getKey()))
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('status', WhatsAppTemplateStatus::Approved)
                    ->where('format', WhatsAppTemplateFormat::Standard))
                ->orWhereIn('id', $linkedIds))
            ->with(['account.phoneNumbers', 'components.variables'])
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppTemplate $template): array => [
                'id' => $template->getKey(),
                'name' => $template->name,
                'language' => $template->language,
                'body' => $template->bodyText(),
                'categoryLabel' => ($template->assigned_category ?? $template->requested_category)->label(),
                'accountId' => $template->whatsapp_account_id,
                'accountLabel' => collect([
                    $template->account->phoneNumbers->first()?->verified_name,
                    $template->account->phoneNumbers->first()?->display_phone_number,
                ])->filter()->join(' · ') ?: 'Conta do WhatsApp',
                'statusLabel' => $template->status->label(),
                'usable' => $template->status === WhatsAppTemplateStatus::Approved
                    && $template->format === WhatsAppTemplateFormat::Standard,
                'variablesWithoutDescription' => collect($template->variableDefinitions())
                    ->filter(fn (array $variable): bool => in_array($variable['component'], ['header', 'body'], true)
                        && blank($variable['description'] ?? null))
                    ->count(),
            ])
            ->values()
            ->all();
    }

    private function saveDraft(Agent $agent, array $validated): void
    {
        $collectionIds = $validated['collection_ids'] ?? [];
        $calendarIds = $validated['schedulable_calendar_ids'] ?? [];
        $templatesConfigured = (bool) ($validated['templates_configured'] ?? false);
        $templateLinks = collect($validated['template_links'] ?? [])
            ->mapWithKeys(fn (array $link): array => [(int) $link['template_id'] => ['usage' => $link['usage']]])
            ->all();
        unset(
            $validated['collection_ids'],
            $validated['schedulable_calendar_ids'],
            $validated['templates_configured'],
            $validated['template_links'],
        );
        $validated = array_filter(
            $validated,
            fn (mixed $value, string $key): bool => $value !== null || ! in_array($key, ['reminder_lead_hours', 'follow_up_after_hours'], true),
            ARRAY_FILTER_USE_BOTH,
        );

        $agent->update($validated);
        $agent->collections()->sync($collectionIds);
        $agent->schedulableCalendars()->sync($calendarIds);
        if ($templatesConfigured) {
            $agent->whatsappTemplates()->sync($templateLinks);
        }
    }
}
