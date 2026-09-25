<?php

namespace App\WhatsApp;

use App\Enums\WhatsAppTemplateCategory;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateComponent;
use App\Models\WhatsAppTemplateHistory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class WhatsAppTemplateService
{
    public function submit(WhatsAppTemplate $template): WhatsAppTemplate
    {
        $account = $template->account;
        $this->assertValidDraft($template);

        $response = $this->request($account)->post(
            $this->templatesUrl($account),
            $this->metaPayload($template),
        );
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

    public function replaceComponents(WhatsAppTemplate $template, array $components): void
    {
        DB::transaction(function () use ($template, $components): void {
            $template->components()->delete();

            foreach (array_values($components) as $position => $componentData) {
                if (! is_array($componentData)) {
                    continue;
                }

                $type = $this->componentType($componentData['type'] ?? null);
                if ($type === null) {
                    continue;
                }

                $example = is_array($componentData['example'] ?? null) ? $componentData['example'] : [];
                $component = $template->components()->create([
                    'type' => $type,
                    'format' => $this->format($componentData['format'] ?? null),
                    'text' => $this->scalar($componentData['text'] ?? null),
                    'media_path' => null,
                    'media_handle' => $this->componentMediaHandle($example),
                    'position' => $position,
                ]);

                if ($type === 'buttons') {
                    $this->replaceButtons($component, $componentData['buttons'] ?? []);

                    continue;
                }

                $this->replaceVariables($component, $componentData);
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

        return [
            'name' => $template->name,
            'language' => $template->language,
            'category' => Str::upper($template->requested_category->value),
            'components' => $template->components
                ->map(fn (WhatsAppTemplateComponent $component): array => $this->metaComponent($component))
                ->values()
                ->all(),
        ];
    }

    public function assertValidDraft(WhatsAppTemplate $template): void
    {
        if (! preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $template->name)) {
            throw new RuntimeException('O nome precisa usar apenas letras minúsculas, números e sublinhados.');
        }

        $body = $template->bodyText();
        if (blank($body)) {
            throw new RuntimeException('O corpo do Template é obrigatório.');
        }

        $positions = $this->positions($body);
        if ($positions !== [] && $positions !== range(1, count($positions))) {
            throw new RuntimeException('As variáveis do corpo precisam ser posicionais e sequenciais, começando em {{1}}.');
        }
        if (count($positions) > 10) {
            throw new RuntimeException('O corpo pode ter no máximo 10 variáveis.');
        }
        if (mb_strlen($body) > 1024) {
            throw new RuntimeException('O corpo do Template pode ter no máximo 1.024 caracteres.');
        }

        foreach ($template->variableDefinitions() as $variable) {
            if (blank($variable['example'] ?? null)) {
                throw new RuntimeException('Informe um exemplo para cada variável do Template.');
            }
        }

        $buttons = $template->buttonDefinitions();
        if (count($buttons) > 3) {
            throw new RuntimeException('O Template pode ter no máximo três botões.');
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

    public function explainRejection(?string $reason): ?string
    {
        return match (Str::upper(trim((string) $reason))) {
            'ABUSIVE_CONTENT',
            'SCAM' => 'A Meta identificou conteúdo abusivo ou enganoso. Remova promessas, ameaças e linguagem que possa induzir o Contato ao erro.',
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
                'buttons' => $component->buttons->map(fn ($button): array => $this->metaButton($button->type, $button->text, $button->url, $button->phone_number, $button->example))
                    ->values()
                    ->all(),
            ];
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

        return $metaComponent;
    }

    private function metaButton(string $type, ?string $text, ?string $url, ?string $phoneNumber, ?string $example): array
    {
        $metaButton = ['type' => match ($type) {
            'quick_reply' => 'QUICK_REPLY',
            'url' => 'URL',
            'phone_number' => 'PHONE_NUMBER',
            'copy_code' => 'COPY_CODE',
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

    private function replaceVariables(WhatsAppTemplateComponent $component, array $componentData): void
    {
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

    private function recordChange(
        WhatsAppTemplate $template,
        string $event,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $oldCategory = null,
        ?string $newCategory = null,
        ?string $reason = null,
    ): void {
        WhatsAppTemplateHistory::create([
            'whatsapp_template_id' => $template->getKey(),
            'event' => $event,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_category' => $oldCategory,
            'new_category' => $newCategory,
            'reason' => $reason,
            'explanation' => $this->explainRejection($reason),
            'created_at' => now(),
        ]);
    }
}
