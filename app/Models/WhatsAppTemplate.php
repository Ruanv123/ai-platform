<?php

namespace App\Models;

use App\Enums\WhatsAppTemplateCategory;
use App\Enums\WhatsAppTemplateStatus;
use Database\Factories\WhatsAppTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $whatsapp_account_id
 * @property string|null $meta_template_id
 * @property string $name
 * @property string $language
 * @property WhatsAppTemplateStatus $status
 * @property WhatsAppTemplateCategory $requested_category
 * @property WhatsAppTemplateCategory|null $assigned_category
 * @property string $body
 * @property string|null $header_type
 * @property string|null $header_text
 * @property string|null $footer
 * @property array<int, array<string, mixed>>|null $buttons
 * @property array<int, array<string, mixed>> $variables
 * @property string|null $rejection_reason
 * @property string|null $rejection_explanation
 * @property Carbon|null $last_synced_at
 * @property Carbon|null $submitted_at
 * @property-read WhatsAppAccount $account
 * @property-read Collection<int, WhatsAppTemplateHistory> $history
 */
#[Fillable([
    'whatsapp_account_id',
    'meta_template_id',
    'name',
    'language',
    'status',
    'requested_category',
    'assigned_category',
    'body',
    'header_type',
    'header_text',
    'footer',
    'buttons',
    'variables',
    'rejection_reason',
    'rejection_explanation',
    'last_synced_at',
    'submitted_at',
])]
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    /** @use HasFactory<WhatsAppTemplateFactory> */
    use HasFactory;

    /** @return BelongsTo<WhatsAppAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    /** @return HasMany<WhatsAppTemplateHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateHistory::class, 'whatsapp_template_id')->latest('created_at');
    }

    public function isSendable(): bool
    {
        return $this->status->canSend();
    }

    /**
     * Return the component variables in their positional order.
     *
     * @return list<array<string, mixed>>
     */
    public function variableDefinitions(): array
    {
        $variables = array_values($this->variables ?? []);

        usort($variables, static fn (array $left, array $right): int => ((int) ($left['position'] ?? 0)) <=> ((int) ($right['position'] ?? 0))
        );

        return $variables;
    }

    protected function casts(): array
    {
        return [
            'status' => WhatsAppTemplateStatus::class,
            'requested_category' => WhatsAppTemplateCategory::class,
            'assigned_category' => WhatsAppTemplateCategory::class,
            'buttons' => 'array',
            'variables' => 'array',
            'last_synced_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
        ];
    }
}
