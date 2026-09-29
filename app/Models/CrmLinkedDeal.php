<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $crm_contact_link_id
 * @property string $integration_id
 * @property string $external_deal_id
 * @property string $pipeline_id
 * @property string $stage_id
 * @property string $status
 * @property float|null $value
 * @property string|null $owner_id
 * @property Carbon|null $provider_updated_at
 */
#[Fillable([
    'team_id',
    'crm_contact_link_id',
    'integration_id',
    'external_deal_id',
    'pipeline_id',
    'stage_id',
    'status',
    'value',
    'owner_id',
    'provider_updated_at',
])]
class CrmLinkedDeal extends Model
{
    use BelongsToTeam;

    /** @return BelongsTo<CrmContactLink, $this> */
    public function contactLink(): BelongsTo
    {
        return $this->belongsTo(CrmContactLink::class, 'crm_contact_link_id');
    }

    /** @return BelongsTo<Integration, $this> */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'provider_updated_at' => 'immutable_datetime',
        ];
    }
}
