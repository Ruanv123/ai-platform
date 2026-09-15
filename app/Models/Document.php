<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $collection_id
 * @property DocumentType $type
 * @property DocumentStatus $status
 * @property string $title
 * @property string $content
 * @property array<int, array{question: string, answer: string}>|null $faq_items
 * @property string|null $error_message
 * @property int $size_bytes
 * @property string|null $ingestion_token
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection $collection
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Chunk> $chunks
 */
#[Fillable([
    'team_id',
    'collection_id',
    'type',
    'status',
    'title',
    'content',
    'faq_items',
    'error_message',
    'size_bytes',
    'ingestion_token',
    'processed_at',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the Empresa that owns the document.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the collection that contains the document.
     *
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * Get the indexed chunks for the document.
     *
     * @return HasMany<Chunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class)->orderBy('position');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'faq_items' => 'array',
            'processed_at' => 'datetime',
            'size_bytes' => 'integer',
        ];
    }
}
