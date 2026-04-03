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
        $modelAnswers = $this->queryModelAnswers($queryEmbedding);
        $combinedContext = $this->formatCombinedContext($bookChunks, $modelAnswers);

        return new RagContext(
            bookChunks: $bookChunks,
            modelAnswers: $modelAnswers,
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

    /**
     * @return array<int, array{text: string, topic: string, relevance_score: float}>
     */
    private function queryModelAnswers(array $queryEmbedding): array
    {
        $collection = config('rag.collection_answers');
        $nResults = config('rag.answer_chunks');

        try {
            $results = $this->chromaClient->query($collection, $queryEmbedding, $nResults);
        } catch (\Throwable) {
            return [];
        }

        $answers = [];
        foreach ($results['documents'] as $i => $doc) {
            $meta = $results['metadatas'][$i] ?? [];
            $answers[] = [
                'text' => $doc,
                'topic' => $meta['topic_slug'] ?? 'unknown',
                'relevance_score' => 1.0 - ($results['distances'][$i] ?? 1.0),
            ];
        }

        return $answers;
    }

    private function formatCombinedContext(array $bookChunks, array $modelAnswers): string
    {
        $parts = [];

        if (! empty($bookChunks)) {
            $bookTexts = array_map(
                fn ($chunk) => "[{$chunk['book']}] {$chunk['text']}",
                $bookChunks
            );
            $parts[] = "=== Reference Material ===\n" . implode("\n\n", $bookTexts);
        }

        if (! empty($modelAnswers)) {
            $answerTexts = array_map(
                fn ($answer) => "[Topic: {$answer['topic']}] {$answer['text']}",
                $modelAnswers
            );
            $parts[] = "=== Model Answers from Learners ===\n" . implode("\n\n", $answerTexts);
        }

        return implode("\n\n", $parts);
    }
}
