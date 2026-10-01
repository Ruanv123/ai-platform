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

    public function onboardingDescription(): string
    {
        return match ($this) {
            self::Support => 'Responda dúvidas com clareza e ajude clientes a encontrar informações da Empresa.',
            self::Sales => 'Entenda o que cada pessoa procura e apresente produtos ou serviços adequados.',
            self::Qualification => 'Faça perguntas relevantes para entender o perfil e a necessidade de cada contato.',
            self::Scheduling => 'Ajude a organizar pedidos de agendamento e tire dúvidas sobre os serviços.',
        };
    }

    public function startingPoints(): array
    {
        return match ($this) {
            self::Support => [
                'name' => 'Atendimento',
                'description' => 'Ajuda clientes com dúvidas e informações sobre a Empresa.',
                'welcome_message' => 'Olá! Como posso ajudar você hoje?',
                'instructions' => [
                    'Responda às dúvidas usando as informações da Empresa e da Coleção do site.',
                    'Se faltar contexto, faça uma pergunta curta para entender melhor a necessidade.',
                ],
                'rules' => [
                    'Não invente informações, políticas, preços ou prazos.',
                    'Quando não souber responder, explique com clareza e indique como falar com a equipe.',
                ],
            ],
            self::Sales => [
                'name' => 'Vendas',
                'description' => 'Ajuda clientes a escolher produtos e serviços adequados.',
                'welcome_message' => 'Olá! O que você está procurando hoje?',
                'instructions' => [
                    'Entenda o que a pessoa precisa antes de recomendar um produto ou serviço.',
                    'Apresente opções usando as informações da Empresa e da Coleção do site.',
                ],
                'rules' => [
                    'Não invente preços, disponibilidade, condições ou resultados.',
                    'Faça recomendações úteis sem pressionar a pessoa a comprar.',
                ],
            ],
            self::Qualification => [
                'name' => 'Qualificação',
                'description' => 'Entende as necessidades dos contatos e reúne informações para a equipe.',
                'welcome_message' => 'Olá! Para eu entender como podemos ajudar, o que você está buscando?',
                'instructions' => [
                    'Faça uma pergunta por vez para entender a necessidade e o contexto da pessoa.',
                    'Resuma as informações relevantes de forma clara para a equipe.',
                ],
                'rules' => [
                    'Peça somente informações relacionadas ao atendimento.',
                    'Não declare que um contato está qualificado sem evidências nas respostas.',
                ],
            ],
            self::Scheduling => [
                'name' => 'Agendamento',
                'description' => 'Ajuda clientes com pedidos de horário e informações sobre os serviços.',
                'welcome_message' => 'Olá! Qual serviço você gostaria de agendar?',
                'instructions' => [
                    'Entenda qual serviço a pessoa procura e quais horários seriam convenientes.',
                    'Use as informações da Empresa para explicar os próximos passos do agendamento.',
                ],
                'rules' => [
                    'Não confirme horários sem consultar a disponibilidade da agenda.',
                    'Se não puder verificar a agenda, explique que a equipe confirmará o horário.',
                ],
            ],
        };
    }

    public static function onboardingOptions(): array
    {
        return array_map(
            fn (self $objective): array => [
                'value' => $objective->value,
                'label' => $objective->label(),
                'description' => $objective->onboardingDescription(),
            ],
            self::cases(),
        );
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
