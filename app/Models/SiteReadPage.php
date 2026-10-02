<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\SiteReadPageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'team_id',
    'site_read_id',
    'url',
    'title',
    'status',
    'error_message',
    'completed_at',
])]
class SiteReadPage extends Model
{
    use BelongsToTeam;

    public function siteRead(): BelongsTo
    {
        return $this->belongsTo(SiteRead::class);
    }

    public function document(): HasOne
    {
        return $this->hasOne(Document::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SiteReadPageStatus::class,
            'completed_at' => 'datetime',
        ];
    }
}
