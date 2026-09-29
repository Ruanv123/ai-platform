<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'shop_id',
    'product_id',
    'external_id',
    'sku',
    'title',
    'options',
    'price',
    'compare_at_price',
    'inventory_quantity',
    'continues_selling_when_out_of_stock',
    'available',
    'last_import_operation_id',
])]
class ProductVariant extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'inventory_quantity' => 'integer',
            'continues_selling_when_out_of_stock' => 'boolean',
            'available' => 'boolean',
        ];
    }
}
