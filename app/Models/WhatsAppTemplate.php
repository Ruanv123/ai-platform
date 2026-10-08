<?php

namespace App\Models;

use App\Enums\WhatsAppTemplateCategory;
use App\Enums\WhatsAppTemplateFormat;
use App\Enums\WhatsAppTemplateStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'whatsapp_account_id',
    'meta_template_id',
    'name',
    'language',
    'format',
    'library_key',
    'status',
    'requested_category',
    'assigned_category',
    'quality_rating',
    'rejection_reason',
    'rejection_explanation',
    'last_synced_at',
    'submitted_at',
])]
class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $attributes = [
        'format' => 'standard',
    ];

    use HasFactory;

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateComponent::class, 'whatsapp_template_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    /** @return BelongsToMany<Agent, $this> */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_whatsapp_template', 'whatsapp_template_id', 'agent_id')
            ->withPivot('usage')
            ->withTimestamps();
    }

    public function history(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateHistory::class, 'whatsapp_template_id')->latest('created_at');
    }

    public function componentByType(string $type): ?WhatsAppTemplateComponent
    {
        return $this->topLevelComponents()->firstWhere('type', Str::lower($type));
    }

    /** @return Collection<int, WhatsAppTemplateComponent> */
    public function topLevelComponents(): Collection
    {
        $this->loadMissing('components');

        return $this->components->whereNull('card_index')->values();
    }

    /** @return array<int, array<string, WhatsAppTemplateComponent>> */
    public function carouselCards(): array
    {
        $this->loadMissing('components');
        $cards = [];

        foreach ($this->components->whereNotNull('card_index')->sortBy('card_index') as $component) {
            $cards[(int) $component->card_index][$component->type] = $component;
        }

        return array_values($cards);
    }

    public function bodyText(): string
    {
        return $this->componentByType('body')?->text ?? '';
    }

    public function headerType(): ?string
    {
        $format = $this->componentByType('header')?->format;

        return $format === null ? null : Str::upper($format);
    }

    public function headerText(): ?string
    {
        return $this->componentByType('header')?->text;
    }

    public function footerText(): ?string
    {
        return $this->componentByType('footer')?->text;
    }

    public function buttonDefinitions(): array
    {
        $component = $this->componentByType('buttons');
        if ($component === null) {
            return [];
        }

        $component->loadMissing('buttons');

        return $component->buttons->map(fn (WhatsAppTemplateButton $button): array => array_filter([
            'type' => match ($button->type) {
                'quick_reply' => 'QUICK_REPLY',
                'url' => 'URL',
                'phone_number' => 'PHONE_NUMBER',
                'copy_code' => 'COPY_CODE',
                'otp' => 'OTP',
                default => Str::upper($button->type),
            },
            'text' => $button->text,
            'url' => $button->url,
            'phone_number' => $button->phone_number,
            'example' => $button->example,
            ...($button->settings ?? []),
        ], fn (mixed $value): bool => $value !== null))->values()->all();
    }

    public function variableDefinitions(): array
    {
        $this->loadMissing('components.variables', 'components.buttons');
        $definitions = [];

        foreach ($this->topLevelComponents() as $component) {
            if ($component->type !== 'buttons') {
                foreach ($component->variables as $variable) {
                    $definitions[] = array_filter([
                        'position' => (int) $variable->position,
                        'component' => $component->type,
                        'example' => $variable->example,
                        'description' => $variable->description,
                    ], fn (mixed $value, string $key): bool => $key !== 'description' || $value !== null, ARRAY_FILTER_USE_BOTH);
                }

                continue;
            }

            $usedVariables = [];
            foreach ($component->buttons as $button) {
                if ($button->type !== 'url' || blank($button->url)) {
                    continue;
                }

                preg_match_all('/{{\s*(\d+)\s*}}/', $button->url, $matches);
                foreach (array_unique($matches[1]) as $position) {
                    $variable = $component->variables->first(fn (WhatsAppTemplateVariable $candidate): bool => (int) $candidate->position === (int) $position && ! isset($usedVariables[$candidate->getKey()])
                    );
                    if ($variable !== null) {
                        $usedVariables[$variable->getKey()] = true;
                    }

                    $definitions[] = [
                        'position' => (int) $position,
                        'component' => 'button',
                        'buttonIndex' => (int) $button->position,
                        'example' => $variable?->example ?? $button->example,
                    ];
                }
            }
        }

        return $definitions;
    }

    public function isSendable(): bool
    {
        return $this->status->canSend();
    }

    protected function casts(): array
    {
        return [
            'format' => WhatsAppTemplateFormat::class,
            'status' => WhatsAppTemplateStatus::class,
            'requested_category' => WhatsAppTemplateCategory::class,
            'assigned_category' => WhatsAppTemplateCategory::class,
            'last_synced_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
        ];
    }
}
