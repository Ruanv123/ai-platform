<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'integration_id', 'contact_field_id', 'crm_field_id', 'direction', 'option_mappings'])]
class CrmFieldMapping extends Model
{
    use BelongsToTeam, HasFactory;

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function contactField(): BelongsTo
    {
        return $this->belongsTo(ContactField::class);
    }

    protected function casts(): array
    {
        return ['option_mappings' => 'array'];
    }
}
