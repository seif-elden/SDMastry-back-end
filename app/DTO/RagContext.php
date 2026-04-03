<?php

namespace App\DTO;

class RagContext
{
    /**
     * @param  array<int, array{text: string, book: string, chapter: string, relevance_score: float}>  $bookChunks
     * @param  array<int, array{text: string, topic: string, relevance_score: float}>  $modelAnswers
     * @param  string  $combinedContext
     */
    public function __construct(
        public readonly array $bookChunks,
        public readonly array $modelAnswers,
        public readonly string $combinedContext,
    ) {}
}
