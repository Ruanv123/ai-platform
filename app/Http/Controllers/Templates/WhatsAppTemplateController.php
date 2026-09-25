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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            ->with(['account.phoneNumbers', 'components.variables', 'components.buttons'])
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
        $template->load(['account.phoneNumbers', 'components.variables', 'components.buttons', 'history']);

        return Inertia::render('templates/show', [
            'template' => $this->serialize($template, true),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function store(SaveWhatsAppTemplateRequest $request, WhatsAppTemplateService $service): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $account = $this->accountForTeam($request, (int) $data['whatsapp_account_id']);
        [$mediaPath, $mediaHandle] = $this->uploadHeaderMedia($request, $account, $service);

        try {
            $template = DB::transaction(function () use ($account, $data, $service, $mediaPath, $mediaHandle): WhatsAppTemplate {
                $template = $account->templates()->create([
                    ...$this->templateAttributes($data),
                    'status' => 'draft',
                    'assigned_category' => null,
                    'meta_template_id' => null,
                ]);
                $service->replaceComponents($template, $this->componentsFromInput($data, null, $mediaPath, $mediaHandle));

                return $template;
            });
        } catch (Throwable $exception) {
            if ($mediaPath !== null) {
                Storage::disk('whatsapp_media')->delete($mediaPath);
            }

            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json($this->serialize($template->load(['account.phoneNumbers', 'components.variables', 'components.buttons'])), 201);
        }

        return to_route('templates.show', $template);
    }

    public function update(SaveWhatsAppTemplateRequest $request, WhatsAppTemplate $template, WhatsAppTemplateService $service): RedirectResponse|JsonResponse
    {
        $this->authorizeTemplate($request, $template);
        $data = $request->validated();
        $account = $this->accountForTeam($request, (int) $data['whatsapp_account_id']);
        abort_unless($account->getKey() === $template->whatsapp_account_id, 422, 'A Conta do Template não pode ser alterada.');
        $previousMediaPath = $template->components()->where('type', 'header')->value('media_path');
        [$mediaPath, $mediaHandle] = $this->uploadHeaderMedia($request, $account, $service);

        try {
            DB::transaction(function () use ($template, $data, $service, $mediaPath, $mediaHandle): void {
                $template->update($this->templateAttributes($data));
                $service->replaceComponents($template, $this->componentsFromInput($data, $template, $mediaPath, $mediaHandle));
            });
        } catch (Throwable $exception) {
            if ($mediaPath !== null) {
                Storage::disk('whatsapp_media')->delete($mediaPath);
            }

            throw $exception;
        }

        if ($previousMediaPath !== null && $previousMediaPath !== $mediaPath) {
            Storage::disk('whatsapp_media')->delete($previousMediaPath);
        }

        if ($request->expectsJson()) {
            return response()->json($this->serialize($template->fresh(['account.phoneNumbers', 'components.variables', 'components.buttons'])));
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
            return response()->json($this->serialize($template->load(['account.phoneNumbers', 'components.variables', 'components.buttons'])));
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
            ->where('team_id', $teamId)
            ->whereHas('phoneNumbers.channel', fn ($query) => $query
                ->where('type', ChannelType::WhatsApp))
            ->with('phoneNumbers')
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
            ->where('team_id', $teamId)
            ->whereHas('phoneNumbers.channel', fn ($query) => $query->where('type', ChannelType::WhatsApp))
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
        ];
    }

    private function componentsFromInput(
        array $data,
        ?WhatsAppTemplate $template = null,
        ?string $mediaPath = null,
        ?string $mediaHandle = null,
    ): array {
        $providedExamples = [];
        foreach ($data['variables'] ?? [] as $variable) {
            if (! is_array($variable)) {
                continue;
            }

            $component = Str::lower((string) ($variable['component'] ?? 'body'));
            $position = (int) ($variable['position'] ?? 0);
            if ($position > 0) {
                $providedExamples[$component][$position] = $variable['example'] ?? null;
            }
        }

        $components = [];
        $existingHeader = $template?->componentByType('header');
        $headerText = array_key_exists('header_text', $data) ? $data['header_text'] : $template?->headerText();
        $headerType = strtoupper((string) ($data['header_type'] ?? $template?->headerType() ?? 'TEXT'));
        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $handle = $mediaHandle ?? $existingHeader?->media_handle;
            $components[] = [
                'type' => 'HEADER',
                'format' => $headerType,
                'media_path' => $mediaPath ?? $existingHeader?->media_path,
                'media_handle' => $handle,
                'example' => $handle === null ? [] : ['header_handle' => [$handle]],
            ];
        } elseif (filled($headerText)) {
            $components[] = [
                'type' => 'HEADER',
                'format' => $headerType,
                'text' => $headerText,
                'example' => ['header_text' => $this->examplesForText('header', (string) $headerText, $providedExamples, $template)],
            ];
        }

        $body = (string) $data['body'];
        $bodyExamples = $this->examplesForText('body', $body, $providedExamples, $template);
        $components[] = array_filter([
            'type' => 'BODY',
            'text' => $body,
            'example' => $bodyExamples === [] ? null : ['body_text' => [$bodyExamples]],
        ], fn (mixed $value): bool => $value !== null);

        $footer = array_key_exists('footer', $data) ? $data['footer'] : $template?->footerText();
        if (filled($footer)) {
            $components[] = ['type' => 'FOOTER', 'text' => $footer];
        }

        $buttons = array_key_exists('buttons', $data) ? $data['buttons'] : ($template?->buttonDefinitions() ?? []);
        if (is_array($buttons) && $buttons !== []) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => array_values($buttons),
            ];
        }

        return $components;
    }

    private function uploadHeaderMedia(
        SaveWhatsAppTemplateRequest $request,
        WhatsAppAccount $account,
        WhatsAppTemplateService $service,
    ): array {
        $file = $request->file('header_media');
        if ($file === null) {
            return [null, null];
        }

        $path = $file->store('template-headers', 'whatsapp_media');
        if (! is_string($path)) {
            throw ValidationException::withMessages([
                'header_media' => 'Não foi possível salvar a cópia local do exemplo de mídia.',
            ]);
        }

        try {
            $handle = $service->uploadHeaderMedia($account, $file);
        } catch (Throwable $exception) {
            Storage::disk('whatsapp_media')->delete($path);

            throw ValidationException::withMessages([
                'header_media' => $exception->getMessage(),
            ]);
        }

        return [$path, $handle];
    }

    private function examplesForText(string $component, string $text, array $providedExamples, ?WhatsAppTemplate $template): array
    {
        preg_match_all('/{{\s*(\d+)\s*}}/', $text, $matches);
        $positions = array_values(array_unique(array_map('intval', $matches[1])));
        if ($positions === []) {
            return [];
        }

        $existingExamples = [];
        foreach ($template?->variableDefinitions() ?? [] as $variable) {
            if (($variable['component'] ?? null) === $component) {
                $existingExamples[(int) $variable['position']] = $variable['example'] ?? null;
            }
        }

        return array_map(
            fn (int $position): ?string => $providedExamples[$component][$position]
                ?? $existingExamples[$position]
                ?? null,
            $positions,
        );
    }

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
            'qualityRating' => $template->quality_rating,
            'body' => $template->bodyText(),
            'headerType' => $template->headerType(),
            'headerText' => $template->headerText(),
            'headerMediaHandleAvailable' => filled($template->componentByType('header')?->media_handle),
            'footer' => $template->footerText(),
            'buttons' => $template->buttonDefinitions(),
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
                'oldQualityRating' => $history->old_quality_rating,
                'newQualityRating' => $history->new_quality_rating,
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
        $phone = $account->phoneNumbers->first();

        return collect([$phone?->verified_name, $phone?->display_phone_number])
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
