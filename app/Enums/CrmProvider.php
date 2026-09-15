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
            self::RdStation => 'Centralize contatos, negócios e atividades do seu time comercial.',
            self::HubSpot => 'Conecte marketing, vendas e atendimento em uma visão única do cliente.',
            self::Pipedrive => 'Mantenha seu funil comercial atualizado a cada conversa com seus clientes.',
            self::Kommo => 'Organize relacionamentos e acompanhe oportunidades em um CRM conversacional.',
            self::Salesforce => 'Leve os dados dos atendimentos para a plataforma CRM mais completa do mercado.',
            self::Ploomes => 'Automatize o registro de oportunidades e atividades no CRM feito para empresas brasileiras.',
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
            self::RdStation => [
                'Criar e atualizar contatos',
                'Registrar oportunidades no funil',
                'Sincronizar atividades de atendimento',
            ],
            self::HubSpot => [
                'Criar e atualizar contatos',
                'Registrar negócios e atividades',
                'Consultar o histórico do cliente',
            ],
            self::Pipedrive => [
                'Criar pessoas e organizações',
                'Criar e mover negócios no funil',
                'Adicionar notas e atividades',
            ],
            self::Kommo => [
                'Criar e atualizar leads',
                'Registrar conversas no histórico',
                'Mover oportunidades entre etapas',
            ],
            self::Salesforce => [
                'Criar e atualizar leads e contatos',
                'Registrar oportunidades e tarefas',
                'Consultar dados do cliente',
            ],
            self::Ploomes => [
                'Criar e atualizar clientes',
                'Registrar oportunidades e tarefas',
                'Sincronizar etapas do funil',
            ],
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
