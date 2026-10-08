<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'whatsapp_template_component_id',
    'type',
    'text',
    'url',
    'phone_number',
    'example',
    'settings',
    'position',
])]
class WhatsAppTemplateButton extends Model
{
    protected $table = 'whatsapp_template_buttons';

    public function component(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplateComponent::class, 'whatsapp_template_component_id');
    }

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }
}
