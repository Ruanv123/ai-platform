<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'whatsapp_template_component_id',
    'position',
    'example',
    'description',
])]
class WhatsAppTemplateVariable extends Model
{
    protected $table = 'whatsapp_template_variables';

    public function component(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplateComponent::class, 'whatsapp_template_component_id');
    }
}
