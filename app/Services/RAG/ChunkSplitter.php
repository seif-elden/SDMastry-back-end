<?php

namespace App\Services\RAG;

class ChunkSplitter
{
    /**
     * Split text into chunks of approximately $chunkSize tokens with $overlap token overlap.
     * Uses whitespace-based token approximation (1 token ≈ 1 word).
     *
     * @return array<int, string>
     */
    public function split(string $text, int $chunkSize, int $overlap): array
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return [];
        }

        if (count($words) <= $chunkSize) {
            return [implode(' ', $words)];
        }

        $chunks = [];
        $start = 0;
        $step = max(1, $chunkSize - $overlap);

        while ($start < count($words)) {
            $chunk = array_slice($words, $start, $chunkSize);
            $chunks[] = implode(' ', $chunk);
            $start += $step;
        }

        return $chunks;
    }
}
