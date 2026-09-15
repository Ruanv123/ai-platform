<?php

namespace App\Enums;

enum AgentTone: string
{
    case Formal = 'formal';
    case Friendly = 'friendly';
    case Casual = 'casual';

    /**
     * Get the display label for the tone.
     */
    public function label(): string
    {
        return match ($this) {
            self::Formal => 'Formal',
            self::Friendly => 'Amigável',
            self::Casual => 'Descontraído',
        };
    }

    /**
     * Get all tones with their values and display labels.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $tone): array => [
                'value' => $tone->value,
                'label' => $tone->label(),
            ],
            self::cases(),
        );
    }
}
