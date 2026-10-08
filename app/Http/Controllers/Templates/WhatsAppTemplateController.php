<?php

namespace App\Http\Controllers\Templates;

use App\Enums\ChannelType;
use App\Enums\TeamPermission;
use App\Enums\WhatsAppTemplateFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\SaveWhatsAppTemplateRequest;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateComponent;
use App\WhatsApp\WhatsAppTemplateLibrary;
use App\WhatsApp\WhatsAppTemplateService;
use App\WhatsApp\WhatsAppTemplateSynchronizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'format' => ['nullable', Rule::enum(WhatsAppTemplateFormat::class)],
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
            ->when(isset($filters['format']), fn ($query) => $query->where('format', $filters['format']))
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
                'format' => $filters['format'] ?? null,
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

    public function create(Request $request, WhatsAppTemplateLibrary $library): Response
    {
        $this->authorizeManagement($request);
        $accounts = $this->accounts($request->user()->company()->getKey());
        abort_if($accounts->isEmpty(), 404);

        return Inertia::render('templates/form', [
            'template' => null,
            'accounts' => $this->accountOptions($accounts),
            'library' => $library->all(),
        ]);
    }

    public function edit(Request $request, WhatsAppTemplate $template): Response
    {
        $this->authorizeManagement($request);
        $this->authorizeTemplate($request, $template);
        $template->load(['account.phoneNumbers', 'components.variables', 'components.buttons']);

        return Inertia::render('templates/form', [
            'template' => $this->serialize($template),
            'accounts' => $this->accountOptions($this->accounts($request->user()->company()->getKey())),
            'library' => [],
        ]);
    }

    public function store(SaveWhatsAppTemplateRequest $request, WhatsAppTemplateService $service): RedirectResponse|JsonResponse
    {
        $data = $request->validated();
        $account = $this->accountForTeam($request, (int) $data['whatsapp_account_id']);
        $media = $this->uploadTemplateMedia($request, $account, $service);

        try {
            $template = DB::transaction(function () use ($account, $data, $service, $media): WhatsAppTemplate {
                $template = $account->templates()->create([
                    ...$this->templateAttributes($data),
                    'library_key' => $data['library_key'] ?? null,
                    'status' => 'draft',
                    'assigned_category' => null,
                    'meta_template_id' => null,
                ]);
                $service->replaceComponents($template, $this->componentsFromInput($data, null, $media));

                return $template;
            });
        } catch (Throwable $exception) {
            $this->deleteMedia($media);

            throw $exception;
        }

        if ($request->expectsJson()) {
            return response()->json($this->serialize($template->load(['account.phoneNumbers', 'components.variables', 'components.buttons'])), 201);
        }

        return $this->afterSaving($template, (bool) ($data['submit'] ?? false), $service, 'Rascunho do Template criado.');
    }

    public function update(SaveWhatsAppTemplateRequest $request, WhatsAppTemplate $template, WhatsAppTemplateService $service): RedirectResponse|JsonResponse
    {
        $this->authorizeTemplate($request, $template);
        $data = $request->validated();
        $account = $this->accountForTeam($request, (int) $data['whatsapp_account_id']);
        abort_unless($account->getKey() === $template->whatsapp_account_id, 422, 'A Conta do Template não pode ser alterada.');
        $previousMediaPaths = $template->components()->whereNotNull('media_path')->pluck('media_path')->all();
        $media = $this->uploadTemplateMedia($request, $account, $service);

        try {
            DB::transaction(function () use ($template, $data, $service, $media): void {
                $template->update($this->templateAttributes($data));
                $service->replaceComponents($template, $this->componentsFromInput($data, $template, $media));
            });
        } catch (Throwable $exception) {
            $this->deleteMedia($media);

            throw $exception;
        }

        $currentMediaPaths = $template->components()->whereNotNull('media_path')->pluck('media_path')->all();
        foreach (array_diff($previousMediaPaths, $currentMediaPaths) as $unusedPath) {
            Storage::disk('whatsapp_media')->delete($unusedPath);
        }

        if ($request->expectsJson()) {
            return response()->json($this->serialize($template->fresh(['account.phoneNumbers', 'components.variables', 'components.buttons'])));
        }

        return $this->afterSaving($template, (bool) ($data['submit'] ?? false), $service, 'Template atualizado.');
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

    private function afterSaving(WhatsAppTemplate $template, bool $submit, WhatsAppTemplateService $service, string $savedMessage): RedirectResponse
    {
        if (! $submit) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $savedMessage]);

            return to_route('templates.show', $template);
        }

        try {
            $service->submit($template);
        } catch (Throwable $exception) {
            report($exception);
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'O rascunho foi salvo, mas a Meta não aceitou o envio. '.$this->submitError($exception),
            ]);

            return to_route('templates.show', $template);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Template enviado para aprovação da Meta.',
        ]);

        return to_route('templates.show', $template);
    }

    /**
     * @param  Collection<int, WhatsAppAccount>  $accounts
     * @return array<int, array{id: int, label: string}>
     */
    private function accountOptions(Collection $accounts): array
    {
        return $accounts->map(fn (WhatsAppAccount $account): array => [
            'id' => $account->getKey(),
            'label' => $this->accountLabel($account),
        ])->values()->all();
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
            'format' => $data['format'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{header: array{0: string, 1: string}|null, cards: array<int, array{0: string, 1: string}>}  $media
     * @return array<int, array<string, mixed>>
     */
    private function componentsFromInput(array $data, ?WhatsAppTemplate $template, array $media): array
    {
        $format = WhatsAppTemplateFormat::from($data['format']);
        $providedExamples = [];
        $providedDescriptions = ['body' => [], 'header' => []];
        foreach ($data['variables'] ?? [] as $variable) {
            if (! is_array($variable)) {
                continue;
            }

            $component = Str::lower((string) ($variable['component'] ?? 'body'));
            $position = (int) ($variable['position'] ?? 0);
            if ($position > 0) {
                $providedExamples[$component][$position] = $variable['example'] ?? null;
                $providedDescriptions[$component][$position] = $variable['description'] ?? null;
            }
        }
        $sendsDescriptions = collect($data['variables'] ?? [])->contains(fn (mixed $variable): bool => is_array($variable) && array_key_exists('description', $variable));

        if ($format === WhatsAppTemplateFormat::Authentication) {
            return $this->authenticationComponents($data);
        }

        $body = (string) ($data['body'] ?? '');
        $bodyExamples = $this->examplesForText('body', $body, $providedExamples, $template);
        $bodyComponent = array_filter([
            'type' => 'BODY',
            'text' => $body,
            'example' => $bodyExamples === [] ? null : ['body_text' => [$bodyExamples]],
            'variable_descriptions' => $sendsDescriptions ? $providedDescriptions['body'] : null,
        ], fn (mixed $value): bool => $value !== null);

        if ($format === WhatsAppTemplateFormat::CallPermission) {
            return [$bodyComponent, ['type' => 'CALL_PERMISSION_REQUEST']];
        }

        if ($format === WhatsAppTemplateFormat::Carousel) {
            return [$bodyComponent, $this->carouselComponent($data, $template, $media['cards'])];
        }

        $components = [];
        $header = $this->headerComponent($data, $template, $media['header'], $providedExamples);
        if ($header !== null) {
            $components[] = $sendsDescriptions && ($header['format'] ?? null) === 'TEXT'
                ? [...$header, 'variable_descriptions' => $providedDescriptions['header']]
                : $header;
        }

        if ($format === WhatsAppTemplateFormat::LimitedTimeOffer) {
            $components[] = [
                'type' => 'LIMITED_TIME_OFFER',
                'limited_time_offer' => [
                    'text' => $data['offer_text'],
                    'has_expiration' => (bool) ($data['offer_has_expiration'] ?? false),
                ],
            ];
        }

        $components[] = $bodyComponent;

        $footer = array_key_exists('footer', $data) ? $data['footer'] : $template?->footerText();
        if ($format === WhatsAppTemplateFormat::Standard && filled($footer)) {
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

    /**
     * @param  array<string, mixed>  $data
     * @param  array{0: string, 1: string}|null  $uploadedMedia
     * @param  array<string, array<int, mixed>>  $providedExamples
     * @return array<string, mixed>|null
     */
    private function headerComponent(array $data, ?WhatsAppTemplate $template, ?array $uploadedMedia, array $providedExamples): ?array
    {
        $existingHeader = $template?->componentByType('header');
        $headerText = array_key_exists('header_text', $data) ? $data['header_text'] : $template?->headerText();
        $headerType = strtoupper((string) ($data['header_type'] ?? $template?->headerType() ?? 'TEXT'));

        if (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $keepsExisting = $uploadedMedia === null && $existingHeader?->format === strtolower($headerType);
            $handle = $uploadedMedia[1] ?? ($keepsExisting ? $existingHeader->media_handle : null);

            return [
                'type' => 'HEADER',
                'format' => $headerType,
                'media_path' => $uploadedMedia[0] ?? ($keepsExisting ? $existingHeader->media_path : null),
                'media_handle' => $handle,
                'example' => $handle === null ? [] : ['header_handle' => [$handle]],
            ];
        }

        if ($headerType === 'NONE' || blank($headerText)) {
            return null;
        }

        return [
            'type' => 'HEADER',
            'format' => 'TEXT',
            'text' => $headerText,
            'example' => ['header_text' => $this->examplesForText('header', (string) $headerText, $providedExamples, $template)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array{0: string, 1: string}>  $uploadedMedia
     * @return array<string, mixed>
     */
    private function carouselComponent(array $data, ?WhatsAppTemplate $template, array $uploadedMedia): array
    {
        $existingCards = $template?->carouselCards() ?? [];
        $headerFormat = strtoupper((string) $data['card_header_format']);
        $cards = [];

        foreach (array_values($data['cards'] ?? []) as $index => $card) {
            $existingHeader = is_numeric($card['existing_index'] ?? null)
                ? ($existingCards[(int) $card['existing_index']]['header'] ?? null)
                : null;
            $keepsExisting = ! isset($uploadedMedia[$index]) && $existingHeader?->format === strtolower($headerFormat);
            $handle = $uploadedMedia[$index][1] ?? ($keepsExisting ? $existingHeader->media_handle : null);
            $components = [[
                'type' => 'HEADER',
                'format' => $headerFormat,
                'media_path' => $uploadedMedia[$index][0] ?? ($keepsExisting ? $existingHeader->media_path : null),
                'media_handle' => $handle,
                'example' => $handle === null ? [] : ['header_handle' => [$handle]],
            ]];

            $cardBody = (string) ($card['body'] ?? '');
            if (filled($cardBody)) {
                preg_match_all('/{{\s*(\d+)\s*}}/', $cardBody, $matches);
                $positions = array_values(array_unique(array_map('intval', $matches[1])));
                $examples = array_values($card['body_examples'] ?? []);
                $components[] = array_filter([
                    'type' => 'BODY',
                    'text' => $cardBody,
                    'example' => $positions === [] ? null : ['body_text' => [array_map(
                        fn (int $position): ?string => $examples[$position - 1] ?? null,
                        $positions,
                    )]],
                ], fn (mixed $value): bool => $value !== null);
            }

            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => array_values($card['buttons'] ?? []),
            ];
            $cards[] = ['components' => $components];
        }

        return ['type' => 'CAROUSEL', 'cards' => $cards];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function authenticationComponents(array $data): array
    {
        $otpType = (string) $data['otp_type'];
        $components = [[
            'type' => 'BODY',
            'add_security_recommendation' => (bool) ($data['add_security_recommendation'] ?? false),
        ]];

        if (filled($data['code_expiration_minutes'] ?? null)) {
            $components[] = [
                'type' => 'FOOTER',
                'code_expiration_minutes' => (int) $data['code_expiration_minutes'],
            ];
        }

        $components[] = [
            'type' => 'BUTTONS',
            'buttons' => [array_filter([
                'type' => 'otp',
                'otp_type' => $otpType,
                'text' => 'Copiar código',
                'autofill_text' => $otpType === 'copy_code' ? null : 'Preencher',
                'supported_apps' => $otpType === 'copy_code' ? null : [[
                    'package_name' => $data['package_name'],
                    'signature_hash' => $data['signature_hash'],
                ]],
            ], fn (mixed $value): bool => $value !== null)],
        ];

        return $components;
    }

    /** @return array{header: array{0: string, 1: string}|null, cards: array<int, array{0: string, 1: string}>} */
    private function uploadTemplateMedia(
        SaveWhatsAppTemplateRequest $request,
        WhatsAppAccount $account,
        WhatsAppTemplateService $service,
    ): array {
        $media = ['header' => null, 'cards' => []];

        try {
            $headerFile = $request->file('header_media');
            if ($headerFile instanceof UploadedFile) {
                $media['header'] = $this->uploadMedia($headerFile, 'header_media', $account, $service);
            }

            foreach (array_keys((array) $request->input('cards', [])) as $index) {
                $cardFile = $request->file('cards.'.$index.'.media');
                if ($cardFile instanceof UploadedFile) {
                    $media['cards'][(int) $index] = $this->uploadMedia($cardFile, 'cards.'.$index.'.media', $account, $service);
                }
            }
        } catch (Throwable $exception) {
            $this->deleteMedia($media);

            throw $exception;
        }

        return $media;
    }

    /** @return array{0: string, 1: string} */
    private function uploadMedia(UploadedFile $file, string $field, WhatsAppAccount $account, WhatsAppTemplateService $service): array
    {
        $path = $file->store('template-headers', 'whatsapp_media');
        if (! is_string($path)) {
            throw ValidationException::withMessages([
                $field => 'Não foi possível salvar a cópia local do exemplo de mídia.',
            ]);
        }

        try {
            $handle = $service->uploadHeaderMedia($account, $file);
        } catch (Throwable $exception) {
            Storage::disk('whatsapp_media')->delete($path);

            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        return [$path, $handle];
    }

    /** @param array{header: array{0: string, 1: string}|null, cards: array<int, array{0: string, 1: string}>} $media */
    private function deleteMedia(array $media): void
    {
        $paths = array_filter([
            $media['header'][0] ?? null,
            ...array_map(fn (array $card): string => $card[0], $media['cards']),
        ]);

        if ($paths !== []) {
            Storage::disk('whatsapp_media')->delete($paths);
        }
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
            'format' => $template->format->value,
            'formatLabel' => $template->format->label(),
            'libraryKey' => $template->library_key,
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
            'headerMediaName' => $this->mediaName($template->componentByType('header')),
            'offer' => $this->offer($template),
            'cards' => $this->cards($template),
            'authentication' => $this->authentication($template),
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

    /** @return array{text: string, hasExpiration: bool}|null */
    private function offer(WhatsAppTemplate $template): ?array
    {
        $offer = $template->componentByType('limited_time_offer');

        return $offer === null ? null : [
            'text' => (string) $offer->text,
            'hasExpiration' => (bool) ($offer->settings['has_expiration'] ?? false),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function cards(WhatsAppTemplate $template): array
    {
        return array_map(function (array $card): array {
            $header = $card['header'] ?? null;
            $body = $card['body'] ?? null;
            $buttons = $card['buttons'] ?? null;

            return [
                'headerFormat' => $header?->format === null ? null : Str::upper($header->format),
                'mediaAvailable' => filled($header?->media_handle),
                'mediaName' => $this->mediaName($header),
                'body' => $body?->text,
                'bodyExamples' => $body === null ? [] : $body->variables->map(fn ($variable): ?string => $variable->example)->values()->all(),
                'buttons' => $buttons === null ? [] : $buttons->buttons->map(fn ($button): array => [
                    'type' => $button->type,
                    'text' => $button->text,
                    'url' => $button->url,
                    'phoneNumber' => $button->phone_number,
                    'example' => $button->example,
                ])->values()->all(),
            ];
        }, $template->carouselCards());
    }

    /** @return array<string, mixed>|null */
    private function authentication(WhatsAppTemplate $template): ?array
    {
        if ($template->format !== WhatsAppTemplateFormat::Authentication) {
            return null;
        }

        $button = collect($template->buttonDefinitions())->firstWhere('type', 'OTP') ?? [];
        $app = $button['supported_apps'][0] ?? [];

        return [
            'otpType' => $button['otp_type'] ?? 'copy_code',
            'packageName' => $app['package_name'] ?? '',
            'signatureHash' => $app['signature_hash'] ?? '',
            'addSecurityRecommendation' => (bool) ($template->componentByType('body')?->settings['add_security_recommendation'] ?? false),
            'codeExpirationMinutes' => $template->componentByType('footer')?->settings['code_expiration_minutes'] ?? null,
        ];
    }

    private function mediaName(?WhatsAppTemplateComponent $component): ?string
    {
        return filled($component?->media_path) ? basename((string) $component->media_path) : null;
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
