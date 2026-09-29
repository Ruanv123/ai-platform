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

class MoveCrmDealStage implements Tool
{
    public const string OPERATION_TYPE = 'move_deal';

    public function __construct(
        private Team $team,
        private Agent $agent,
        private ?Contact $contact,
        private CrmOperationDispatcher $dispatcher,
        private string $pipelineId,
        private array $allowedStages,
        private bool $simulate = false,
        private ?string $playgroundPhone = null,
    ) {}

    public function description(): Stringable|string
    {
        $stages = collect($this->allowedStages)
            ->map(fn (array $stage): string => (string) ($stage['name'] ?? $stage['stage_id'] ?? '').': '.(string) ($stage['description'] ?? ''))
            ->implode('; ');

        return 'Mova o Negócio aberto deste Contato neste Funil de vendas para uma das Etapas liberadas, somente quando o critério estiver atendido. Etapas liberadas: '.$stages;
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $arguments = $request->validate([
                'stage_id' => ['required', 'string', 'max:128'],
            ]);
        } catch (ValidationException $exception) {
            return $this->json(['ok' => false, 'message' => $exception->validator->errors()->first()]);
        }

        $stageId = trim($arguments['stage_id']);
        $stage = collect($this->allowedStages)->first(fn (array $allowedStage): bool => ($allowedStage['stage_id'] ?? null) === $stageId);
        if ($stage === null || trim((string) ($stage['description'] ?? '')) === '') {
            return $this->json(['ok' => false, 'message' => 'Essa Etapa do funil não está liberada para o Agente.']);
        }

        if ($this->simulate) {
            return $this->json([
                'ok' => true,
                'simulated' => true,
                'message' => 'No atendimento real, este pedido moveria o Negócio aberto para a Etapa liberada em segundo plano.',
                'would_send' => [
                    'pipeline_id' => $this->pipelineId,
                    'stage_id' => $stageId,
                    'stage_description' => $stage['description'],
                    'contact_name' => $this->contact?->name,
                    'contact_phone' => $this->contact?->phone ?? $this->playgroundPhone,
                ],
            ]);
        }

        if ($this->contact === null) {
            return $this->json(['ok' => false, 'message' => 'Não há um Contato neste atendimento.']);
        }

        try {
            $operation = $this->dispatcher->queueMoveDeal(
                $this->team,
                $this->agent,
                $this->contact,
                $this->pipelineId,
                $stageId,
                $this->allowedStages,
            );

            return $this->json([
                'ok' => true,
                'registered' => true,
                'operation_id' => $operation->getKey(),
                'message' => 'Pedido registrado. O Negócio será movido no CRM em segundo plano.',
            ]);
        } catch (CrmOperationException $exception) {
            return $this->json(['ok' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->json(['ok' => false, 'message' => 'Não foi possível registrar a Operação no CRM agora.']);
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stage_id' => $schema->string()
                ->description('ID de uma Etapa liberada. Escolha somente quando o critério descrito pela Empresa estiver atendido.')
                ->enum(array_values(array_map(fn (array $stage): string => (string) $stage['stage_id'], $this->allowedStages)))
                ->required(),
        ];
    }

    public function name(): string
    {
        return 'mover_negocio_etapa';
    }

    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
