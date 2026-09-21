<?php

namespace App\Flows;

use App\Enums\FlowCategory;
use InvalidArgumentException;

final class FlowDslValidator
{
    /**
     * Validate a version-independent flow definition and return its normalized graph.
     *
     * @param  array<string, mixed>  $dsl
     * @return array{version: int, nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public function normalize(array $dsl): array
    {
        $version = $dsl['version'] ?? 1;
        $rawNodes = $dsl['nodes'] ?? null;
        $rawEdges = $dsl['edges'] ?? [];

        if (! is_int($version) && ! is_numeric($version)) {
            throw new InvalidArgumentException('O DSL precisa declarar uma versão numérica.');
        }

        if (! is_array($rawNodes) || $rawNodes === []) {
            throw new InvalidArgumentException('O Fluxo precisa ter ao menos um Nó.');
        }

        if (! is_array($rawEdges)) {
            throw new InvalidArgumentException('As arestas do Fluxo precisam ser uma lista.');
        }

        $nodes = [];
        $ids = [];

        foreach ($rawNodes as $key => $node) {
            if (is_string($node)) {
                $node = ['id' => (string) $key, 'type' => $node, 'config' => []];
            }

            if (! is_array($node)) {
                throw new InvalidArgumentException('Cada Nó precisa ser um objeto.');
            }

            $id = $node['id'] ?? (is_string($key) ? $key : null);
            $type = $node['type'] ?? null;

            if (! is_string($id) || trim($id) === '' || ! is_string($type) || trim($type) === '') {
                throw new InvalidArgumentException('Cada Nó precisa de id e tipo.');
            }

            if (isset($ids[$id])) {
                throw new InvalidArgumentException("O Nó {$id} está duplicado.");
            }

            $node['id'] = $id;
            $node['type'] = $this->normalizeType($type);
            if (! in_array($node['type'], [
                'message',
                'capture_response',
                'condition',
                'wait',
                'define_variable',
                'set_variable',
                'set_variable_or_tag',
                'deliver_agent',
                'transfer_human',
                'emit_event',
                'http_request',
                'send_template',
                'subflow',
                'sub_flow',
                'ai_task',
                'intent_router',
                'route_intent',
                'ab_test',
                'test_ab',
                'exception',
                'end',
                'finish',
            ], true)) {
                throw new InvalidArgumentException("O tipo de Nó {$node['type']} não pertence ao catálogo do Fluxo.");
            }
            $node['config'] = is_array($node['config'] ?? null) ? $node['config'] : [];
            $nodes[] = $node;
            $ids[$id] = true;
        }

        $edges = [];
        foreach ($rawEdges as $edge) {
            if (! is_array($edge)) {
                throw new InvalidArgumentException('Cada aresta precisa ser um objeto.');
            }

            $source = $edge['source'] ?? $edge['from'] ?? null;
            $target = $edge['target'] ?? $edge['to'] ?? null;

            if (! is_string($source) || ! is_string($target)) {
                throw new InvalidArgumentException('Cada aresta precisa de origem e destino.');
            }

            if (! isset($ids[$source])) {
                throw new InvalidArgumentException("A aresta aponta para o Nó de origem inexistente {$source}.");
            }

            if (! isset($ids[$target])) {
                throw new InvalidArgumentException("A aresta aponta para o Nó de destino inexistente {$target}.");
            }

            $edge['source'] = $source;
            $edge['target'] = $target;
            $edges[] = $edge;
        }

        $outgoing = [];
        $incoming = array_fill_keys(array_keys($ids), 0);
        foreach ($edges as $edge) {
            $outgoing[$edge['source']][] = $edge;
            $incoming[$edge['target']]++;
        }

        $roots = array_keys(array_filter($incoming, fn (int $count): bool => $count === 0));
        if ($roots === []) {
            throw new InvalidArgumentException('O Fluxo não possui Nó inicial; o grafo é um ciclo.');
        }

        if (count($roots) > 1) {
            throw new InvalidArgumentException('O Fluxo possui mais de um Nó inicial: '.implode(', ', $roots).'.');
        }

        $reachable = [];
        $stack = $roots;
        while ($stack !== []) {
            $id = array_pop($stack);
            if (isset($reachable[$id])) {
                continue;
            }

            $reachable[$id] = true;
            foreach ($outgoing[$id] ?? [] as $edge) {
                $stack[] = $edge['target'];
            }
        }

        $orphans = array_diff(array_keys($ids), array_keys($reachable));
        if ($orphans !== []) {
            throw new InvalidArgumentException('O Fluxo possui Nó órfão: '.implode(', ', $orphans).'.');
        }

        $nodeById = collect($nodes)->keyBy('id')->all();
        foreach ($nodes as $node) {
            if ($node['type'] === 'condition') {
                $hasElse = array_key_exists('else', $node['config'])
                    || collect($outgoing[$node['id']] ?? [])->contains(fn (array $edge): bool => in_array(strtolower((string) ($edge['label'] ?? $edge['condition'] ?? '')), ['else', 'otherwise', 'senão', 'no'], true));
                if (! $hasElse) {
                    throw new InvalidArgumentException("O Nó de Condição {$node['id']} precisa de uma saída senão.");
                }
            }
            if (($outgoing[$node['id']] ?? []) !== []) {
                continue;
            }

            if ($this->isWaitingNode($node['type']) && ! $this->isTerminalWaitingNode($node)) {
                throw new InvalidArgumentException("O Nó {$node['id']} espera uma resposta, mas não possui saída.");
            }
        }

        if (! $this->allPathsExit($roots, $outgoing, $nodeById)) {
            throw new InvalidArgumentException('O Fluxo possui um ciclo sem saída.');
        }

        return [
            'version' => (int) $version,
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /** @param array<string, mixed> $dsl */
    public function category(array $dsl): FlowCategory
    {
        foreach ($dsl['nodes'] as $node) {
            if (is_array($node) && $this->isWaitingNode((string) ($node['type'] ?? ''))) {
                return FlowCategory::Conversation;
            }
        }

        return FlowCategory::Reaction;
    }

