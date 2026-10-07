<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\SiteReadProposalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'site_read_id',
    'proposed_fields',
    'status',
])]
class SiteReadProposal extends Model
{
    use BelongsToTeam, HasFactory;

    public function siteRead(): BelongsTo
    {
        return $this->belongsTo(SiteRead::class);
    }

    protected function casts(): array
    {
        return [
            'proposed_fields' => 'array',
            'status' => SiteReadProposalStatus::class,
        ];
    }
}
