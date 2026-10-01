<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\SiteReadStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'team_id',
    'requested_by_id',
    'website',
    'resolved_website',
    'status',
    'found_pages_count',
    'error_message',
    'started_at',
    'completed_at',
])]
class SiteRead extends Model
{
    use BelongsToTeam;

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SiteReadPage::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SiteReadStatus::class,
            'found_pages_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
