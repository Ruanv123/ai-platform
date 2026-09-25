<?php

namespace App\WhatsApp;

use App\Enums\WhatsAppTemplateCategory;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateHistory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class WhatsAppTemplateService
{
    /**
     * Submit a local draft to Meta. No local state is changed before the
     * provider confirms the request, so a connection failure keeps the draft.
     */
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
                'meta_template_id' => $this->scalar($response->json('id')) ??
                    $template->meta_template_id,
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

    /**
     * Apply one template received from Meta, preserving a local draft when it
     * has not been submitted yet.
     *
     * @param  array<string, mixed>  $remote
     */
    public function upsertFromMeta(
        WhatsAppAccount $account,
        array $remote,
    ): WhatsAppTemplate {
        $name = (string) ($remote['name'] ?? '');
        $language = (string) ($remote['language'] ?? 'pt_BR');
        $metaId = $this->scalar($remote['id']);
        $template = WhatsAppTemplate::withoutGlobalScopes()
            ->where('whatsapp_account_id', $account->getKey())
            ->when(
                $metaId !== null,
                fn ($query) => $query->where('meta_template_id', $metaId),
            )
            ->when(
                $metaId === null,
                fn ($query) => $query
                    ->where('name', $name)
                    ->where('language', $language),
            )
            ->first();

        $components = is_array($remote['components'] ?? null)
            ? $remote['components']
            : [];
        $oldStatus = $template?->status?->value;
        $oldCategory = $template?->assigned_category?->value;
        $status = $this->status($remote['status'] ?? 'PENDING');
        $assignedCategory = $this->category(
            $remote['category'] ?? ($remote['correct_category'] ?? null),
        );
        $requestedCategory =
            ($template === null ? null : $template->requested_category) ??
            ($assignedCategory ?? WhatsAppTemplateCategory::Utility);
        $rejectionReason = $this->scalar(
            $remote['rejected_reason'] ?? ($remote['rejection_reason'] ?? null),
        );
        $attributes = [
            'whatsapp_account_id' => $account->getKey(),
            'meta_template_id' => $metaId,
            'name' => $name,
            'language' => $language,
            'status' => $status,
            'requested_category' => $requestedCategory,
            'assigned_category' => $assignedCategory,
            'body' => $this->componentText($components, 'BODY'),
            'header_type' => $this->component($components, 'HEADER')['format'] ?? null,
            'header_text' => $this->component($components, 'HEADER')['text'] ?? null,
            'footer' => $this->component($components, 'FOOTER')['text'] ?? null,
            'buttons' => $this->component($components, 'BUTTONS')['buttons'] ?? [],
            'variables' => $this->variables($components),
            'rejection_reason' => $rejectionReason,
            'rejection_explanation' => $this->explainRejection(
                $rejectionReason,
            ),
            'last_synced_at' => now(),
            'submitted_at' => ($template === null ? null : $template->submitted_at) ?? now(),
        ];

        if ($template === null) {
            $template = WhatsAppTemplate::withoutGlobalScopes()->create(
                $attributes,
            );
            $this->recordChange(
                $template,
                'imported',
                null,
                $status->value,
                null,
                $assignedCategory?->value,
                $rejectionReason,
            );

            return $template;
        }

        $template->forceFill($attributes)->save();
        if ($oldStatus !== $status->value) {
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

        return $template;
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
        $this->recordChange(
            $template,
            'deleted',
            $oldStatus,
            WhatsAppTemplateStatus::Deleted->value,
        );
    }

    /** @return array<string, mixed> */
    public function metaPayload(WhatsAppTemplate $template): array
    {
        $components = [];
        if (filled($template->header_text)) {
            $components[] = array_filter([
                'type' => 'HEADER',
                'format' => Str::upper(
                    (string) ($template->header_type ?: 'TEXT'),
                ),
                'text' => $template->header_text,
            ]);
        }
        $components[] = ['type' => 'BODY', 'text' => $template->body];
        if (filled($template->footer)) {
            $components[] = ['type' => 'FOOTER', 'text' => $template->footer];
        }
        if ($template->buttons !== []) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $template->buttons,
            ];
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
            throw new RuntimeException(
                'O nome precisa usar apenas letras minúsculas, números e sublinhados.',
            );
        }
        if (blank($template->body)) {
            throw new RuntimeException('O corpo do Template é obrigatório.');
        }
        $positions = $this->bodyPositions($template->body);
        if ($positions !== [] && $positions !== range(1, count($positions))) {
            throw new RuntimeException(
                'As variáveis do corpo precisam ser posicionais e sequenciais, começando em {{1}}.',
            );
        }
        if (count($positions) > 10) {
            throw new RuntimeException(
                'O corpo pode ter no máximo 10 variáveis.',
            );
        }
        if (mb_strlen($template->body) > 1024) {
            throw new RuntimeException(
                'O corpo do Template pode ter no máximo 1.024 caracteres.',
            );
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

    private function request(WhatsAppAccount $account): PendingRequest
    {
        return Http::withToken($account->access_token)
            ->connectTimeout(3)
            ->timeout(15);
    }

    private function templatesUrl(WhatsAppAccount $account): string
    {
        return $this->graphBase().
            '/'.
            rawurlencode($account->waba_id).
            '/message_templates';
    }

    private function graphBase(): string
    {
        return rtrim(
            (string) config(
                'services.whatsapp.graph_api_url',
                'https://graph.facebook.com',
            ),
            '/',
        ).
            '/'.
            trim(
                (string) config('services.whatsapp.graph_api_version', 'v23.0'),
                '/',
            );
    }

    /** @param array<int, array<string, mixed>> $components */
    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, mixed>
     */
    private function component(array $components, string $type): array
    {
        foreach ($components as $component) {
            if (Str::upper((string) ($component['type'] ?? '')) === $type) {
                return $component;
            }
        }

        return [];
    }

    /** @param array<int, array<string, mixed>> $components */
    private function componentText(array $components, string $type): string
    {
        return (string) ($this->component($components, $type)['text'] ?? '');
    }

    /** @param array<int, array<string, mixed>> $components */
    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return list<array{position: int, component: string}>
     */
    private function variables(array $components): array
    {
        $variables = [];
        foreach ($components as $component) {
            $componentType = Str::lower((string) ($component['type'] ?? ''));
            preg_match_all(
                "/{{\s*(\d+)\s*}}/",
                (string) ($component['text'] ?? ''),
                $matches,
            );
            foreach (array_unique($matches[1]) as $position) {
                $variables[] = [
                    'position' => (int) $position,
                    'component' => $componentType,
                ];
            }
        }

        usort(
            $variables,
            static fn (array $left, array $right): int => $left['position'] <=>
                $right['position'],
        );

        return $variables;
    }

    /** @return list<int> */
    private function bodyPositions(string $body): array
    {
        preg_match_all("/{{\s*(\d+)\s*}}/", $body, $matches);

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
            'details' => [],
            'created_at' => now(),
        ]);
    }
}
