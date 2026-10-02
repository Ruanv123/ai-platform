<?php

namespace App\Enums;

enum CrmProvider: string
{
    case RdStation = 'rd_station';
    case HubSpot = 'hubspot';
    case Pipedrive = 'pipedrive';
    case Kommo = 'kommo';
    case Salesforce = 'salesforce';
    case Ploomes = 'ploomes';

    /**
     * Get the provider's display name.
     */
    public function name(): string
    {
        return match ($this) {
            self::RdStation => 'RD Station CRM',
            self::HubSpot => 'HubSpot',
            self::Pipedrive => 'Pipedrive',
            self::Kommo => 'Kommo',
            self::Salesforce => 'Salesforce',
            self::Ploomes => 'Ploomes',
        };
    }

    /**
     * Get the provider's short logo mark.
     */
    public function logo(): string
    {
        return match ($this) {
            self::RdStation => 'RD',
            self::HubSpot => 'HS',
            self::Pipedrive => 'PD',
            self::Kommo => 'KO',
            self::Salesforce => 'SF',
            self::Ploomes => 'PL',
        };
    }

    /**
     * Get the provider's catalog description.
     */
    public function description(): string
    {
        return match ($this) {
            self::RdStation => 'Conecte sua conta pela autorização oficial do RD Station CRM.',
            default => 'Em breve.',
        };
    }

    /**
     * Get the capabilities planned for the integration.
     *
     * @return array<int, string>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::RdStation => ['Carregar metadados do CRM'],
            default => [],
        };
    }

    /**
     * Get all CRM providers prepared for the integrations catalog.
     *
     * @return array<int, array{value: string, name: string, description: string, logo: string, capabilities: array<int, string>}>
     */
    public static function catalog(): array
    {
        return array_map(
            fn (self $provider): array => [
                'value' => $provider->value,
                'name' => $provider->name(),
                'description' => $provider->description(),
                'logo' => $provider->logo(),
                'capabilities' => $provider->capabilities(),
            ],
            self::cases(),
        );
    }
}
