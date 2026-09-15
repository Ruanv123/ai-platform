<?php

namespace App\Enums;

enum TeamSegment: string
{
    case Services = 'servicos';
    case Retail = 'varejo';
    case Industry = 'industria';
    case Technology = 'tecnologia';
    case Healthcare = 'saude';
    case Education = 'educacao';
    case Other = 'outro';

    /**
     * Get the display label for the segment.
     */
    public function label(): string
    {
        return match ($this) {
            self::Services => 'Serviços',
            self::Retail => 'Varejo',
            self::Industry => 'Indústria',
            self::Technology => 'Tecnologia',
            self::Healthcare => 'Saúde',
            self::Education => 'Educação',
            self::Other => 'Outro',
        };
    }

    /**
     * Get the options for a segment select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $segment): array => ['value' => $segment->value, 'label' => $segment->label()],
            self::cases(),
        );
    }
}
