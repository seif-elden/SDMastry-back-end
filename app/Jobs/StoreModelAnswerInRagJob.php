<?php

namespace App\Jobs;

use App\Services\RAG\ChromaClient;
use App\Services\RAG\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StoreModelAnswerInRagJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $attemptId,
        public string $modelAnswer,
        public int $topicId,
        public string $topicTitle,
    ) {}

    public function handle(EmbeddingService $embeddingService, ChromaClient $chromaClient): void
    {
        try {
            $collection = config('rag.collection_answers');
            $chromaClient->createCollectionIfNotExists($collection);

            $embedding = $embeddingService->embed($this->modelAnswer);

            $topicSlug = str($this->topicTitle)->slug()->toString();

            $chromaClient->addDocuments(
                $collection,
                [$this->modelAnswer],
                [$embedding],
                [[
                    'topic_id' => $this->topicId,
                    'topic_slug' => $topicSlug,
                    'attempt_id' => $this->attemptId,
                    'created_at' => now()->toISOString(),
                ]],
                ["model-answer-attempt-{$this->attemptId}"],
            );
        } catch (\Throwable $e) {
            Log::error('StoreModelAnswerInRagJob failed', [
                'attempt_id' => $this->attemptId,
                'topic_id' => $this->topicId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
