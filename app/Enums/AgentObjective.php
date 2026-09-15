<?php

namespace App\Enums;

enum AgentObjective: string
{
    case Support = 'support';
    case Sales = 'sales';
    case Qualification = 'qualification';
    case Scheduling = 'scheduling';

    /**
     * Get the display label for the objective.
     */
    public function label(): string
    {
        return match ($this) {
            self::Support => 'Atendimento / Suporte',
            self::Sales => 'Vendas',
            self::Qualification => 'Qualificação / SDR',
            self::Scheduling => 'Agendamento',
        };
    }

    /**
     * Get all objectives with their values and display labels.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $objective): array => [
                'value' => $objective->value,
                'label' => $objective->label(),
            ],
            self::cases(),
        );
    }
}
