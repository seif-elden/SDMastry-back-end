<?php

namespace App\Jobs;

use App\Services\RAG\ChromaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestBookChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  string  $collection
     * @param  array<int, array{text: string, embedding: array, metadata: array}>  $chunks
     */
    public function __construct(
        public string $collection,
        public array $chunks,
    ) {}

    public function handle(ChromaClient $chromaClient): void
    {
        try {
            $chromaClient->createCollectionIfNotExists($this->collection);

            $documents = [];
            $embeddings = [];
            $metadatas = [];
            $ids = [];

            foreach ($this->chunks as $chunk) {
                $documents[] = $chunk['text'];
                $embeddings[] = $chunk['embedding'];
                $metadatas[] = $chunk['metadata'];
                $ids[] = $chunk['id'];
            }

            $chromaClient->addDocuments($this->collection, $documents, $embeddings, $metadatas, $ids);
        } catch (\Throwable $e) {
            Log::error('IngestBookChunkJob failed', [
                'collection' => $this->collection,
                'chunk_count' => count($this->chunks),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
