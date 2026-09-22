<?php

namespace App\Flows;

use App\Ai\Agents\FlowBuilderAgent;
use App\Models\Flow;

final class FlowAiService
{
    public function __construct(private readonly FlowDslValidator $validator) {}

    /**
     * @param  list<array{role: string, content: string}>  $history
     * @return array{dsl: array<string, mixed>, changes: list<array{kind: string, description: string}>, assistantMessage: string}
     */
    public function propose(Flow $flow, string $message, array $history = []): array
    {
        $agent = new FlowBuilderAgent(
            $flow->draft_dsl,
            $history,
            config('flows.node_catalog', []),
        );
        $response = $agent->prompt(
            $message."\n\nContexto estruturado:\n".$agent->promptContext(),
            provider: config('ai.playground.provider'),
            model: config('ai.playground.model'),
            timeout: (int) config('ai.playground.timeout', 60),
        );
        $payload = json_decode($response->text, true);
        if (! is_array($payload) || ! is_array($payload['dsl'] ?? null)) {
            throw new FlowValidationException(['A IA não devolveu um Fluxo em formato válido.']);
        }

        $catalogTypes = [];
        foreach (config('flows.node_catalog', []) as $catalogNode) {
            if (is_array($catalogNode) && is_string($catalogNode['type'] ?? null)) {
                $catalogTypes[] = $catalogNode['type'];
            }
        }
        $rawNodes = $payload['dsl']['nodes'] ?? [];
        if (! is_array($rawNodes)) {
            throw new FlowValidationException(['A IA não devolveu uma lista de Nós válida.']);
        }
        foreach ($rawNodes as $node) {
            if (is_array($node) && ! in_array($node['type'] ?? null, $catalogTypes, true)) {
                throw new FlowValidationException(['A IA tentou usar um tipo de Nó que não está no catálogo.']);
            }
        }

        try {
            $dsl = $this->validator->normalize($payload['dsl']);
        } catch (\InvalidArgumentException $exception) {
            throw new FlowValidationException(['A proposta da IA não passou pela validação: '.$exception->getMessage()], previous: $exception);
        }

        return [
            'dsl' => $dsl,
            'changes' => $this->changes($flow->draft_dsl, $dsl),
            'assistantMessage' => 'Encontrei uma proposta. Revise as alterações antes de aplicar ao rascunho.',
        ];
    }

    /**
     * @param  array<string, mixed>  $oldDsl
     * @param  array<string, mixed>  $newDsl
     * @return list<array{kind: string, description: string}>
     */
    private function changes(array $oldDsl, array $newDsl): array
    {
        /** @var array<string, array<string, mixed>> $oldNodes */
        $oldNodes = $this->nodesById($oldDsl['nodes'] ?? []);
        /** @var array<string, array<string, mixed>> $newNodes */
        $newNodes = $this->nodesById($newDsl['nodes'] ?? []);
        /** @var array<string, string> $labels */
        $labels = [];
        foreach (config('flows.node_catalog', []) as $catalogNode) {
            if (is_array($catalogNode) && is_string($catalogNode['type'] ?? null) && is_string($catalogNode['label'] ?? null)) {
                $labels[$catalogNode['type']] = $catalogNode['label'];
            }
        }
        $changes = [];

        foreach (array_diff(array_keys($newNodes), array_keys($oldNodes)) as $id) {
            $type = (string) ($newNodes[$id]['type'] ?? '');
            $changes[] = [
                'kind' => 'add',
                'description' => 'Adicionar '.($labels[$type] ?? 'Nó')." ({$id}).",
            ];
        }
        foreach (array_diff(array_keys($oldNodes), array_keys($newNodes)) as $id) {
            $type = (string) ($oldNodes[$id]['type'] ?? '');
            $changes[] = [
                'kind' => 'remove',
                'description' => 'Remover '.($labels[$type] ?? 'Nó')." ({$id}).",
            ];
        }
        foreach (array_intersect(array_keys($newNodes), array_keys($oldNodes)) as $id) {
            if ($newNodes[$id] !== $oldNodes[$id]) {
                $changes[] = [
                    'kind' => 'update',
                    'description' => "Atualizar o Nó {$id}.",
                ];
            }
        }
        if (($oldDsl['edges'] ?? []) !== ($newDsl['edges'] ?? [])) {
            $changes[] = [
                'kind' => 'connect',
                'description' => 'Atualizar as conexões do Fluxo.',
            ];
        }

        return $changes === []
            ? [['kind' => 'update', 'description' => 'Nenhuma alteração foi identificada.']]
            : $changes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function nodesById(mixed $nodes): array
    {
        $result = [];
        if (! is_array($nodes)) {
            return $result;
        }

        foreach ($nodes as $node) {
            if (is_array($node) && is_string($node['id'] ?? null)) {
                $result[$node['id']] = $node;
            }
        }

        return $result;
    }
}
