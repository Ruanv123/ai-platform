<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'contact_id', 'integration_id', 'external_person_id'])]
class CrmContactLink extends Model
{
    use BelongsToTeam;

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    /** @return HasMany<CrmLinkedDeal, $this> */
    public function linkedDeals(): HasMany
    {
        return $this->hasMany(CrmLinkedDeal::class);
    }
}
