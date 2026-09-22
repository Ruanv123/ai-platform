<?php

namespace App\Http\Controllers\Templates;

use App\Enums\ChannelType;
use App\Enums\TeamPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\SaveWhatsAppTemplateRequest;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\WhatsAppTemplateService;
use App\WhatsApp\WhatsAppTemplateSynchronizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class WhatsAppTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->company();
        abort_if($team === null, 404);
        $accounts = $this->accounts($team->getKey());
        abort_if($accounts->isEmpty(), 404);

        $filters = $request->validate([
            'status' => ['nullable', 'in:draft,pending,approved,rejected,paused,disabled,deleted'],
            'category' => ['nullable', 'in:marketing,utility,authentication'],
            'search' => ['nullable', 'string', 'max:255'],
            'account' => ['nullable', 'integer'],
        ]);
        $templates = WhatsAppTemplate::query()
            ->whereIn('whatsapp_account_id', $accounts->modelKeys())
            ->with('account.phoneNumber')
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['category']), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('requested_category', $filters['category'])
                    ->orWhere('assigned_category', $filters['category']);
            }))
            ->when(isset($filters['account']), fn ($query) => $query->where('whatsapp_account_id', $filters['account']))
            ->when(filled($filters['search'] ?? null), fn ($query) => $query->where('name', 'like', '%'.$filters['search'].'%'))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('templates/index', [
            'templates' => $this->paginator($templates, $accounts->count() > 1),
            'accounts' => $accounts->map(fn (WhatsAppAccount $account): array => [
                'id' => $account->getKey(),
                'label' => $this->accountLabel($account),
            ])->values(),
            'filters' => [
                'status' => $filters['status'] ?? null,
                'category' => $filters['category'] ?? null,
                'search' => $filters['search'] ?? '',
                'account' => $filters['account'] ?? null,
            ],
            'multipleAccounts' => $accounts->count() > 1,
            'permissions' => $this->permissions($request),
        ]);
    }

    public function show(Request $request, WhatsAppTemplate $template): Response
    {
        $this->authorizeTemplate($request, $template);
        $template->load(['account.phoneNumber', 'history']);

        return Inertia::render('templates/show', [
            'template' => $this->serialize($template, true),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function store(SaveWhatsAppTemplateRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $account = $this->accountForTeam($request, (int) $data['whatsapp_account_id']);
        $template = $account->templates()->create([
            ...$this->templateAttributes($data),
            'status' => 'draft',
            'assigned_category' => null,
            'meta_template_id' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json($this->serialize($template->load('account.phoneNumber')), 201);
        }

        return to_route('templates.show', $template);
    }

    public function update(SaveWhatsAppTemplateRequest $request, WhatsAppTemplate $template): RedirectResponse|JsonResponse
    {
        $this->authorizeTemplate($request, $template);
        $data = $request->validated();
        $account = $this->accountForTeam($request, (int) $data['whatsapp_account_id']);
        abort_unless($account->getKey() === $template->whatsapp_account_id, 422, 'A Conta do Template não pode ser alterada.');
        $attributes = $this->templateAttributes($data);
        if (! array_key_exists('buttons', $data)) {
            unset($attributes['buttons']);
        }
        if (! array_key_exists('variables', $data)) {
            unset($attributes['variables']);
        }
        if (! array_key_exists('header_type', $data)) {
            unset($attributes['header_type']);
        }
        $template->update($attributes);

        if ($request->expectsJson()) {
            return response()->json($this->serialize($template->fresh(['account.phoneNumber'])));
        }

        return to_route('templates.show', $template);
    }

    public function submit(Request $request, WhatsAppTemplate $template, WhatsAppTemplateService $service): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request);
        $this->authorizeTemplate($request, $template);

        try {
            $template = $service->submit($template);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'template' => $this->submitError($exception),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json($this->serialize($template->load('account.phoneNumber')));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Template enviado para aprovação da Meta.',
        ]);

        return to_route('templates.show', $template);
    }

    public function sync(Request $request, WhatsAppTemplateSynchronizer $synchronizer): RedirectResponse|JsonResponse
    {
        $this->authorizeManagement($request);
        $accounts = $this->accounts($request->user()->company()->getKey());
        $result = ['imported' => 0, 'removed' => 0, 'templates' => 0];

        try {
            foreach ($accounts as $account) {
                $synced = $synchronizer->sync($account);
                $result = [
                    'imported' => $result['imported'] + $synced['imported'],
                    'removed' => $result['removed'] + $synced['removed'],
                    'templates' => $result['templates'] + $synced['templates'],
                ];
            }
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'sync' => 'Não foi possível sincronizar os Templates com a Meta agora. Tente novamente.',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['removed'] > 0
                ? 'Templates sincronizados. Alguns foram marcados como removidos na Meta.'
                : 'Templates sincronizados com a Meta.',
        ]);

        return to_route('templates.index');
    }

    /** @return Collection<int, WhatsAppAccount> */
    private function accounts(int $teamId): Collection
    {
        return WhatsAppAccount::query()
            ->whereHas('phoneNumber.channel', fn ($query) => $query
                ->where('team_id', $teamId)
                ->where('type', ChannelType::WhatsApp))
            ->with('phoneNumber')
            ->get();
    }

    private function accountForTeam(Request $request, int $accountId): WhatsAppAccount
    {
        $account = $this->accounts($request->user()->company()->getKey())
            ->firstWhere('id', $accountId);
        abort_if($account === null, 404);

        return $account;
    }

    private function authorizeTemplate(Request $request, WhatsAppTemplate $template): void
    {
        $teamId = $request->user()->company()?->getKey();
        abort_unless($teamId !== null && $template->account()
            ->whereHas('phoneNumber.channel', fn ($query) => $query->where('team_id', $teamId)->where('type', ChannelType::WhatsApp))
            ->exists(), 404);
    }

    private function authorizeManagement(Request $request): void
    {
        $team = $request->user()->company();
        abort_unless($team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageChannels), 403);
    }

    /** @return array<string, bool> */
    private function permissions(Request $request): array
    {
        $team = $request->user()->company();

        return ['canManage' => $team !== null && $request->user()->hasTeamPermission($team, TeamPermission::ManageChannels)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function templateAttributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'language' => $data['language'],
            'requested_category' => $data['requested_category'],
            'body' => $data['body'],
            'header_type' => $data['header_type'] ?? null,
            'header_text' => $data['header_text'] ?? null,
            'footer' => $data['footer'] ?? null,
            'buttons' => $data['buttons'] ?? [],
            'variables' => $data['variables'] ?? $this->variables($data['body']),
        ];
    }

    /** @return list<array{position: int, component: string}> */
    private function variables(string $body): array
    {
        preg_match_all('/{{\s*(\d+)\s*}}/', $body, $matches);

        return array_values(array_map(
            fn (string $position): array => ['position' => (int) $position, 'component' => 'body'],
            array_unique($matches[1]),
        ));
    }

    /** @return array<string, mixed> */
    private function serialize(WhatsAppTemplate $template, bool $withHistory = false): array
    {
        $data = [
            'id' => $template->getKey(),
            'name' => $template->name,
            'language' => $template->language,
            'status' => $template->status->value,
            'statusLabel' => $template->status->label(),
            'requestedCategory' => $template->requested_category->value,
            'requestedCategoryLabel' => $template->requested_category->label(),
            'assignedCategory' => $template->assigned_category?->value,
            'assignedCategoryLabel' => $template->assigned_category?->label(),
            'body' => $template->body,
            'headerType' => $template->header_type,
            'headerText' => $template->header_text,
            'footer' => $template->footer,
            'buttons' => $template->buttons ?? [],
            'variables' => $template->variableDefinitions(),
            'rejectionReason' => $template->rejection_reason,
            'rejectionExplanation' => $template->rejection_explanation,
            'accountId' => $template->whatsapp_account_id,
            'accountLabel' => $this->accountLabel($template->account),
            'lastSyncedAt' => $template->last_synced_at === null ? null : $template->last_synced_at->toIso8601String(),
            'submittedAt' => $template->submitted_at?->toIso8601String(),
        ];

        if ($withHistory) {
            $data['history'] = $template->history->map(fn ($history): array => [
                'id' => $history->getKey(),
                'event' => $history->event,
                'oldStatus' => $history->old_status,
                'newStatus' => $history->new_status,
                'oldCategory' => $history->old_category,
                'newCategory' => $history->new_category,
                'reason' => $history->reason,
                'explanation' => $history->explanation,
                'createdAt' => $history->created_at->toIso8601String(),
            ])->values();
        }

        return $data;
    }

    /**
     * @param  LengthAwarePaginator<int, WhatsAppTemplate>  $templates
     * @return array<string, mixed>
     */
    private function paginator(LengthAwarePaginator $templates, bool $multipleAccounts): array
    {
        return [
            'data' => $templates->getCollection()->map(fn (WhatsAppTemplate $template): array => [
                ...$this->serialize($template),
                'showAccount' => $multipleAccounts,
            ])->values(),
            'currentPage' => $templates->currentPage(),
            'lastPage' => $templates->lastPage(),
            'total' => $templates->total(),
        ];
    }

    private function accountLabel(WhatsAppAccount $account): string
    {
        $phone = $account->phoneNumber;

        return collect([$phone->verified_name, $phone->display_phone_number])
            ->filter(fn (?string $value): bool => filled($value))
            ->join(' · ') ?: 'Conta do WhatsApp';
    }

    private function submitError(Throwable $exception): string
    {
        return Str::contains($exception->getMessage(), ['Template', 'Meta', 'variáveis', 'nome'])
            ? $exception->getMessage()
            : 'A Meta não aceitou o envio agora. O rascunho foi preservado para tentar novamente.';
    }
}
