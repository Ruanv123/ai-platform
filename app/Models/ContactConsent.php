<?php

namespace App\Models;

use App\Enums\ConsentAction;
use App\Enums\ConsentPurpose;
use App\Models\Scopes\TeamScope;
use Database\Factories\ContactConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One immutable consent event. The current state is the latest event for a
 * Contact and purpose, not a mutable column.
 *
 * @property int $id
 * @property int $team_id
 * @property int $contact_id
 * @property ConsentPurpose $purpose
 * @property ConsentAction $action
 * @property string $source
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property-read Contact $contact
 * @property-read Team $team
 */
#[Fillable(['team_id', 'contact_id', 'purpose', 'action', 'source', 'metadata', 'created_at'])]
class ContactConsent extends Model
{
    /** @use HasFactory<ContactConsentFactory> */
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope(new TeamScope);

        static::updating(function (): never {
            throw new LogicException('O registro de Consentimento é append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('O registro de Consentimento não pode ser apagado.');
        });
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    protected function casts(): array
    {
        return [
            'purpose' => ConsentPurpose::class,
            'action' => ConsentAction::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
