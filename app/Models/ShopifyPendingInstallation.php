<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'domain',
    'shop_name',
    'currency_code',
    'country_code',
    'timezone',
    'credentials',
    'handoff_token_hash',
    'expires_at',
    'handoff_started_at',
    'consumed_at',
])]
#[Hidden(['credentials', 'handoff_token_hash'])]
class ShopifyPendingInstallation extends Model
{
    use HasFactory, HasUuids;

    public function isActive(): bool
    {
        return $this->consumed_at === null
            && is_string($this->handoff_token_hash)
            && $this->expires_at?->isFuture() === true;
    }

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'expires_at' => 'immutable_datetime',
            'handoff_started_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }
}
