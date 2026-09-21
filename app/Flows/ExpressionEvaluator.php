<?php

namespace App\Flows;

use InvalidArgumentException;

final class ExpressionEvaluator
{
    /** @param array<string, mixed> $context */
    public function evaluate(string $expression, array $context = []): bool
    {
        $tokens = $this->tokenize(trim($expression));
        $position = 0;
        $value = $this->parseOr($tokens, $position, $context);

        if ($position !== count($tokens)) {
            throw new InvalidArgumentException('Expressão contém tokens depois do fim.');
        }

        return (bool) $value;
    }

    /**
     * @param  list<array{type: string, value: mixed}>  $tokens
     * @param  array<string, mixed>  $context
     */
    private function parseOr(array $tokens, int &$position, array $context): mixed
    {
        $value = $this->parseAnd($tokens, $position, $context);
        while ($this->match($tokens, $position, '||') || $this->match($tokens, $position, 'or')) {
            $value = (bool) $value || (bool) $this->parseAnd($tokens, $position, $context);
        }

        return $value;
    }

    /**
     * @param  list<array{type: string, value: mixed}>  $tokens
     * @param  array<string, mixed>  $context
     */
    private function parseAnd(array $tokens, int &$position, array $context): mixed
    {
        $value = $this->parseUnary($tokens, $position, $context);
        while ($this->match($tokens, $position, '&&') || $this->match($tokens, $position, 'and')) {
            $value = (bool) $value && (bool) $this->parseUnary($tokens, $position, $context);
        }

        return $value;
    }

    /**
     * @param  list<array{type: string, value: mixed}>  $tokens
     * @param  array<string, mixed>  $context
     */
    private function parseUnary(array $tokens, int &$position, array $context): mixed
    {
        if ($this->match($tokens, $position, '!')) {
            return ! (bool) $this->parseUnary($tokens, $position, $context);
        }

        return $this->parseComparison($tokens, $position, $context);
    }

    /**
     * @param  list<array{type: string, value: mixed}>  $tokens
     * @param  array<string, mixed>  $context
     */
    private function parseComparison(array $tokens, int &$position, array $context): mixed
    {
        $left = $this->parsePrimary($tokens, $position, $context);
        $operator = $tokens[$position]['value'] ?? null;

        if (! in_array($operator, ['==', '!=', '>=', '<=', '>', '<', 'contains', 'in'], true)) {
            return $left;
        }

        $position++;
        $right = $this->parsePrimary($tokens, $position, $context);

        return match ($operator) {
            '==' => $left == $right,
            '!=' => $left != $right,
            '>=' => $left >= $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '<' => $left < $right,
            'contains' => is_array($left)
                ? in_array($right, $left, true)
                : str_contains((string) $left, (string) $right),
            'in' => is_array($right) && in_array($left, $right, true),
        };
    }

    /**
     * @param  list<array{type: string, value: mixed}>  $tokens
     * @param  array<string, mixed>  $context
     */
    private function parsePrimary(array $tokens, int &$position, array $context): mixed
    {
        $token = $tokens[$position] ?? null;
        if ($token === null) {
            throw new InvalidArgumentException('Expressão incompleta.');
        }

        if ($token['value'] === '(') {
            $position++;
            $value = $this->parseOr($tokens, $position, $context);
            $this->expect($tokens, $position, ')');

            return $value;
        }

        $position++;
        if ($token['type'] !== 'identifier') {
            return $token['value'];
        }

        return $this->resolve((string) $token['value'], $context);
    }

    /** @param array<string, mixed> $context */
    private function resolve(string $path, array $context): mixed
    {
        if ($path === 'true') {
            return true;
        }
        if ($path === 'false') {
            return false;
        }
        if ($path === 'null') {
            return null;
        }

        [$root, $rest] = array_pad(explode('.', $path, 2), 2, null);
        $root = match ($root) {
            'contact', 'contact_field', 'field', 'fields' => 'fields',
            'tag' => 'tags',
            'globals' => 'global',
            default => $root,
        };
        $value = array_key_exists($root, $context) ? $context[$root] : ($context['variables'][$path] ?? null);

        if ($rest !== null) {
            $value = data_get($context[$root] ?? [], $rest);
        }

        return $value;
    }

    /** @param list<array{type: string, value: mixed}> $tokens */
    private function match(array $tokens, int &$position, string $value): bool
    {
        if (($tokens[$position]['value'] ?? null) !== $value) {
            return false;
        }

        $position++;

        return true;
    }

    /** @param list<array{type: string, value: mixed}> $tokens */
    private function expect(array $tokens, int &$position, string $value): void
    {
        if (! $this->match($tokens, $position, $value)) {
            throw new InvalidArgumentException("Esperado {$value} na expressão.");
        }
    }

    /** @return list<array{type: string, value: mixed}> */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $offset = 0;
        $pattern = <<<'REGEX'
~\G\s*(?:((?:"(?:\\.|[^"\\])*"|\'(?:\\.|[^\'\\])*\'))|(\d+(?:\.\d+)?)|([A-Za-z_][A-Za-z0-9_.]*)|(==|!=|>=|<=|&&|\|\||[()!<>]|contains|in|and|or))~A
REGEX;

        while ($offset < strlen($expression)) {
            if (preg_match($pattern, $expression, $match, 0, $offset) !== 1) {
                throw new InvalidArgumentException('Expressão contém um token inválido.');
            }

            $offset += strlen($match[0]);
            if ($match[1] !== '') {
                $tokens[] = ['type' => 'literal', 'value' => stripcslashes(substr($match[1], 1, -1))];
            } elseif ($match[2] !== '') {
                $tokens[] = ['type' => 'literal', 'value' => str_contains($match[2], '.') ? (float) $match[2] : (int) $match[2]];
            } elseif ($match[3] !== '') {
                $tokens[] = [
                    'type' => in_array($match[3], ['contains', 'in', 'and', 'or'], true) ? 'operator' : 'identifier',
                    'value' => $match[3],
                ];
            } else {
                $tokens[] = ['type' => 'operator', 'value' => $match[4]];
            }
        }

        return $tokens;
    }
}
