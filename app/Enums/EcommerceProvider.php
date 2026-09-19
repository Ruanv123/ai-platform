<?php

namespace App\Enums;

enum EcommerceProvider: string
{
    case Shopify = 'shopify';
    case Nuvemshop = 'nuvemshop';
    case WooCommerce = 'woocommerce';
    case Vtex = 'vtex';
    case LojaIntegrada = 'loja_integrada';
    case MercadoLivre = 'mercado_livre';

    /**
     * Get the provider's display name.
     */
    public function name(): string
    {
        return match ($this) {
            self::Shopify => 'Shopify',
            self::Nuvemshop => 'Nuvemshop',
            self::WooCommerce => 'WooCommerce',
            self::Vtex => 'VTEX',
            self::LojaIntegrada => 'Loja Integrada',
            self::MercadoLivre => 'Mercado Livre',
        };
    }

    /**
     * Get the provider's short logo mark.
     */
    public function logo(): string
    {
        return match ($this) {
            self::Shopify => 'SH',
            self::Nuvemshop => 'NU',
            self::WooCommerce => 'WC',
            self::Vtex => 'VT',
            self::LojaIntegrada => 'LI',
            self::MercadoLivre => 'ML',
        };
    }

    /**
     * Get the provider's catalog description.
     */
    public function description(): string
    {
        return match ($this) {
            self::Shopify => 'Conecte sua loja e acompanhe pedidos, clientes e oportunidades em um só lugar.',
            self::Nuvemshop => 'Leve os dados da sua loja brasileira para transformar atendimentos em vendas.',
            self::WooCommerce => 'Sincronize pedidos e clientes da sua operação com o histórico de atendimento.',
            self::Vtex => 'Centralize o contexto dos pedidos e clientes da sua operação omnichannel.',
            self::LojaIntegrada => 'Conecte a operação da sua loja ao atendimento da sua equipe.',
            self::MercadoLivre => 'Acompanhe compradores, pedidos e conversas do seu marketplace.',
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
            self::Shopify => [
                'Consultar pedidos e dados de clientes',
                'Relacionar atendimentos aos pedidos',
                'Criar oportunidades a partir de conversas',
            ],
            self::Nuvemshop => [
                'Consultar pedidos e dados de clientes',
                'Acompanhar o status das compras',
                'Registrar oportunidades no CRM',
            ],
            self::WooCommerce => [
                'Consultar pedidos e clientes',
                'Relacionar conversas ao histórico de compras',
                'Sincronizar eventos da loja',
            ],
            self::Vtex => [
                'Consultar pedidos e clientes',
                'Acompanhar compras em todos os canais',
                'Registrar oportunidades no funil',
            ],
            self::LojaIntegrada => [
                'Consultar pedidos e clientes',
                'Relacionar atendimentos às compras',
                'Sincronizar eventos da loja',
            ],
            self::MercadoLivre => [
                'Consultar pedidos e compradores',
                'Relacionar perguntas e mensagens aos pedidos',
                'Registrar oportunidades de venda',
            ],
        };
    }

    /**
     * Get all e-commerce providers prepared for the integrations catalog.
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
