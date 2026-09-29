<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['team_id', 'shop_id', 'external_id', 'title'])]
class Category extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['team_id', 'last_import_operation_id'])
            ->withTimestamps();
    }
}
