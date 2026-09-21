<?php

namespace App\Ai\Tools;

use App\Models\FlowExecution;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class ReturnToFlow implements Tool
{
    public function __construct(private readonly FlowExecution $execution) {}

    public function description(): string
    {
        return 'Devolve o turno ao Fluxo quando a condição do atendimento foi satisfeita ou quando a conversa deve continuar no próximo passo.';
    }

    public function handle(Request $request): string
    {
        $variables = $this->execution->variables;
        $variables['__return_to_flow'] = true;
        $this->execution->forceFill([
            'variables' => $variables,
            'status' => 'running',
            'waiting_for' => null,
            'wake_at' => null,
        ])->save();

        return 'O turno foi devolvido ao Fluxo.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function name(): string
    {
        return 'return_to_flow';
    }
}
