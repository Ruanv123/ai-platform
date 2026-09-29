<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'team_id',
    'integration_id',
    'domain',
    'name',
    'currency_code',
    'country_code',
    'timezone',
    'sync_status',
    'sync_progress',
    'last_synced_at',
    'sync_error',
    'suggested_action',
    'reconciliation_cursor',
])]
class Shop extends Model
{
    use BelongsToTeam, HasFactory, HasUuids, SoftDeletes;

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    protected function casts(): array
    {
        return [
            'sync_progress' => 'integer',
            'last_synced_at' => 'immutable_datetime',
            'reconciliation_cursor' => 'immutable_datetime',
        ];
    }
}
