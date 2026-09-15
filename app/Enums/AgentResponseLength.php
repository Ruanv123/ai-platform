<?php

namespace App\Enums;

enum AgentResponseLength: string
{
    case Short = 'short';
    case Medium = 'medium';

    /**
     * Get the display label for the response length.
     */
    public function label(): string
    {
        return match ($this) {
            self::Short => 'Curtas',
            self::Medium => 'Médias',
        };
    }

    /**
     * Get all response lengths with their values and display labels.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $length): array => [
                'value' => $length->value,
                'label' => $length->label(),
            ],
            self::cases(),
        );
    }
}
