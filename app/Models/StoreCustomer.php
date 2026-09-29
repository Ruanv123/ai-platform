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
    'contact_id',
    'external_id',
    'first_name',
    'last_name',
    'email',
    'phone_e164',
    'total_spent',
    'currency_code',
    'last_import_operation_id',
])]
class StoreCustomer extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function displayName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->last_name])));
    }

    protected function casts(): array
    {
        return [
            'total_spent' => 'decimal:2',
        ];
    }
}
