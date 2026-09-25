<?php

namespace App\Models;

use Database\Factories\WhatsAppTemplateHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $whatsapp_template_id
 * @property string $event
 * @property string|null $old_status
 * @property string|null $new_status
 * @property string|null $old_category
 * @property string|null $new_category
 * @property string|null $reason
 * @property string|null $explanation
 * @property Carbon $created_at
 * @property-read WhatsAppTemplate $template
 */
#[Fillable([
    'whatsapp_template_id',
    'event',
    'old_status',
    'new_status',
    'old_category',
    'new_category',
    'reason',
    'explanation',
    'created_at',
])]
class WhatsAppTemplateHistory extends Model
{
    protected $table = 'whatsapp_template_histories';

    /** @use HasFactory<WhatsAppTemplateHistoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('O histórico de Template é append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('O histórico de Template não pode ser apagado.');
        });
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
