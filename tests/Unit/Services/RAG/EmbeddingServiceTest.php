<?php

namespace Tests\Unit\Services\RAG;

use App\Exceptions\ChromaException;
use App\Services\RAG\EmbeddingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'rag.ollama_base_url' => 'http://localhost:11434',
            'rag.ollama_embedder' => 'nomic-embed-text',
        ]);
    }

    public function test_embed_calls_correct_ollama_endpoint(): void
    {
        $fakeEmbedding = array_fill(0, 384, 0.1);

        Http::fake([
            'localhost:11434/api/embeddings' => Http::response([
                'embedding' => $fakeEmbedding,
            ]),
        ]);

        $service = new EmbeddingService;
        $result = $service->embed('test text');

        $this->assertCount(384, $result);
        $this->assertEquals($fakeEmbedding, $result);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:11434/api/embeddings'
                && $request['model'] === 'nomic-embed-text'
                && $request['prompt'] === 'test text';
        });
    }

    public function test_embed_returns_float_array(): void
    {
        $fakeEmbedding = [0.1, 0.2, 0.3, -0.5, 0.99];

        Http::fake([
            'localhost:11434/api/embeddings' => Http::response([
                'embedding' => $fakeEmbedding,
            ]),
        ]);

        $service = new EmbeddingService;
        $result = $service->embed('hello');

        foreach ($result as $value) {
            $this->assertIsFloat($value);
        }
    }

    public function test_embed_throws_on_failure(): void
    {
        Http::fake([
            'localhost:11434/api/embeddings' => Http::response('Server error', 500),
        ]);

        $this->expectException(ChromaException::class);

        $service = new EmbeddingService;
        $service->embed('test');
    }

    public function test_embed_batch_returns_multiple_embeddings(): void
    {
        $fakeEmbedding = array_fill(0, 384, 0.1);

        Http::fake([
            'localhost:11434/api/embeddings' => Http::response([
                'embedding' => $fakeEmbedding,
            ]),
        ]);

        $service = new EmbeddingService;
        $results = $service->embedBatch(['text one', 'text two', 'text three']);

        $this->assertCount(3, $results);

        foreach ($results as $embedding) {
            $this->assertCount(384, $embedding);
        }
    }
}
