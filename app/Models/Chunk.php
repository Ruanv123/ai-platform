<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\ChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $document_id
 * @property int $team_id
 * @property int $collection_id
 * @property int $position
 * @property string $content
 * @property array<float> $embedding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 * @property-read Team $team
 * @property-read Collection $collection
 */
#[Fillable(['document_id', 'team_id', 'collection_id', 'position', 'content', 'embedding'])]
class Chunk extends Model
{
    /** @use HasFactory<ChunkFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the document that contains the chunk.
     *
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the Empresa that owns the chunk.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the collection that contains the chunk.
     *
     * @return BelongsTo<Collection, $this>
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['embedding' => AsVector::class];
    }
}
