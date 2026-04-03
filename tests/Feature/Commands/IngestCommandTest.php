<?php

namespace Tests\Feature\Commands;

use App\Jobs\IngestBookChunkJob;
use App\Services\RAG\EmbeddingService;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class IngestCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_ingest_command_processes_txt_file_and_dispatches_jobs(): void
    {
        $filePath = storage_path('rag/books/test-book.txt');
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Create a test file with enough words to produce multiple chunks
        $words = array_map(fn ($i) => "word{$i}", range(1, 100));
        file_put_contents($filePath, implode(' ', $words));

        $fakeEmbedding = array_fill(0, 384, 0.1);

        $mockEmbedding = Mockery::mock(EmbeddingService::class);
        $mockEmbedding->shouldReceive('embed')->andReturn($fakeEmbedding);
        $this->app->instance(EmbeddingService::class, $mockEmbedding);

        $this->artisan('rag:ingest', [
            '--file' => 'test-book.txt',
            '--chunk-size' => 30,
            '--overlap' => 5,
        ])->assertSuccessful();

        Queue::assertPushed(IngestBookChunkJob::class);

        // Cleanup
        @unlink($filePath);
    }

    public function test_ingest_command_fails_with_missing_file(): void
    {
        $this->artisan('rag:ingest', [
            '--file' => 'nonexistent.txt',
        ])->assertFailed();
    }

    public function test_ingest_command_fails_without_file_option(): void
    {
        $this->artisan('rag:ingest')
            ->assertFailed();
    }

    public function test_ingest_command_dispatches_correct_batch_sizes(): void
    {
        $filePath = storage_path('rag/books/batch-test.txt');
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 200 words with chunk-size=10 and overlap=2 → many chunks, should batch into groups of 20
        $words = array_map(fn ($i) => "word{$i}", range(1, 200));
        file_put_contents($filePath, implode(' ', $words));

        $fakeEmbedding = array_fill(0, 384, 0.1);

        $mockEmbedding = Mockery::mock(EmbeddingService::class);
        $mockEmbedding->shouldReceive('embed')->andReturn($fakeEmbedding);
        $this->app->instance(EmbeddingService::class, $mockEmbedding);

        config(['rag.ingest_batch_size' => 20]);

        $this->artisan('rag:ingest', [
            '--file' => 'batch-test.txt',
            '--chunk-size' => 10,
            '--overlap' => 2,
        ])->assertSuccessful();

        Queue::assertPushed(IngestBookChunkJob::class, function ($job) {
            return count($job->chunks) <= 20;
        });

        // Cleanup
        @unlink($filePath);
    }

    public function test_ingest_command_accepts_absolute_path(): void
    {
        $filePath = storage_path('rag/books/absolute-test.txt');
        $dir = dirname($filePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, 'Some test content for absolute path testing with enough words.');

        $fakeEmbedding = array_fill(0, 384, 0.1);

        $mockEmbedding = Mockery::mock(EmbeddingService::class);
        $mockEmbedding->shouldReceive('embed')->andReturn($fakeEmbedding);
        $this->app->instance(EmbeddingService::class, $mockEmbedding);

        $this->artisan('rag:ingest', [
            '--file' => $filePath,
        ])->assertSuccessful();

        Queue::assertPushed(IngestBookChunkJob::class);

        // Cleanup
        @unlink($filePath);
    }
}
