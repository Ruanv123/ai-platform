<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'collection_id', 'url'])]
class SiteCollectionIgnoredUrl extends Model
{
    use BelongsToTeam;

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }
}
