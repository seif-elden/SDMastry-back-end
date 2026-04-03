<?php

namespace App\Services\RAG;

use App\DTO\RagContext;

class RagRetrievalService
{
    public function __construct(
        private ChromaClient $chromaClient,
        private EmbeddingService $embeddingService,
    ) {}

    public function retrieve(string $userAnswer, string $topicTitle, string $topicDescription): RagContext
    {
        $queryText = "{$topicTitle}: {$userAnswer}";
        $queryEmbedding = $this->embeddingService->embed($queryText);

        $bookChunks = $this->queryBooks($queryEmbedding);
        $combinedContext = $this->formatCombinedContext($bookChunks);

        return new RagContext(
            bookChunks: $bookChunks,
            modelAnswers: [],
            combinedContext: $combinedContext,
        );
    }

    /**
     * @return array<int, array{text: string, book: string, chapter: string, relevance_score: float}>
     */
    private function queryBooks(array $queryEmbedding): array
    {
        $collection = config('rag.collection_books');
        $nResults = config('rag.context_chunks');

        try {
            $results = $this->chromaClient->query($collection, $queryEmbedding, $nResults);
        } catch (\Throwable) {
            return [];
        }

        $chunks = [];
        foreach ($results['documents'] as $i => $doc) {
            $meta = $results['metadatas'][$i] ?? [];
            $chunks[] = [
                'text' => $doc,
                'book' => $meta['book'] ?? 'unknown',
                'chapter' => $meta['chapter_hint'] ?? 'unknown',
                'relevance_score' => 1.0 - ($results['distances'][$i] ?? 1.0),
            ];
        }

        return $chunks;
    }

    private function formatCombinedContext(array $bookChunks): string
    {
        $parts = [];

        if (! empty($bookChunks)) {
            $bookTexts = array_map(
                fn ($chunk) => "[{$chunk['book']}] {$chunk['text']}",
                $bookChunks
            );
            $parts[] = "=== Reference Material ===\n" . implode("\n\n", $bookTexts);
        }

        return implode("\n\n", $parts);
    }
}
