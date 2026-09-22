<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\ContactFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property int $team_id
 * @property string $name
 * @property string $type
 * @property array<int, string>|null $options
 * @property bool $is_sensitive
 * @property-read Team $team
 * @property-read Collection<int, ContactFieldValue> $values
 */
#[Fillable(['team_id', 'name', 'type', 'options', 'is_sensitive'])]
class ContactField extends Model
{
    /** @use HasFactory<ContactFieldFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return HasMany<ContactFieldValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ContactFieldValue::class);
    }

    /** @return BelongsToMany<Contact, $this, Pivot, 'pivot'> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_field_values')
            ->withPivot('value');
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_sensitive' => 'boolean',
        ];
    }
}
