<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['team_id', 'shop_id', 'contact_id', 'agent_id', 'channel_id', 'token_hash'])]
#[Hidden(['token_hash'])]
class PurchaseAttribution extends Model
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

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
