<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class FlowBuilderAgent implements Agent
{
    use Promptable;

    /**
     * @param  array<string, mixed>  $currentDsl
     * @param  list<array{role: string, content: string}>  $history
     * @param  list<array<string, mixed>>  $catalog
     */
    public function __construct(
        private readonly array $currentDsl,
        private readonly array $history,
        private readonly array $catalog,
    ) {}

    public function instructions(): string
    {
        return <<<'PROMPT'
Você é o construtor de Fluxos da plataforma. Responda somente com JSON válido, sem markdown.
O JSON deve ter exatamente esta forma: {"dsl":{"version":1,"nodes":[],"edges":[]},"changes":[{"kind":"add|remove|update|connect","description":"..."}]}
Use somente os tipos presentes no catálogo. Preserve os Nós existentes quando o pedido for um ajuste. Cada Nó precisa de id, type, config e position {x,y}; cada aresta precisa de id, source e target. O Fluxo deve ter uma única raiz conectada e todos os caminhos devem chegar a um Nó final ou a um passo que possa continuar.
Escreva as descrições de alteração em português claro. Nunca aplique a alteração fora do JSON.
PROMPT;
    }

    public function promptContext(): string
    {
        return json_encode([
            'dsl_atual' => $this->currentDsl,
            'catalogo_de_nos' => $this->catalog,
            'historico' => $this->history,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