    public function isWaitingNode(string $type): bool
    {
        return in_array($this->normalizeType($type), [
            'capture_response',
            'deliver_agent',
            'wait',
            'transfer_human',
        ], true);
    }

    private function normalizeType(string $type): string
    {
        return str_replace('-', '_', strtolower(trim($type)));
    }

    /**
     * @param  list<string>  $roots
     * @param  array<string, list<array<string, mixed>>>  $outgoing
     * @param  array<string, array<string, mixed>>  $nodes
     */
    private function allPathsExit(array $roots, array $outgoing, array $nodes): bool
    {
        $visiting = [];
        $memo = [];
        foreach ($roots as $root) {
            if (! $this->canExit($root, $outgoing, $nodes, $visiting, $memo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $outgoing
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<string, bool>  $visiting
     * @param  array<string, bool>  $memo
     */
    private function canExit(string $id, array $outgoing, array $nodes, array &$visiting, array &$memo): bool
    {
        if (isset($memo[$id])) {
            return true;
        }

        if (isset($visiting[$id])) {
            return false;
        }

        $visiting[$id] = true;
        $next = $outgoing[$id] ?? [];
        if ($next === []) {
            unset($visiting[$id]);
            $memo[$id] = true;

            return true;
        }

        $canExit = false;
        foreach ($next as $edge) {
            if ($this->canExit($edge['target'], $outgoing, $nodes, $visiting, $memo)) {
                $canExit = true;
                break;
            }
        }

        unset($visiting[$id]);
        if ($canExit) {
            $memo[$id] = true;
        }

        return $canExit;
    }

    /** @param array<string, mixed> $node */
    private function isTerminalWaitingNode(array $node): bool
    {
        return (bool) data_get($node, 'config.terminal', false)
            || $node['type'] === 'transfer_human'
            || ($node['type'] === 'deliver_agent'
                && blank(data_get($node, 'config.return_when') ?? data_get($node, 'config.return_condition')));
    }
}
