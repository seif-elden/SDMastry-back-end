<?php

namespace App\Services\RAG;

use App\Exceptions\ChromaException;
use Illuminate\Support\Facades\Http;

class ChromaClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('rag.chroma_base_url'), '/');
    }

    public function createCollectionIfNotExists(string $collection): void
    {
        if ($this->collectionExists($collection)) {
            return;
        }

        $response = Http::post("{$this->baseUrl}/api/v1/collections", [
            'name' => $collection,
            'get_or_create' => true,
        ]);

        if (! $response->successful()) {
            throw new ChromaException(
                "Failed to create collection '{$collection}': {$response->body()}"
            );
        }
    }

    public function collectionExists(string $collection): bool
    {
        $response = Http::get("{$this->baseUrl}/api/v1/collections");

        if (! $response->successful()) {
            throw new ChromaException(
                "Failed to list collections: {$response->body()}"
            );
        }

        $collections = $response->json();

        return collect($collections)->contains(fn ($c) => $c['name'] === $collection);
    }

    /**
     * @param  array<int, string>  $documents
     * @param  array<int, array<int, float>>  $embeddings
     * @param  array<int, array<string, mixed>>  $metadatas
     * @param  array<int, string>  $ids
     */
    public function addDocuments(string $collection, array $documents, array $embeddings, array $metadatas, array $ids): void
    {
        $collectionId = $this->getCollectionId($collection);

        $response = Http::post("{$this->baseUrl}/api/v1/collections/{$collectionId}/upsert", [
            'documents' => $documents,
            'embeddings' => $embeddings,
            'metadatas' => $metadatas,
            'ids' => $ids,
        ]);

        if (! $response->successful()) {
            throw new ChromaException(
                "Failed to add documents to '{$collection}': {$response->body()}"
            );
        }
    }

    /**
     * @param  array<int, float>  $queryEmbedding
     * @param  array<string, mixed>  $where
     * @return array{ids: array, documents: array, metadatas: array, distances: array}
     */
    public function query(string $collection, array $queryEmbedding, int $nResults, array $where = []): array
    {
        $collectionId = $this->getCollectionId($collection);

        $payload = [
            'query_embeddings' => [$queryEmbedding],
            'n_results' => $nResults,
            'include' => ['documents', 'metadatas', 'distances'],
        ];

        if (! empty($where)) {
            $payload['where'] = $where;
        }

        $response = Http::post("{$this->baseUrl}/api/v1/collections/{$collectionId}/query", $payload);

        if (! $response->successful()) {
            throw new ChromaException(
                "Failed to query collection '{$collection}': {$response->body()}"
            );
        }

        $data = $response->json();

        return [
            'ids' => $data['ids'][0] ?? [],
            'documents' => $data['documents'][0] ?? [],
            'metadatas' => $data['metadatas'][0] ?? [],
            'distances' => $data['distances'][0] ?? [],
        ];
    }

    private function getCollectionId(string $collection): string
    {
        $response = Http::get("{$this->baseUrl}/api/v1/collections/{$collection}");

        if (! $response->successful()) {
            throw new ChromaException(
                "Collection '{$collection}' not found: {$response->body()}"
            );
        }

        return $response->json('id');
    }
}
