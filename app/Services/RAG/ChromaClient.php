<?php

namespace App\Services\RAG;

use App\Exceptions\ChromaException;
use Illuminate\Support\Facades\Http;

class ChromaClient
{
    private string $baseUrl;
    private ?string $apiVersion = null;
    private int $timeoutSeconds = 60;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('rag.chroma_base_url'), '/');
    }

    public function createCollectionIfNotExists(string $collection): void
    {
        if ($this->collectionExists($collection)) {
            return;
        }

        $response = $this->request()->post($this->collectionsEndpoint(), [
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
        $response = $this->request()->get($this->collectionsEndpoint());

        if (! $response->successful()) {
            throw new ChromaException(
                "Failed to list collections: {$response->body()}"
            );
        }

        $collections = $response->json();

        return collect($collections)->contains(fn ($c) => $c['name'] === $collection);
    }

    public function deleteCollectionIfExists(string $collection): bool
    {
        if (! $this->collectionExists($collection)) {
            return false;
        }

        $response = $this->request()->delete($this->collectionByNameEndpoint($collection));

        if (! $response->successful()) {
            throw new ChromaException(
                "Failed to delete collection '{$collection}': {$response->body()}"
            );
        }

        return true;
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

        $response = $this->request()->post($this->collectionOperationEndpoint($collectionId, 'upsert'), [
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

        $response = $this->request()->post($this->collectionOperationEndpoint($collectionId, 'query'), $payload);

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

    /**
     * @return array{ids: array, documents: array, metadatas: array}
     */
    public function peek(string $collection, int $limit = 25): array
    {
        $collectionId = $this->getCollectionId($collection);

        $response = $this->request()->post($this->collectionOperationEndpoint($collectionId, 'get'), [
            'limit' => max(1, $limit),
            'include' => ['documents', 'metadatas'],
        ]);

        if (! $response->successful()) {
            throw new ChromaException(
                "Failed to peek collection '{$collection}': {$response->body()}"
            );
        }

        $data = $response->json();

        return [
            'ids' => $data['ids'] ?? [],
            'documents' => $data['documents'] ?? [],
            'metadatas' => $data['metadatas'] ?? [],
        ];
    }

    private function getCollectionId(string $collection): string
    {
        $response = $this->request()->get($this->collectionByNameEndpoint($collection));

        if (! $response->successful()) {
            throw new ChromaException(
                "Collection '{$collection}' not found: {$response->body()}"
            );
        }

        $collectionId = $response->json('id');

        if (! is_string($collectionId) || trim($collectionId) === '') {
            throw new ChromaException("Collection '{$collection}' did not return a valid collection id.");
        }

        return $collectionId;
    }

    private function detectApiVersion(): string
    {
        if ($this->apiVersion !== null) {
            return $this->apiVersion;
        }

        $v2CollectionsUrl = "{$this->baseUrl}/api/v2/tenants/default_tenant/databases/default_database/collections";
        $v2Response = $this->request()->get($v2CollectionsUrl);

        if ($v2Response->successful()) {
            $this->apiVersion = 'v2';

            return $this->apiVersion;
        }

        $v1CollectionsUrl = "{$this->baseUrl}/api/v1/collections";
        $v1Response = $this->request()->get($v1CollectionsUrl);

        if ($v1Response->successful()) {
            $this->apiVersion = 'v1';

            return $this->apiVersion;
        }

        throw new ChromaException(
            'Unable to detect Chroma API version. '
            . "v2 response: {$v2Response->status()} {$v2Response->body()}; "
            . "v1 response: {$v1Response->status()} {$v1Response->body()}"
        );
    }

    private function collectionsEndpoint(): string
    {
        return $this->detectApiVersion() === 'v2'
            ? "{$this->baseUrl}/api/v2/tenants/default_tenant/databases/default_database/collections"
            : "{$this->baseUrl}/api/v1/collections";
    }

    private function collectionByNameEndpoint(string $collection): string
    {
        return $this->collectionsEndpoint() . '/' . $collection;
    }

    private function collectionOperationEndpoint(string $collectionId, string $operation): string
    {
        return $this->collectionsEndpoint() . '/' . $collectionId . '/' . $operation;
    }

    private function request()
    {
        return Http::timeout($this->timeoutSeconds);
    }
}
