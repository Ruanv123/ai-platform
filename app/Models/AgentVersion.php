<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\AgentObjective;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['team_id', 'agent_id', 'number', 'objective', 'ecommerce_tools', 'crm_tools', 'published_at'])]
class AgentVersion extends Model
{
    use BelongsToTeam, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Uma Versão publicada do Agente é imutável.');
        });
    }

    public function shouldSearchProducts(bool $shopConnected, bool $shopToolsEnabled = true): bool
    {
        $toolSettings = $this->getAttribute('ecommerce_tools');
        $searchProducts = is_array($toolSettings)
            ? ($toolSettings['search_products'] ?? $this->objectiveValue() === AgentObjective::Sales)
            : $this->objectiveValue() === AgentObjective::Sales;

        return $shopConnected && $shopToolsEnabled && (bool) $searchProducts;
    }

    public function shouldSendPurchaseLink(bool $shopConnected, bool $shopToolsEnabled = true): bool
    {
        $toolSettings = $this->getAttribute('ecommerce_tools');
        $sendPurchaseLink = is_array($toolSettings)
            ? ($toolSettings['send_purchase_link'] ?? $this->objectiveValue() === AgentObjective::Sales)
            : $this->objectiveValue() === AgentObjective::Sales;

        return $shopConnected && $shopToolsEnabled && (bool) $sendPurchaseLink;
    }

    public function objectiveValue(): AgentObjective
    {
        $objective = $this->getAttribute('objective');

        return $objective instanceof AgentObjective
            ? $objective
            : AgentObjective::from((string) $objective);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    protected function casts(): array
    {
        return [
            'objective' => AgentObjective::class,
            'ecommerce_tools' => 'array',
            'crm_tools' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }
}
