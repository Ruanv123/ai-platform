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

class RegisterCrmNote implements Tool
{
    public const string OPERATION_TYPE = 'register_note';

    public function __construct(
        private Team $team,
        private Agent $agent,
        private ?Contact $contact,
        private CrmOperationDispatcher $dispatcher,
        private string $pipelineId,
        private bool $simulate = false,
        private ?string $playgroundPhone = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Registre no CRM uma nota curta com informação relevante que o Contato compartilhou. A nota será enviada à Pessoa do CRM e, se existir, ao Negócio aberto do Funil de vendas do Agente.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $arguments = $request->validate([
                'body' => ['required', 'string', 'max:5000'],
            ]);
        } catch (ValidationException $exception) {
            return $this->json(['ok' => false, 'message' => $exception->validator->errors()->first()]);
        }

        $body = trim($arguments['body']);
        if ($body === '') {
            return $this->json(['ok' => false, 'message' => 'Escreva o conteúdo da nota.']);
        }

        if ($this->simulate) {
            return $this->json([
                'ok' => true,
                'simulated' => true,
                'message' => 'No atendimento real, esta nota seria enviada ao CRM em segundo plano.',
                'would_send' => [
                    'body' => $body,
                    'contact_name' => $this->contact?->name,
                    'contact_phone' => $this->contact?->phone ?? $this->playgroundPhone,
                    'pipeline_id' => $this->pipelineId,
                    'include_open_deal' => true,
                ],
            ]);
        }

        if ($this->contact === null) {
            return $this->json(['ok' => false, 'message' => 'Não há um Contato neste atendimento.']);
        }

        try {
            $operation = $this->dispatcher->queueRegisterNote(
                $this->team,
                $this->agent,
                $this->contact,
                $body,
                $this->pipelineId !== '' ? $this->pipelineId : null,
            );

            return $this->json([
                'ok' => true,
                'registered' => true,
                'operation_id' => $operation->getKey(),
                'message' => 'Pedido registrado. A nota será enviada ao CRM em segundo plano.',
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
            'body' => $schema->string()->description('Nota curta com uma informação relevante que o Contato compartilhou.')->required(),
        ];
    }

    public function name(): string
    {
        return 'registrar_nota_crm';
    }

    private function json(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
