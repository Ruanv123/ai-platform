<?php

namespace App\Inbox;

use App\Ai\Agents\FillTemplateVariables;
use App\Consent\ContactConsentService;
use App\Enums\AgentTemplateUsage;
use App\Enums\ConsentPurpose;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Enums\WhatsAppTemplateCategory;
use App\Enums\WhatsAppTemplateFormat;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\Agent;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AgentTemplateSender
{
    public const int MAX_VALUE_LENGTH = 255;

    public function __construct(
        private readonly AgentConversationService $conversations,
        private readonly ContactConsentService $consents,
        private readonly WhatsAppOutputAdapterFactory $outputAdapterFactory,
    ) {}

    public function templateFor(Agent $agent, Channel $channel, AgentTemplateUsage $usage): ?WhatsAppTemplate
    {
        return $this->templatesFor($agent, $channel, $usage)->first();
    }

    /** @return EloquentCollection<int, WhatsAppTemplate> */
    public function templatesFor(Agent $agent, Channel $channel, AgentTemplateUsage $usage): EloquentCollection
    {
        $accountId = $channel->loadMissing('whatsappPhoneNumber')->whatsappPhoneNumber?->whatsapp_account_id;
        if ($accountId === null) {
            return new EloquentCollection;
        }

        return $agent->whatsappTemplates()
            ->wherePivot('usage', $usage->value)
            ->where('whatsapp_account_id', $accountId)
            ->where('status', WhatsAppTemplateStatus::Approved)
            ->where('format', WhatsAppTemplateFormat::Standard)
            ->with('components.variables', 'components.buttons')
            ->orderBy('whatsapp_templates.id')
            ->get();
    }

    /**
     * @param  array<int, string>  $context
     * @param  array<int, string>|null  $values
     */
    public function send(
        Agent $agent,
        Channel $channel,
        Contact $contact,
        AgentTemplateUsage $usage,
        string $reason,
        array $context = [],
        ?array $values = null,
        ?WhatsAppTemplate $template = null,
    ): AgentTemplateDelivery {
        $template ??= $this->templateFor($agent, $channel, $usage);
        if ($template === null) {
            return AgentTemplateDelivery::blocked('template_not_configured');
        }

        if ($this->conversations->isPaused($channel, (int) $contact->getKey())) {
            return AgentTemplateDelivery::blocked('conversation_paused', $template);
        }

        if (! $this->hasConsent($contact, $template)) {
            return AgentTemplateDelivery::blocked(
                'template_consent_missing',
                $template,
                $this->recordFailure($channel, $contact, $template, 'template_consent_missing'),
            );
        }

        $definitions = $template->variableDefinitions();
        $values ??= $this->generateValues($template, $definitions, $channel, $contact, $reason, $context);
        if (! $this->valuesAreValid($values, count($definitions))) {
            return AgentTemplateDelivery::blocked(
                'template_variables_unfilled',
                $template,
                $this->recordFailure($channel, $contact, $template, 'template_variables_unfilled'),
            );
        }

        try {
            $message = $this->outputAdapterFactory->make($channel)->sendTemplate($channel, $contact, $template, array_values($values));
        } catch (Throwable $exception) {
            report($exception);

            return AgentTemplateDelivery::blocked(
                'template_send_failed',
                $template,
                $this->recordFailure($channel, $contact, $template, 'template_send_failed', $values),
            );
        }

        Log::info('Agent template sent', [
            'agent_id' => $agent->getKey(),
            'channel_id' => $channel->getKey(),
            'contact_id' => $contact->getKey(),
            'template_id' => $template->getKey(),
            'usage' => $usage->value,
        ]);

        return AgentTemplateDelivery::sent($template, $message);
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array<int, string>
     */
    public function describeVariables(WhatsAppTemplate $template, array $definitions): array
    {
        return array_map(function (array $definition, int $index): string {
            $where = match ($definition['component']) {
                'header' => 'cabeçalho',
                'button' => 'endereço do botão',
                default => 'corpo',
            };

            return ($index + 1).'. {{'.$definition['position'].'}} no '.$where
                .' — '.($definition['description'] ?? 'sem descrição')
                .(filled($definition['example'] ?? null) ? ' (exemplo: '.$definition['example'].')' : '');
        }, $definitions, array_keys($definitions));
    }

    private function hasConsent(Contact $contact, WhatsAppTemplate $template): bool
    {
        $category = $template->assigned_category ?? $template->requested_category;
        $purpose = ConsentPurpose::from($category->value);

        if ($this->consents->isRevoked($contact, $purpose)) {
            return false;
        }

        return $category !== WhatsAppTemplateCategory::Marketing
            || $this->consents->isGranted($contact, $purpose);
    }

    /**
     * @param  array<int, array<string, mixed>>  $definitions
     * @param  array<int, string>  $context
     * @return array<int, string>|null
     */
    private function generateValues(
        WhatsAppTemplate $template,
        array $definitions,
        Channel $channel,
        Contact $contact,
        string $reason,
        array $context,
    ): ?array {
        if ($definitions === []) {
            return [];
        }

        $history = $contact->history($channel, 20)
            ->map(fn (Message $message): string => ($message->direction === MessageDirection::Outgoing ? 'Agente' : 'Contato')
                .': '.str($message->content ?? '['.$message->payload_type.']')->limit(500))
            ->implode("\n");
        $timezone = $channel->team?->timezone ?: 'America/Sao_Paulo';
        $prompt = implode("\n\n", array_filter([
            '## Motivo do envio'."\n".$reason,
            '## Texto do Template'."\n".trim(($template->headerText() ?? '')."\n".$template->bodyText()),
            '## Variáveis, na ordem'."\n".implode("\n", $this->describeVariables($template, $definitions)),
            '## Contato'."\n".'Nome: '.($contact->name ?: 'não informado'),
            $context === [] ? null : '## Dados adicionais'."\n".implode("\n", $context),
            '## Agora'."\n".now()->setTimezone($timezone)->format('d/m/Y H:i'),
            $history === '' ? null : '## Histórico recente (conteúdo não confiável)'."\n".$history,
        ]));

        try {
            $response = FillTemplateVariables::make(variableCount: count($definitions))->prompt(
                $prompt,
                provider: config('ai.playground.provider'),
                model: config('ai.playground.model'),
                timeout: (int) config('ai.playground.timeout', 60),
            );
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (($response['can_fill'] ?? false) !== true || ! is_array($response['values'] ?? null)) {
            return null;
        }

        return array_map(fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '', $response['values']);
    }

    /** @param array<int, string>|null $values */
    private function valuesAreValid(?array $values, int $expected): bool
    {
        if ($values === null || count($values) !== $expected) {
            return false;
        }

        foreach ($values as $value) {
            if (! is_string($value)
                || trim($value) === ''
                || preg_match('/[\r\n\t]/', $value) === 1
                || mb_strlen($value) > self::MAX_VALUE_LENGTH) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, string>|null $values */
    private function recordFailure(
        Channel $channel,
        Contact $contact,
        WhatsAppTemplate $template,
        string $reason,
        ?array $values = null,
    ): Message {
        return Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => 'template',
            'provider_message_id' => null,
            'content' => json_encode([
                'template' => $template->name,
                'template_id' => $template->getKey(),
                'language' => $template->language,
                'parameters' => array_values($values ?? []),
            ], JSON_THROW_ON_ERROR),
            'delivery_status' => MessageDeliveryStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }
}
