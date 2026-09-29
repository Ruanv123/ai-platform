<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'team_id',
    'shop_id',
    'external_id',
    'title',
    'description',
    'handle',
    'url',
    'status',
    'online_store_published',
    'embedding',
    'source_hash',
    'last_import_operation_id',
])]
class Product extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)
            ->withPivot(['team_id', 'last_import_operation_id'])
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'online_store_published' => 'boolean',
            'embedding' => AsVector::class,
        ];
    }
}
