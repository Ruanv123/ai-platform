<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $name
 * @property string $type
 * @property string|null $provider
 * @property array<string, mixed> $credentials
 * @property string $status
 * @property string|null $status_message
 * @property Carbon|null $last_tested_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'type', 'provider', 'credentials', 'status', 'status_message', 'last_tested_at', 'crm_metadata', 'crm_metadata_loaded_at', 'crm_singleton_key'])]
#[Hidden(['credentials'])]
class Integration extends Model
{
    /** @use HasFactory<IntegrationFactory> */
    use BelongsToTeam, HasFactory, HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Integration $integration): void {
            $integration->crm_singleton_key = $integration->type === 'crm' ? 'crm' : null;
        });
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function baseUrl(): ?string
    {
        $baseUrl = $this->credential('base_url');

        return is_string($baseUrl) && filled($baseUrl) ? rtrim($baseUrl, '/') : null;
    }

    /** @return HasMany<SchedulableCalendar, $this> */
    public function schedulableCalendars(): HasMany
    {
        return $this->hasMany(SchedulableCalendar::class);
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'last_tested_at' => 'immutable_datetime',
            'crm_metadata' => 'array',
            'crm_metadata_loaded_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
