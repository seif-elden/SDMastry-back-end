<?php

namespace Tests\Unit\Services\RAG;

use App\DTO\RagContext;
use App\Services\RAG\ChromaClient;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\RagRetrievalService;
use Mockery;
use Tests\TestCase;

class RagRetrievalServiceTest extends TestCase
{
    private ChromaClient $chromaClient;

    private EmbeddingService $embeddingService;

    private RagRetrievalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chromaClient = Mockery::mock(ChromaClient::class);
        $this->embeddingService = Mockery::mock(EmbeddingService::class);
        $this->service = new RagRetrievalService($this->chromaClient, $this->embeddingService);

        config([
            'rag.collection_books' => 'test_books',
            'rag.collection_answers' => 'test_answers',
            'rag.context_chunks' => 5,
            'rag.answer_chunks' => 3,
        ]);
    }

    public function test_retrieves_correct_chunks_and_formats_context(): void
    {
        $fakeEmbedding = [0.1, 0.2, 0.3];

        $this->embeddingService
            ->shouldReceive('embed')
            ->once()
            ->andReturn($fakeEmbedding);

        $this->chromaClient
            ->shouldReceive('query')
            ->with('test_books', $fakeEmbedding, 5)
            ->once()
            ->andReturn([
                'ids' => ['book-chunk-0'],
                'documents' => ['CAP theorem states...'],
                'metadatas' => [['book' => 'DDIA', 'chapter_hint' => 'Chapter 9']],
                'distances' => [0.3],
            ]);

        $this->chromaClient
            ->shouldReceive('query')
            ->with('test_answers', $fakeEmbedding, 3)
            ->once()
            ->andReturn([
                'ids' => ['model-answer-1'],
                'documents' => ['A model answer about CAP...'],
                'metadatas' => [['topic_slug' => 'cap-theorem']],
                'distances' => [0.2],
            ]);

        $result = $this->service->retrieve('My answer about CAP', 'CAP Theorem', 'Distributed systems');

        $this->assertInstanceOf(RagContext::class, $result);
        $this->assertCount(1, $result->bookChunks);
        $this->assertCount(1, $result->modelAnswers);

        $this->assertEquals('DDIA', $result->bookChunks[0]['book']);
        $this->assertEquals('Chapter 9', $result->bookChunks[0]['chapter']);
        $this->assertEqualsWithDelta(0.7, $result->bookChunks[0]['relevance_score'], 0.001);

        $this->assertEquals('cap-theorem', $result->modelAnswers[0]['topic']);
        $this->assertEqualsWithDelta(0.8, $result->modelAnswers[0]['relevance_score'], 0.001);

        $this->assertStringContainsString('=== Reference Material ===', $result->combinedContext);
        $this->assertStringContainsString('=== Model Answers from Learners ===', $result->combinedContext);
        $this->assertStringContainsString('CAP theorem states...', $result->combinedContext);
        $this->assertStringContainsString('A model answer about CAP...', $result->combinedContext);
    }

    public function test_handles_empty_results_gracefully(): void
    {
        $fakeEmbedding = [0.1, 0.2, 0.3];

        $this->embeddingService
            ->shouldReceive('embed')
            ->once()
            ->andReturn($fakeEmbedding);

        $this->chromaClient
            ->shouldReceive('query')
            ->andReturn([
                'ids' => [],
                'documents' => [],
                'metadatas' => [],
                'distances' => [],
            ]);

        $result = $this->service->retrieve('test answer', 'Test Topic', 'Test desc');

        $this->assertInstanceOf(RagContext::class, $result);
        $this->assertEmpty($result->bookChunks);
        $this->assertEmpty($result->modelAnswers);
        $this->assertEmpty($result->combinedContext);
    }

    public function test_handles_chroma_exception_gracefully(): void
    {
        $fakeEmbedding = [0.1, 0.2, 0.3];

        $this->embeddingService
            ->shouldReceive('embed')
            ->once()
            ->andReturn($fakeEmbedding);

        $this->chromaClient
            ->shouldReceive('query')
            ->andThrow(new \App\Exceptions\ChromaException('Connection refused'));

        $result = $this->service->retrieve('test answer', 'Test Topic', 'Test desc');

        $this->assertInstanceOf(RagContext::class, $result);
        $this->assertEmpty($result->bookChunks);
        $this->assertEmpty($result->modelAnswers);
    }
}
