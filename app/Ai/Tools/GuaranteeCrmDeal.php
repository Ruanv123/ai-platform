<?php

namespace App\Ai\Tools;

use App\Crm\CrmOperationDispatcher;
use App\Crm\CrmOperationException;
use App\Models\Agent;
use App\Models\Contact;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class GuaranteeCrmDeal implements Tool
{
    public const string OPERATION_TYPE = 'guarantee_deal';

    public function __construct(
        private Team $team,
        private Agent $agent,
        private ?Contact $contact,
        private CrmOperationDispatcher $dispatcher,
        private string $pipelineId,
        private string $initialStageId,
        private bool $simulate = false,
        private ?string $playgroundPhone = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Registre no CRM o interesse comercial confirmado pelo Contato. Informe um título claro para a negociação e, se o Contato mencionar um valor, inclua o valor estimado. O pedido será processado em segundo plano.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $arguments = $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            ]);
        } catch (ValidationException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->validator->errors()->first(),
            ]);
        }

        $title = trim($arguments['title']);
        $value = isset($arguments['estimated_value']) ? (float) $arguments['estimated_value'] : null;
        if ($title === '') {
            return $this->json(['ok' => false, 'message' => 'Informe um título para a negociação.']);
        }

        if ($this->simulate) {
            return $this->json([
                'ok' => true,
                'simulated' => true,
                'message' => 'No atendimento real, este pedido seria enviado ao CRM em segundo plano.',
                'would_send' => [
                    'title' => $title,
                    'estimated_value' => $value,
                    'contact_name' => $this->contact?->name,
                    'contact_phone' => $this->contact?->phone ?? $this->playgroundPhone,
                    'pipeline_id' => $this->pipelineId,
                    'initial_stage_id' => $this->initialStageId,
                ],
            ]);
        }

        if ($this->contact === null) {
            return $this->json(['ok' => false, 'message' => 'Não há um Contato neste atendimento.']);
        }

        try {
            $operation = $this->dispatcher->queueGuaranteeDeal(
                $this->team,
                $this->agent,
                $this->contact,
                $title,
                $value,
                $this->pipelineId,
                $this->initialStageId,
            );

            return $this->json([
                'ok' => true,
                'registered' => true,
                'operation_id' => $operation->getKey(),
                'message' => 'Pedido registrado. A negociação será atualizada no CRM em segundo plano.',
            ]);
        } catch (CrmOperationException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->json([
                'ok' => false,
                'message' => 'Não foi possível registrar a Operação no CRM agora.',
            ]);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Título claro da negociação, conforme o interesse confirmado pelo Contato.')->required(),
            'estimated_value' => $schema->number()->min(0)->description('Valor estimado mencionado pelo Contato, quando disponível.'),
        ];
    }

    public function name(): string
    {
        return 'garantir_negocio';
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
