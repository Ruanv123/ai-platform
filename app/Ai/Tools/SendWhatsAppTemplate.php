<?php

namespace App\Ai\Tools;

use App\Enums\AgentTemplateUsage;
use App\Inbox\AgentTemplateSender;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\WhatsAppTemplate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SendWhatsAppTemplate implements Tool
{
    /** @param Collection<int, WhatsAppTemplate> $templates */
    public function __construct(
        private Agent $agent,
        private Channel $channel,
        private Contact $contact,
        private Collection $templates,
        private AgentTemplateSender $sender,
    ) {}

    public function description(): Stringable|string
    {
        $catalog = $this->templates->map(function (WhatsAppTemplate $template): string {
            $variables = $this->sender->describeVariables($template, $template->variableDefinitions());

            return '- '.$template->name.': '.trim(($template->headerText() ?? '').' '.$template->bodyText())
                .($variables === [] ? ' (sem variáveis)' : "\n  Variáveis, na ordem:\n  ".implode("\n  ", $variables));
        })->implode("\n");

        return 'Envie ao Contato um Template aprovado do WhatsApp quando ele ajudar mais do que uma resposta em texto, por exemplo para confirmar um agendamento com botões. '
            .'Informe o nome do Template e um valor para cada variável, na ordem listada, sem quebras de linha. '
            .'Não envie o mesmo Template duas vezes seguidas e não repita na sua resposta o texto que o Template já levou. '
            ."Templates disponíveis:\n".$catalog;
    }

    public function handle(Request $request): Stringable|string
    {
        $template = $this->templates->firstWhere('name', (string) ($request['template'] ?? ''));
        if ($template === null) {
            return $this->json(['ok' => false, 'motivo' => 'Template não disponível para este Agente.']);
        }

        $values = is_array($request['values'] ?? null)
            ? array_map(fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', array_values($request['values']))
            : [];
        $delivery = $this->sender->send(
            $this->agent,
            $this->channel,
            $this->contact,
            AgentTemplateUsage::OnDemand,
            'O Agente decidiu enviar este Template durante a conversa.',
            values: $values,
            template: $template,
        );

        return $this->json($delivery->sent
            ? ['ok' => true, 'template' => $template->name]
            : ['ok' => false, 'motivo' => match ($delivery->reason) {
                'template_consent_missing' => 'O Contato não autorizou mensagens desta categoria.',
                'template_variables_unfilled' => 'Os valores das variáveis estão incompletos ou fora do formato.',
                'conversation_paused' => 'A conversa está com um atendente humano.',
                default => 'Não foi possível enviar o Template agora.',
            }]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'template' => $schema->string()
                ->enum($this->templates->pluck('name')->values()->all())
                ->description('Nome do Template a enviar.')
                ->required(),
            'values' => $schema->array()
                ->items($schema->string())
                ->description('Um valor por variável do Template, na ordem listada na descrição da ferramenta. Lista vazia quando o Template não tem variáveis.')
                ->required(),
        ];
    }

    public function name(): string
    {
        return 'enviar_template';
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
