<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property int $team_id
 * @property string $name
 * @property-read Team $team
 * @property-read Collection<int, Contact> $contacts
 */
#[Fillable(['team_id', 'name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToTeam, HasFactory, HasUuids;

    /** @return BelongsToMany<Contact, $this, Pivot, 'pivot'> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_tag');
    }
}
