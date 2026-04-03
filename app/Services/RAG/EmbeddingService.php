<?php

namespace App\Services\RAG;

use App\Exceptions\ChromaException;
use Illuminate\Support\Facades\Http;

class EmbeddingService
{
    private string $baseUrl;

    private string $model;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('rag.ollama_base_url'), '/');
        $this->model = config('rag.ollama_embedder');
    }

    /**
     * @return array<int, float>
     */
    public function embed(string $text): array
    {
        $response = Http::timeout(60)->post("{$this->baseUrl}/api/embeddings", [
            'model' => $this->model,
            'prompt' => $text,
        ]);

        if (! $response->successful()) {
            throw new ChromaException(
                "Ollama embedding failed: {$response->body()}"
            );
        }

        return $response->json('embedding');
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $text) => $this->embed($text), $texts);
    }
}
