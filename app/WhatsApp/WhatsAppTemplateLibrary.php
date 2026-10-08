<?php

namespace App\WhatsApp;

use App\Enums\AgentTemplateUsage;

final class WhatsAppTemplateLibrary
{
    /**
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     title: string,
     *     purpose: string,
     *     usage: string,
     *     usageLabel: string,
     *     category: string,
     *     format: string,
     *     language: string,
     *     headerType: string,
     *     headerText: string,
     *     body: string,
     *     footer: string,
     *     buttons: array<int, array{type: string, text: string}>,
     *     variables: array<int, array{position: int, component: string, example: string, description: string}>
     * }>
     */
    public function all(): array
    {
        return array_map(fn (array $model): array => [
            ...$model,
            'usageLabel' => AgentTemplateUsage::from($model['usage'])->label(),
            'format' => 'standard',
            'language' => 'pt_BR',
        ], [
            [
                'key' => 'retomar_conversa',
                'name' => 'retomar_conversa',
                'title' => 'Retomar conversa',
                'purpose' => 'Quando a janela de 24 horas fechou e o Agente ainda precisa responder o Contato.',
                'usage' => AgentTemplateUsage::ResumeConversation->value,
                'category' => 'utility',
                'headerType' => 'NONE',
                'headerText' => '',
                'body' => 'Olá, {{1}}! Ficou pendente uma resposta sobre {{2}}. Responda esta mensagem para continuarmos o seu atendimento.',
                'footer' => '',
                'buttons' => [
                    ['type' => 'quick_reply', 'text' => 'Continuar atendimento'],
                ],
                'variables' => [
                    ['position' => 1, 'component' => 'body', 'example' => 'Maria', 'description' => 'Primeiro nome do Contato'],
                    ['position' => 2, 'component' => 'body', 'example' => 'o seu pedido', 'description' => 'Assunto da conversa, em poucas palavras'],
                ],
            ],
            [
                'key' => 'lembrete_compromisso',
                'name' => 'lembrete_compromisso',
                'title' => 'Lembrete de compromisso',
                'purpose' => 'Antes de um compromisso marcado pelo Agente, para o Contato não esquecer.',
                'usage' => AgentTemplateUsage::AppointmentReminder->value,
                'category' => 'utility',
                'headerType' => 'TEXT',
                'headerText' => 'Lembrete de compromisso',
                'body' => 'Olá, {{1}}! Passando para lembrar do seu compromisso em {{2}}, às {{3}}. Se precisar remarcar ou cancelar, é só responder esta mensagem.',
                'footer' => '',
                'buttons' => [
                    ['type' => 'quick_reply', 'text' => 'Confirmar presença'],
                    ['type' => 'quick_reply', 'text' => 'Preciso remarcar'],
                ],
                'variables' => [
                    ['position' => 1, 'component' => 'body', 'example' => 'Maria', 'description' => 'Primeiro nome do Contato'],
                    ['position' => 2, 'component' => 'body', 'example' => '15/10', 'description' => 'Data do compromisso, no formato dia/mês'],
                    ['position' => 3, 'component' => 'body', 'example' => '14h30', 'description' => 'Horário de início do compromisso'],
                ],
            ],
            [
                'key' => 'confirmacao_agendamento',
                'name' => 'confirmacao_agendamento',
                'title' => 'Confirmação de agendamento',
                'purpose' => 'Para o Agente confirmar um compromisso que acabou de marcar.',
                'usage' => AgentTemplateUsage::OnDemand->value,
                'category' => 'utility',
                'headerType' => 'NONE',
                'headerText' => '',
                'body' => 'Olá, {{1}}! Seu compromisso está confirmado para {{2}}, às {{3}}. Até lá!',
                'footer' => '',
                'buttons' => [],
                'variables' => [
                    ['position' => 1, 'component' => 'body', 'example' => 'Maria', 'description' => 'Primeiro nome do Contato'],
                    ['position' => 2, 'component' => 'body', 'example' => '15/10', 'description' => 'Data do compromisso, no formato dia/mês'],
                    ['position' => 3, 'component' => 'body', 'example' => '14h30', 'description' => 'Horário de início do compromisso'],
                ],
            ],
            [
                'key' => 'follow_up_atendimento',
                'name' => 'follow_up_atendimento',
                'title' => 'Follow-up de atendimento',
                'purpose' => 'Quando o Contato parou de responder no meio de uma dúvida ou solicitação.',
                'usage' => AgentTemplateUsage::FollowUp->value,
                'category' => 'utility',
                'headerType' => 'NONE',
                'headerText' => '',
                'body' => 'Olá, {{1}}! Conseguimos resolver a sua solicitação sobre {{2}}? Se ainda precisar de ajuda, é só responder esta mensagem.',
                'footer' => '',
                'buttons' => [
                    ['type' => 'quick_reply', 'text' => 'Ainda preciso de ajuda'],
                    ['type' => 'quick_reply', 'text' => 'Está resolvido'],
                ],
                'variables' => [
                    ['position' => 1, 'component' => 'body', 'example' => 'Maria', 'description' => 'Primeiro nome do Contato'],
                    ['position' => 2, 'component' => 'body', 'example' => 'a troca do produto', 'description' => 'Solicitação do Contato, em poucas palavras'],
                ],
            ],
            [
                'key' => 'follow_up_vendas',
                'name' => 'follow_up_vendas',
                'title' => 'Follow-up de vendas',
                'purpose' => 'Quando o Contato demonstrou interesse em comprar e parou de responder.',
                'usage' => AgentTemplateUsage::FollowUp->value,
                'category' => 'marketing',
                'headerType' => 'NONE',
                'headerText' => '',
                'body' => 'Olá, {{1}}! Vi que você se interessou por {{2}}. Posso te ajudar a finalizar ou tirar alguma dúvida?',
                'footer' => '',
                'buttons' => [
                    ['type' => 'quick_reply', 'text' => 'Quero continuar'],
                    ['type' => 'quick_reply', 'text' => 'Agora não'],
                ],
                'variables' => [
                    ['position' => 1, 'component' => 'body', 'example' => 'Maria', 'description' => 'Primeiro nome do Contato'],
                    ['position' => 2, 'component' => 'body', 'example' => 'o plano anual', 'description' => 'Produto ou serviço de interesse do Contato'],
                ],
            ],
        ]);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_column($this->all(), 'key');
    }
}
