<?php

namespace App\Knowledge;

class MarkdownChunker
{
    /**
     * Split Markdown into chunks while keeping paragraph boundaries and a small overlap.
     *
     * @return array<int, string>
     */
    public function chunk(string $markdown): array
    {
        $targetLength = (int) config('knowledge.chunking.target_tokens', 800)
            * (int) config('knowledge.chunking.characters_per_token', 4);
        $overlapLength = (int) config('knowledge.chunking.overlap_tokens', 100)
            * (int) config('knowledge.chunking.characters_per_token', 4);
        $normalizedMarkdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
        $blocks = preg_split('/\R{2,}/u', $normalizedMarkdown) ?: [];
        $blocks = array_values(array_filter(array_map('trim', $blocks), static fn (string $block): bool => $block !== ''));
        $chunks = [];
        $current = '';

        foreach ($blocks as $block) {
            foreach ($this->splitLongBlock($block, $targetLength) as $part) {
                $candidate = $current === '' ? $part : $current."\n\n{$part}";

                if ($current !== '' && mb_strlen($candidate) > $targetLength) {
                    $chunks[] = $current;
                    $current = $this->overlap($current, $overlapLength);
                    $candidate = $current === '' ? $part : $current."\n\n{$part}";
                }

                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * @return array<int, string>
     */
    private function splitLongBlock(string $block, int $targetLength): array
    {
        if (mb_strlen($block) <= $targetLength) {
            return [$block];
        }

        $words = preg_split('/\s+/u', $block) ?: [];
        $parts = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : "{$current} {$word}";

            if ($current !== '' && mb_strlen($candidate) > $targetLength) {
                $parts[] = $current;
                $current = $word;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private function overlap(string $chunk, int $overlapLength): string
    {
        if (mb_strlen($chunk) <= $overlapLength) {
            return $chunk;
        }

        $overlap = mb_substr($chunk, -$overlapLength);

        return preg_replace('/^\S*\s+/u', '', $overlap) ?: $overlap;
    }
}
