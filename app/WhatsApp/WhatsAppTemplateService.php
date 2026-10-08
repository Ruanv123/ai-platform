<?php

namespace App\WhatsApp;

use App\Enums\WhatsAppConnectionMethod;
use App\Enums\WhatsAppTemplateCategory;
use App\Enums\WhatsAppTemplateFormat;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateComponent;
use App\Models\WhatsAppTemplateHistory;
use App\WhatsApp\Oficial\MetaGraphApiResponseHandler;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class WhatsAppTemplateService
{
    public function __construct(private readonly MetaGraphApiResponseHandler $responseHandler) {}

    public function submit(WhatsAppTemplate $template): WhatsAppTemplate
    {
        $account = $template->account;
        $this->assertValidDraft($template);

        $response = $this->request($account)->post(
            $this->templatesUrl($account),
            $this->metaPayload($template),
        );
        $this->responseHandler->markChannelUnavailableForInvalidToken($response, $account);
        $response->throw();

        $oldStatus = $template->status->value;
        $oldCategory = $template->assigned_category?->value;
        $metaStatus = $this->status($response->json('status', 'PENDING'));
        $assignedCategory = $this->category($response->json('category'));
        $template
            ->forceFill([
                'meta_template_id' => $this->scalar($response->json('id')) ?? $template->meta_template_id,
                'status' => $metaStatus,
                'assigned_category' => $assignedCategory,
                'rejection_reason' => null,
                'rejection_explanation' => null,
                'submitted_at' => now(),
                'last_synced_at' => now(),
            ])
            ->save();

        $this->recordChange(
            $template,
            'submitted',
            $oldStatus,
            $metaStatus->value,
            $oldCategory,
            $assignedCategory?->value,
        );

        return $template->fresh();
    }

    public function uploadHeaderMedia(WhatsAppAccount $account, UploadedFile $file): string
    {
        $appId = $account->connection_method === WhatsAppConnectionMethod::TechProvider
            ? config('services.whatsapp.platform.app_id')
            : $account->app_id;

        if (blank($appId)) {
            throw new RuntimeException('Configure o App ID da Meta para enviar o exemplo de mídia.');
        }

        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $baseUrl = $this->graphBase();

        try {
            $session = Http::connectTimeout(3)
                ->timeout(15)
                ->withHeaders(['Authorization' => 'OAuth '.$account->access_token])
                ->post($baseUrl.'/'.rawurlencode((string) $appId).'/uploads?'.http_build_query([
                    'file_length' => $file->getSize(),
                    'file_type' => $mimeType,
                    'file_name' => $file->getClientOriginalName(),
                ]));
            $this->responseHandler->markChannelUnavailableForInvalidToken($session, $account);

            if (! $session->successful()) {
                throw new RuntimeException('Não foi possível criar uma sessão para enviar o exemplo de mídia à Meta.');
            }

            $uploadId = $session->json('id');
            if (! is_string($uploadId) || blank($uploadId)) {
                throw new RuntimeException('A Meta não devolveu uma sessão válida para o exemplo de mídia.');
            }

            $filePath = $file->getRealPath();
            if (! is_string($filePath)) {
                throw new RuntimeException('Não foi possível ler o arquivo de exemplo enviado.');
            }

            $stream = fopen($filePath, 'rb');
            if (! is_resource($stream)) {
                throw new RuntimeException('Não foi possível ler o arquivo de exemplo enviado.');
            }

            try {
                $upload = Http::connectTimeout(3)
                    ->timeout(120)
                    ->withHeaders([
                        'Authorization' => 'OAuth '.$account->access_token,
                        'file_offset' => '0',
                        'Content-Type' => $mimeType,
                    ])
                    ->withBody($stream, $mimeType)
                    ->post($baseUrl.'/'.$uploadId);
                $this->responseHandler->markChannelUnavailableForInvalidToken($upload, $account);
            } finally {
                fclose($stream);
            }

            if (! $upload->successful()) {
                throw new RuntimeException('Não foi possível enviar o exemplo de mídia à Meta.');
            }

            $handle = $upload->json('h');
            if (! is_string($handle) || blank($handle)) {
                throw new RuntimeException('A Meta não devolveu uma referência válida para o exemplo de mídia.');
            }

            return $handle;
        } catch (ConnectionException) {
            throw new RuntimeException('Não foi possível enviar o exemplo de mídia à Meta. Verifique sua conexão e tente novamente.');
        }
    }

    public function upsertFromMeta(WhatsAppAccount $account, array $remote): WhatsAppTemplate
    {
        $name = (string) ($remote['name'] ?? '');
        $language = (string) ($remote['language'] ?? 'pt_BR');
        $metaId = $this->scalar($remote['id'] ?? null);
        $template = WhatsAppTemplate::withoutGlobalScopes()
            ->where('whatsapp_account_id', $account->getKey())
            ->when($metaId !== null, fn ($query) => $query->where('meta_template_id', $metaId))
            ->when($metaId === null, fn ($query) => $query->where('name', $name)->where('language', $language))
            ->first();
        $components = is_array($remote['components'] ?? null) ? $remote['components'] : [];
        $oldStatus = $template?->status?->value;
        $oldCategory = $template?->assigned_category?->value;
        $status = $this->status($remote['status'] ?? 'PENDING');
        $assignedCategory = $this->category($remote['category'] ?? ($remote['correct_category'] ?? null));
        $requestedCategory = $template?->requested_category ?? $assignedCategory ?? WhatsAppTemplateCategory::Utility;
        $rejectionReason = $this->scalar($remote['rejected_reason'] ?? ($remote['rejection_reason'] ?? null));
        $attributes = [
            'whatsapp_account_id' => $account->getKey(),
            'meta_template_id' => $metaId,
            'name' => $name,
            'language' => $language,
            'format' => $template?->format ?? $this->formatFromMeta($components, $assignedCategory),
            'status' => $status,
            'requested_category' => $requestedCategory,
            'assigned_category' => $assignedCategory,
            'quality_rating' => $this->qualityRating($remote),
            'rejection_reason' => $rejectionReason,
            'rejection_explanation' => $this->explainRejection($rejectionReason),
            'last_synced_at' => now(),
            'submitted_at' => $template?->submitted_at ?? now(),
        ];

        return DB::transaction(function () use ($attributes, $components, $oldStatus, $oldCategory, $rejectionReason, $status, $assignedCategory, $template): WhatsAppTemplate {
            if ($template === null) {
                $template = WhatsAppTemplate::withoutGlobalScopes()->create($attributes);
            } else {
                $template->forceFill($attributes)->save();
            }

            $this->replaceComponents($template, $components);

            if ($oldStatus === null) {
                $this->recordChange(
                    $template,
                    'imported',
                    null,
                    $status->value,
                    null,
                    $assignedCategory?->value,
                    $rejectionReason,
                );
            } elseif ($oldStatus !== $status->value) {
                $this->recordChange(
                    $template,
                    'status_changed',
                    $oldStatus,
                    $status->value,
                    $oldCategory,
                    $assignedCategory?->value,
                    $rejectionReason,
                );
            } elseif ($oldCategory !== $assignedCategory?->value) {
                $this->recordChange(
                    $template,
                    'category_changed',
                    $oldStatus,
                    $status->value,
                    $oldCategory,
                    $assignedCategory?->value,
                );
            }

            return $template->load('components.variables', 'components.buttons');
        });
    }

    public function updateFromWebhook(WhatsAppAccount $account, string $field, array $value): bool
    {
        $metaTemplateId = $this->scalar($value['message_template_id'] ?? null);

        if ($metaTemplateId === null) {
            return false;
        }

        $template = $account->templates()
            ->where('meta_template_id', $metaTemplateId)
            ->first();

        if ($template === null) {
            return false;
        }

        return match ($field) {
            'message_template_status_update' => $this->updateStatusFromWebhook($template, $value),
            'template_category_update' => $this->updateCategoryFromWebhook($template, $value),
            'message_template_quality_update' => $this->updateQualityFromWebhook($template, $value),
            default => false,
        };
    }

    public function replaceComponents(WhatsAppTemplate $template, array $components): void
    {
        DB::transaction(function () use ($template, $components): void {
            $existingComponents = $template->components()->with('variables')->get();
            $existingMedia = $existingComponents
                ->whereNotNull('media_handle')
                ->keyBy(fn (WhatsAppTemplateComponent $component): string => $this->mediaKey($component->card_index, $component->type));
            $existingDescriptions = $existingComponents
                ->mapWithKeys(fn (WhatsAppTemplateComponent $component): array => [
                    $this->mediaKey($component->card_index, $component->type) => $component->variables
                        ->whereNotNull('description')
                        ->mapWithKeys(fn ($variable): array => [(int) $variable->position => $variable->description])
                        ->all(),
                ]);
            $template->components()->delete();

            $position = 0;
            foreach (array_values($components) as $componentData) {
                if (! is_array($componentData)) {
                    continue;
                }

                if ($this->componentType($componentData['type'] ?? null) !== 'carousel') {
                    $this->createComponent($template, $componentData, null, $position++, $existingMedia, $existingDescriptions);

                    continue;
                }

                $this->createComponent($template, ['type' => 'carousel'], null, $position++, $existingMedia, $existingDescriptions);
                $cards = is_array($componentData['cards'] ?? null) ? array_values($componentData['cards']) : [];
                foreach ($cards as $cardIndex => $card) {
                    $cardComponents = is_array($card['components'] ?? null) ? array_values($card['components']) : [];
                    foreach ($cardComponents as $cardPosition => $cardComponent) {
                        if (is_array($cardComponent)) {
                            $this->createComponent($template, $cardComponent, $cardIndex, $cardPosition, $existingMedia, $existingDescriptions);
                        }
                    }
                }
            }
        });
    }

    public function markDeleted(WhatsAppTemplate $template): void
    {
        if ($template->status === WhatsAppTemplateStatus::Deleted) {
            return;
        }

        $oldStatus = $template->status->value;
        $template
            ->forceFill([
                'status' => WhatsAppTemplateStatus::Deleted,
                'last_synced_at' => now(),
            ])
            ->save();
        $this->recordChange($template, 'deleted', $oldStatus, WhatsAppTemplateStatus::Deleted->value);
    }

    public function metaPayload(WhatsAppTemplate $template): array
    {
        $template->loadMissing('components.variables', 'components.buttons');
        $components = [];

        foreach ($template->topLevelComponents() as $component) {
            if ($component->type === 'carousel') {
                $components[] = [
                    'type' => 'CAROUSEL',
                    'cards' => array_map(fn (array $card): array => [
                        'components' => collect($card)
                            ->sortBy('position')
                            ->map(fn (WhatsAppTemplateComponent $cardComponent): array => $this->metaComponent($cardComponent))
                            ->values()
                            ->all(),
                    ], $template->carouselCards()),
                ];

                continue;
            }

            $metaComponent = $this->metaComponent($component);
            if ($template->format === WhatsAppTemplateFormat::Authentication && $component->type === 'body') {
                unset($metaComponent['text'], $metaComponent['example']);
            }

            $components[] = $metaComponent;
        }

        return [
            'name' => $template->name,
            'language' => $template->language,
            'category' => Str::upper($template->requested_category->value),
            'components' => $components,
        ];
    }

    public function assertValidDraft(WhatsAppTemplate $template): void
    {
        if (! preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $template->name)) {
            throw new RuntimeException('O nome precisa usar apenas letras minúsculas, números e sublinhados.');
        }

        if (! $template->format->allowsCategory($template->requested_category)) {
            throw new RuntimeException('O formato escolhido não está disponível para a categoria do Template.');
        }

        if ($template->format === WhatsAppTemplateFormat::Authentication) {
            $this->assertValidAuthentication($template);

            return;
        }

        $this->assertValidText(
            $template->bodyText(),
            'corpo',
            $template->format === WhatsAppTemplateFormat::LimitedTimeOffer ? 600 : 1024,
            true,
        );

        foreach ($template->variableDefinitions() as $variable) {
            if (blank($variable['example'] ?? null)) {
                throw new RuntimeException('Informe um exemplo para cada variável do Template.');
            }
        }

        $header = $template->componentByType('header');
        if ($header !== null
            && in_array($header->format, ['image', 'video', 'document'], true)
            && blank($header->media_handle)) {
            throw new RuntimeException('Envie um arquivo de exemplo para o cabeçalho de mídia do Template.');
        }

        match ($template->format) {
            WhatsAppTemplateFormat::Carousel => $this->assertValidCarousel($template),
            WhatsAppTemplateFormat::Coupon => $this->assertValidCoupon($template),
            WhatsAppTemplateFormat::LimitedTimeOffer => $this->assertValidLimitedTimeOffer($template),
            WhatsAppTemplateFormat::CallPermission => $this->assertValidCallPermission($template),
            default => $this->assertValidButtons($template->buttonDefinitions(), 3),
        };
    }

    public function explainRejection(?string $reason): ?string
    {
        return match (Str::upper(trim((string) $reason))) {
            'ABUSIVE_CONTENT',
            'SCAM' => 'A Meta identificou conteúdo abusivo ou enganoso. Remova promessas, ameaças e linguagem que possa induzir o Contato ao erro.',
            'INCORRECT_FORMAT',
            'INVALID_FORMAT' => 'A estrutura do Template não segue o formato aceito. Confira as variáveis, botões e limites de caracteres.',
            'INCORRECT_CATEGORY' => 'A categoria escolhida não corresponde ao conteúdo. Escolha Utilidade para avisos operacionais e Marketing apenas para divulgação.',
            'TAG_CONTENT_MISMATCH' => 'O conteúdo não corresponde à categoria ou às tags declaradas. Revise o objetivo e a categoria do Template.',
            '' => null,
            default => 'A Meta recusou este Template. Revise o conteúdo, a categoria e as variáveis antes de reenviar.',
        };
    }

    private function metaComponent(WhatsAppTemplateComponent $component): array
    {
        if ($component->type === 'buttons') {
            return [
                'type' => 'BUTTONS',
                'buttons' => $component->buttons->map(fn ($button): array => [
                    ...$this->metaButton($button->type, $button->text, $button->url, $button->phone_number, $button->example),
                    ...($button->settings ?? []),
                ])
                    ->values()
                    ->all(),
            ];
        }

        if ($component->type === 'limited_time_offer') {
            return [
                'type' => 'LIMITED_TIME_OFFER',
                'limited_time_offer' => [
                    'text' => (string) $component->text,
                    'has_expiration' => (bool) ($component->settings['has_expiration'] ?? false),
                ],
            ];
        }

        if ($component->type === 'call_permission_request') {
            return ['type' => 'CALL_PERMISSION_REQUEST'];
        }

        $metaComponent = ['type' => Str::upper($component->type)];
        if ($component->type === 'header') {
            $metaComponent['format'] = Str::upper((string) ($component->format ?? 'text'));
            if (in_array($component->format, ['image', 'video', 'document'], true)) {
                if (filled($component->media_handle)) {
                    $metaComponent['example'] = ['header_handle' => [$component->media_handle]];
                }

                return $metaComponent;
            }
        }

        if ($component->text !== null) {
            $metaComponent['text'] = $component->text;
        }

        $examples = $component->variables->map(fn ($variable): ?string => $variable->example)->values()->all();
        if ($examples !== [] && collect($examples)->every(fn (?string $example): bool => filled($example))) {
            if ($component->type === 'body') {
                $metaComponent['example'] = ['body_text' => [$examples]];
            } elseif ($component->type === 'header') {
                $metaComponent['example'] = ['header_text' => $examples];
            }
        }

        foreach ($component->settings ?? [] as $setting => $value) {
            $metaComponent[$setting] = $value;
        }

        return $metaComponent;
    }

    private function metaButton(string $type, ?string $text, ?string $url, ?string $phoneNumber, ?string $example): array
    {
        $metaButton = ['type' => match ($type) {
            'quick_reply' => 'QUICK_REPLY',
            'url' => 'URL',
            'phone_number' => 'PHONE_NUMBER',
            'copy_code' => 'COPY_CODE',
            'otp' => 'OTP',
            default => Str::upper($type),
        }];

        if ($text !== null) {
            $metaButton['text'] = $text;
        }
        if ($url !== null) {
            $metaButton['url'] = $url;
        }
        if ($phoneNumber !== null) {
            $metaButton['phone_number'] = $phoneNumber;
        }
        if ($example !== null) {
            $metaButton['example'] = $type === 'url' ? [$example] : $example;
        }

        return $metaButton;
    }

    private function replaceButtons(WhatsAppTemplateComponent $component, mixed $buttons): void
    {
        if (! is_array($buttons)) {
            return;
        }

        foreach (array_values($buttons) as $position => $buttonData) {
            if (! is_array($buttonData)) {
                continue;
            }

            $type = $this->buttonType($buttonData['type'] ?? null);
            if ($type === null) {
                continue;
            }

            $example = $buttonData['example'] ?? null;
            if (is_array($example)) {
                $example = $example[0] ?? null;
            }

            $button = $component->buttons()->create([
                'type' => $type,
                'text' => $this->scalar($buttonData['text'] ?? null),
                'url' => $this->scalar($buttonData['url'] ?? null),
                'phone_number' => $this->scalar($buttonData['phone_number'] ?? null),
                'example' => $this->scalar($example),
                'settings' => $type === 'otp' ? $this->otpSettings($buttonData) : null,
                'position' => $position,
            ]);

            if ($type !== 'url' || blank($button->url)) {
                continue;
            }

            foreach ($this->positions($button->url) as $variablePosition) {
                $component->variables()->create([
                    'position' => $variablePosition,
                    'example' => $button->example,
                ]);
            }
        }
    }

    /** @param array<int, string> $existingDescriptions */
    private function replaceVariables(WhatsAppTemplateComponent $component, array $componentData, array $existingDescriptions = []): void
    {
        $providedDescriptions = is_array($componentData['variable_descriptions'] ?? null) ? $componentData['variable_descriptions'] : null;
        $examples = match ($component->type) {
            'body' => data_get($componentData, 'example.body_text.0', []),
            'header' => data_get($componentData, 'example.header_text', []),
            default => [],
        };
        if (! is_array($examples)) {
            $examples = [];
        }

        foreach ($this->positions((string) $component->text) as $position) {
            $component->variables()->create([
                'position' => $position,
                'example' => $this->scalar($examples[$position - 1] ?? null),
                'description' => $providedDescriptions === null
                    ? ($existingDescriptions[$position] ?? null)
                    : $this->scalar($providedDescriptions[$position] ?? null),
            ]);
        }
    }

    private function componentType(mixed $type): ?string
    {
        return match (Str::lower((string) $type)) {
            'header' => 'header',
            'body' => 'body',
            'footer' => 'footer',
            'buttons' => 'buttons',
            'carousel' => 'carousel',
            'limited_time_offer' => 'limited_time_offer',
            'call_permission_request' => 'call_permission_request',
            default => null,
        };
    }

    private function buttonType(mixed $type): ?string
    {
        return match (Str::lower((string) $type)) {
            'quick_reply' => 'quick_reply',
            'url' => 'url',
            'phone_number' => 'phone_number',
            'copy_code' => 'copy_code',
            'otp' => 'otp',
            default => null,
        };
    }

    private function format(mixed $format): ?string
    {
        return is_scalar($format) && filled($format) ? Str::lower((string) $format) : null;
    }

    private function componentMediaHandle(array $example): ?string
    {
        $handle = data_get($example, 'header_handle.0');

        return $this->scalar($handle ?? $example['header_handle'] ?? null);
    }

    private function qualityRating(array $remote): ?string
    {
        $rating = $remote['quality_rating'] ?? data_get($remote, 'quality_score.score') ?? $remote['quality_score'] ?? null;

        return $this->scalar($rating);
    }

    private function updateStatusFromWebhook(WhatsAppTemplate $template, array $value): bool
    {
        $status = $this->webhookStatus($value['event'] ?? null);

        if ($status === null) {
            return false;
        }

        $oldStatus = $template->status->value;
        $oldCategory = $template->assigned_category?->value;
        $reason = $status === WhatsAppTemplateStatus::Rejected
            ? $this->scalar($value['reason'] ?? ($value['rejected_reason'] ?? null))
            : null;
        $hasChanged = $oldStatus !== $status->value || $template->rejection_reason !== $reason;

        DB::transaction(function () use ($template, $status, $reason, $hasChanged, $oldStatus, $oldCategory): void {
            $template->forceFill([
                'status' => $status,
                'rejection_reason' => $reason,
                'rejection_explanation' => $this->explainRejection($reason),
                'last_synced_at' => now(),
            ])->save();

            if ($hasChanged) {
                $this->recordChange(
                    $template,
                    'status_changed',
                    $oldStatus,
                    $status->value,
                    $oldCategory,
                    $oldCategory,
                    $reason,
                );
            }
        });

        return true;
    }

    private function updateCategoryFromWebhook(WhatsAppTemplate $template, array $value): bool
    {
        $category = $this->category($value['new_category'] ?? null);

        if ($category === null) {
            return false;
        }

        $oldStatus = $template->status->value;
        $oldCategory = $template->assigned_category?->value;

        DB::transaction(function () use ($template, $category, $oldStatus, $oldCategory): void {
            $template->forceFill([
                'assigned_category' => $category,
                'last_synced_at' => now(),
            ])->save();

            if ($oldCategory !== $category->value) {
                $this->recordChange(
                    $template,
                    'category_changed',
                    $oldStatus,
                    $oldStatus,
                    $oldCategory,
                    $category->value,
                );
            }
        });

        return true;
    }

    private function updateQualityFromWebhook(WhatsAppTemplate $template, array $value): bool
    {
        $qualityRating = $this->scalar($value['new_quality_score'] ?? null);

        if ($qualityRating === null) {
            return false;
        }

        $oldQualityRating = $template->quality_rating;
        $status = $template->status->value;
        $category = $template->assigned_category?->value;

        DB::transaction(function () use ($template, $qualityRating, $oldQualityRating, $status, $category): void {
            $template->forceFill([
                'quality_rating' => $qualityRating,
                'last_synced_at' => now(),
            ])->save();

            if ($oldQualityRating !== $qualityRating) {
                $this->recordChange(
                    $template,
                    'quality_changed',
                    $status,
                    $status,
                    $category,
                    $category,
                    null,
                    $oldQualityRating,
                    $qualityRating,
                );
            }
        });

        return true;
    }

    private function webhookStatus(mixed $event): ?WhatsAppTemplateStatus
    {
        return match (Str::upper((string) $event)) {
            'APPROVED' => WhatsAppTemplateStatus::Approved,
            'REJECTED', 'IN_APPEAL' => WhatsAppTemplateStatus::Rejected,
            'PAUSED' => WhatsAppTemplateStatus::Paused,
            'DISABLED', 'LIMIT_EXCEEDED' => WhatsAppTemplateStatus::Disabled,
            'DELETED', 'PENDING_DELETION' => WhatsAppTemplateStatus::Deleted,
            default => null,
        };
    }

    private function request(WhatsAppAccount $account): PendingRequest
    {
        return Http::withToken($account->access_token)
            ->connectTimeout(3)
            ->timeout(15);
    }

    private function templatesUrl(WhatsAppAccount $account): string
    {
        return $this->graphBase().'/'.rawurlencode($account->waba_id).'/message_templates';
    }

    private function graphBase(): string
    {
        return rtrim((string) config('services.whatsapp.graph_api_url', 'https://graph.facebook.com'), '/').'/'.trim((string) config('services.whatsapp.graph_api_version', 'v23.0'), '/');
    }

    private function positions(string $text): array
    {
        preg_match_all('/{{\s*(\d+)\s*}}/', $text, $matches);

        return array_values(array_unique(array_map('intval', $matches[1])));
    }

    private function status(mixed $status): WhatsAppTemplateStatus
    {
        return match (Str::upper((string) $status)) {
            'APPROVED' => WhatsAppTemplateStatus::Approved,
            'REJECTED', 'IN_APPEAL' => WhatsAppTemplateStatus::Rejected,
            'PAUSED' => WhatsAppTemplateStatus::Paused,
            'DISABLED', 'LIMIT_EXCEEDED' => WhatsAppTemplateStatus::Disabled,
            'DELETED', 'PENDING_DELETION' => WhatsAppTemplateStatus::Deleted,
            default => WhatsAppTemplateStatus::Pending,
        };
    }

    private function category(mixed $category): ?WhatsAppTemplateCategory
    {
        return match (Str::lower((string) $category)) {
            'marketing' => WhatsAppTemplateCategory::Marketing,
            'utility' => WhatsAppTemplateCategory::Utility,
            'authentication' => WhatsAppTemplateCategory::Authentication,
            default => null,
        };
    }

    private function scalar(mixed $value): ?string
    {
        return is_scalar($value) && filled($value) ? (string) $value : null;
    }

    /**
     * @param  Collection<string, WhatsAppTemplateComponent>  $existingMedia
     * @param  Collection<string, array<int, string>>  $existingDescriptions
     */
    private function createComponent(
        WhatsAppTemplate $template,
        array $componentData,
        ?int $cardIndex,
        int $position,
        Collection $existingMedia,
        Collection $existingDescriptions,
    ): void {
        $type = $this->componentType($componentData['type'] ?? null);
        if ($type === null) {
            return;
        }

        $example = is_array($componentData['example'] ?? null) ? $componentData['example'] : [];
        $mediaHandle = $this->scalar($componentData['media_handle'] ?? $this->componentMediaHandle($example));
        $mediaPath = $this->scalar($componentData['media_path'] ?? null);
        $existing = $existingMedia->get($this->mediaKey($cardIndex, $type));
        if ($type === 'header'
            && $mediaPath === null
            && $mediaHandle !== null
            && $existing?->media_handle === $mediaHandle) {
            $mediaPath = $existing->media_path;
        }

        $text = $type === 'limited_time_offer'
            ? data_get($componentData, 'limited_time_offer.text', $componentData['text'] ?? null)
            : ($componentData['text'] ?? null);

        $component = $template->components()->create([
            'card_index' => $cardIndex,
            'type' => $type,
            'format' => $this->format($componentData['format'] ?? null),
            'text' => $this->scalar($text),
            'media_path' => $mediaPath,
            'media_handle' => $mediaHandle,
            'settings' => $this->componentSettings($type, $componentData),
            'position' => $position,
        ]);

        if ($type === 'buttons') {
            $this->replaceButtons($component, $componentData['buttons'] ?? []);

            return;
        }

        $this->replaceVariables($component, $componentData, $existingDescriptions->get($this->mediaKey($cardIndex, $type), []));
    }

    private function mediaKey(?int $cardIndex, string $type): string
    {
        return ($cardIndex === null ? 'template' : 'card-'.$cardIndex).':'.$type;
    }

    /** @return array<string, bool|int>|null */
    private function componentSettings(string $type, array $componentData): ?array
    {
        $settings = match ($type) {
            'body' => array_key_exists('add_security_recommendation', $componentData)
                ? ['add_security_recommendation' => filter_var($componentData['add_security_recommendation'], FILTER_VALIDATE_BOOL)]
                : [],
            'footer' => filled($componentData['code_expiration_minutes'] ?? null)
                ? ['code_expiration_minutes' => (int) $componentData['code_expiration_minutes']]
                : [],
            'limited_time_offer' => [
                'has_expiration' => filter_var(
                    data_get($componentData, 'limited_time_offer.has_expiration', $componentData['has_expiration'] ?? false),
                    FILTER_VALIDATE_BOOL,
                ),
            ],
            default => [],
        };

        return $settings === [] ? null : $settings;
    }

    /** @return array<string, mixed> */
    private function otpSettings(array $buttonData): array
    {
        $supportedApps = collect(is_array($buttonData['supported_apps'] ?? null) ? $buttonData['supported_apps'] : [])
            ->filter(fn (mixed $app): bool => is_array($app) && filled($app['package_name'] ?? null))
            ->map(fn (array $app): array => [
                'package_name' => (string) $app['package_name'],
                'signature_hash' => (string) ($app['signature_hash'] ?? ''),
            ])
            ->values()
            ->all();
        $otpType = Str::lower((string) ($buttonData['otp_type'] ?? 'copy_code'));

        return array_filter([
            'otp_type' => $otpType,
            'autofill_text' => $this->scalar($buttonData['autofill_text'] ?? null),
            'supported_apps' => $supportedApps === [] ? null : $supportedApps,
            'zero_tap_terms_accepted' => $otpType === 'zero_tap' ? true : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    private function formatFromMeta(array $components, ?WhatsAppTemplateCategory $category): WhatsAppTemplateFormat
    {
        $types = collect($components)
            ->filter(fn (mixed $component): bool => is_array($component))
            ->map(fn (array $component): string => Str::upper((string) ($component['type'] ?? '')));
        $buttonTypes = collect($components)
            ->filter(fn (mixed $component): bool => is_array($component) && Str::upper((string) ($component['type'] ?? '')) === 'BUTTONS')
            ->flatMap(fn (array $component): array => is_array($component['buttons'] ?? null) ? $component['buttons'] : [])
            ->map(fn (mixed $button): string => is_array($button) ? Str::upper((string) ($button['type'] ?? '')) : '');

        return match (true) {
            $types->contains('CAROUSEL') => WhatsAppTemplateFormat::Carousel,
            $types->contains('LIMITED_TIME_OFFER') => WhatsAppTemplateFormat::LimitedTimeOffer,
            $types->contains('CALL_PERMISSION_REQUEST') => WhatsAppTemplateFormat::CallPermission,
            $category === WhatsAppTemplateCategory::Authentication => WhatsAppTemplateFormat::Authentication,
            $category === WhatsAppTemplateCategory::Marketing && $buttonTypes->contains('COPY_CODE') => WhatsAppTemplateFormat::Coupon,
            default => WhatsAppTemplateFormat::Standard,
        };
    }

    private function assertValidText(string $text, string $label, int $maxLength, bool $required): void
    {
        if ($required && blank($text)) {
            throw new RuntimeException('O '.$label.' do Template é obrigatório.');
        }

        $positions = $this->positions($text);
        if ($positions !== [] && $positions !== range(1, count($positions))) {
            throw new RuntimeException('As variáveis do '.$label.' precisam ser posicionais e sequenciais, começando em {{1}}.');
        }
        if (count($positions) > 10) {
            throw new RuntimeException('O '.$label.' pode ter no máximo 10 variáveis.');
        }
        if (mb_strlen($text) > $maxLength) {
            throw new RuntimeException('O '.$label.' do Template pode ter no máximo '.number_format($maxLength, 0, ',', '.').' caracteres.');
        }
    }

    /** @param array<int, array<string, mixed>> $buttons */
    private function assertValidButtons(array $buttons, int $maxButtons): void
    {
        if (count($buttons) > $maxButtons) {
            throw new RuntimeException($maxButtons === 3
                ? 'O Template pode ter no máximo três botões.'
                : 'O Template pode ter no máximo '.$maxButtons.' botões.');
        }
        if (count(array_filter($buttons, fn (array $button): bool => $button['type'] === 'COPY_CODE')) > 1) {
            throw new RuntimeException('O Template pode ter apenas um botão de copiar código.');
        }
        foreach ($buttons as $button) {
            if ($button['type'] !== 'COPY_CODE' && blank($button['text'] ?? null)) {
                throw new RuntimeException('Informe o texto de cada botão do Template.');
            }
            if ($button['type'] === 'URL' && blank($button['url'] ?? null)) {
                throw new RuntimeException('Informe o endereço do botão de URL.');
            }
            if ($button['type'] === 'PHONE_NUMBER' && blank($button['phone_number'] ?? null)) {
                throw new RuntimeException('Informe o telefone do botão de chamada.');
            }
            if ($button['type'] === 'COPY_CODE' && blank($button['example'] ?? null)) {
                throw new RuntimeException('Informe um código de exemplo para o botão de copiar código.');
            }
        }
    }

    private function assertValidCarousel(WhatsAppTemplate $template): void
    {
        if ($template->componentByType('header') !== null || $template->componentByType('footer') !== null) {
            throw new RuntimeException('O carrossel não aceita cabeçalho nem rodapé fora dos cards.');
        }

        $cards = $template->carouselCards();
        if (count($cards) < 2 || count($cards) > 10) {
            throw new RuntimeException('O carrossel precisa ter de 2 a 10 cards.');
        }

        $headerFormats = [];
        $withBody = [];
        $buttonTypes = [];
        foreach ($cards as $card) {
            $header = $card['header'] ?? null;
            if ($header === null || ! in_array($header->format, ['image', 'video'], true) || blank($header->media_handle)) {
                throw new RuntimeException('Envie a imagem ou o vídeo de cada card do carrossel.');
            }
            $headerFormats[] = $header->format;

            $body = $card['body'] ?? null;
            $withBody[] = $body !== null;
            if ($body !== null) {
                $this->assertValidText((string) $body->text, 'texto do card', 160, true);
                foreach ($body->variables as $variable) {
                    if (blank($variable->example)) {
                        throw new RuntimeException('Informe um exemplo para cada variável dos cards.');
                    }
                }
            }

            $buttons = $card['buttons'] ?? null;
            $definitions = $buttons === null ? [] : $buttons->buttons->map(fn ($button): array => array_filter([
                'type' => Str::upper($button->type),
                'text' => $button->text,
                'url' => $button->url,
                'phone_number' => $button->phone_number,
                'example' => $button->example,
            ], fn (mixed $value): bool => $value !== null))->values()->all();
            if ($definitions === []) {
                throw new RuntimeException('Cada card do carrossel precisa de pelo menos um botão.');
            }
            if (collect($definitions)->contains(fn (array $button): bool => ! in_array($button['type'], ['QUICK_REPLY', 'URL', 'PHONE_NUMBER'], true))) {
                throw new RuntimeException('Os cards aceitam apenas botões de resposta rápida, URL ou telefone.');
            }
            $this->assertValidButtons($definitions, 2);
            $buttonTypes[] = array_column($definitions, 'type');
        }

        if (count(array_unique($headerFormats)) > 1) {
            throw new RuntimeException('Todos os cards precisam usar o mesmo tipo de mídia.');
        }
        if (count(array_unique($withBody)) > 1) {
            throw new RuntimeException('Se um card tiver texto, todos os cards precisam ter texto.');
        }
        if (count(array_unique(array_map('serialize', $buttonTypes))) > 1) {
            throw new RuntimeException('Todos os cards precisam ter os mesmos botões, na mesma ordem.');
        }
    }

    private function assertValidCoupon(WhatsAppTemplate $template): void
    {
        $header = $template->componentByType('header');
        if ($header !== null && $header->format !== 'text') {
            throw new RuntimeException('O cupom aceita apenas cabeçalho de texto.');
        }
        if ($template->componentByType('footer') !== null) {
            throw new RuntimeException('O cupom não aceita rodapé.');
        }

        $buttons = $template->buttonDefinitions();
        $this->assertValidButtons($buttons, 2);
        if (count(array_filter($buttons, fn (array $button): bool => $button['type'] === 'COPY_CODE')) !== 1) {
            throw new RuntimeException('O cupom precisa de um botão de copiar código.');
        }
        if (collect($buttons)->contains(fn (array $button): bool => ! in_array($button['type'], ['COPY_CODE', 'QUICK_REPLY'], true))) {
            throw new RuntimeException('O cupom aceita apenas o botão de copiar código e uma resposta rápida.');
        }
    }

    private function assertValidLimitedTimeOffer(WhatsAppTemplate $template): void
    {
        $offer = $template->componentByType('limited_time_offer');
        if ($offer === null || blank($offer->text)) {
            throw new RuntimeException('Informe o título da oferta.');
        }
        if (mb_strlen((string) $offer->text) > 16) {
            throw new RuntimeException('O título da oferta pode ter no máximo 16 caracteres.');
        }

        $header = $template->componentByType('header');
        if ($header !== null && ! in_array($header->format, ['image', 'video'], true)) {
            throw new RuntimeException('A oferta aceita apenas cabeçalho de imagem ou vídeo.');
        }
        if ($template->componentByType('footer') !== null) {
            throw new RuntimeException('A oferta por tempo limitado não aceita rodapé.');
        }

        $buttons = $template->buttonDefinitions();
        $this->assertValidButtons($buttons, 2);
        if (array_column($buttons, 'type') !== ['COPY_CODE', 'URL']) {
            throw new RuntimeException('A oferta precisa do botão de copiar código e de um botão de URL.');
        }
    }

    private function assertValidCallPermission(WhatsAppTemplate $template): void
    {
        if ($template->componentByType('call_permission_request') === null) {
            throw new RuntimeException('O pedido de permissão de ligação está faltando no Template.');
        }

        foreach (['header', 'footer', 'buttons'] as $type) {
            if ($template->componentByType($type) !== null) {
                throw new RuntimeException('O pedido de ligação aceita apenas o corpo da mensagem.');
            }
        }
    }

    private function assertValidAuthentication(WhatsAppTemplate $template): void
    {
        $buttons = $template->buttonDefinitions();
        $otpButtons = array_values(array_filter($buttons, fn (array $button): bool => $button['type'] === 'OTP'));
        if (count($buttons) !== 1 || count($otpButtons) !== 1) {
            throw new RuntimeException('O Template de autenticação precisa de um único botão de código.');
        }

        $otpType = $otpButtons[0]['otp_type'] ?? null;
        if (! in_array($otpType, ['copy_code', 'one_tap', 'zero_tap'], true)) {
            throw new RuntimeException('Escolha como o Contato vai usar o código.');
        }
        if ($otpType !== 'copy_code') {
            $apps = $otpButtons[0]['supported_apps'] ?? [];
            if ($apps === [] || collect($apps)->contains(fn (array $app): bool => blank($app['package_name'] ?? null) || blank($app['signature_hash'] ?? null))) {
                throw new RuntimeException('Informe o pacote e o hash da assinatura do app Android.');
            }
        }

        $minutes = $template->componentByType('footer')?->settings['code_expiration_minutes'] ?? null;
        if ($minutes !== null && ($minutes < 1 || $minutes > 90)) {
            throw new RuntimeException('A validade do código precisa ficar entre 1 e 90 minutos.');
        }
    }

    private function recordChange(
        WhatsAppTemplate $template,
        string $event,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $oldCategory = null,
        ?string $newCategory = null,
        ?string $reason = null,
        ?string $oldQualityRating = null,
        ?string $newQualityRating = null,
    ): void {
        WhatsAppTemplateHistory::create([
            'whatsapp_template_id' => $template->getKey(),
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_category' => $oldCategory,
            'new_category' => $newCategory,
            'old_quality_rating' => $oldQualityRating,
            'new_quality_rating' => $newQualityRating,
            'reason' => $reason,
            'explanation' => $this->explainRejection($reason),
            'created_at' => now(),
        ]);
    }
}
