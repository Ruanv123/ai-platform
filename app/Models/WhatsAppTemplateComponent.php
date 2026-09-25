<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'whatsapp_template_id',
    'type',
    'format',
    'text',
    'media_path',
    'media_handle',
    'position',
])]
class WhatsAppTemplateComponent extends Model
{
    protected $table = 'whatsapp_template_components';

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateVariable::class, 'whatsapp_template_component_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function buttons(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateButton::class, 'whatsapp_template_component_id')
            ->orderBy('position')
            ->orderBy('id');
    }
}
