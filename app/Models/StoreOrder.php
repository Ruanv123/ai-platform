<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'team_id',
    'shop_id',
    'store_customer_id',
    'purchase_attribution_id',
    'customer_external_id',
    'external_id',
    'order_number',
    'total_amount',
    'currency_code',
    'financial_status',
    'fulfillment_status',
    'ordered_at',
    'last_import_operation_id',
])]
class StoreOrder extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function storeCustomer(): BelongsTo
    {
        return $this->belongsTo(StoreCustomer::class);
    }

    public function purchaseAttribution(): BelongsTo
    {
        return $this->belongsTo(PurchaseAttribution::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'ordered_at' => 'immutable_datetime',
        ];
    }
}
