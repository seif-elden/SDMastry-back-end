<?php

namespace App\Console\Commands;

use App\Services\RAG\ChromaClient;
use App\Services\RAG\EmbeddingService;
use App\Services\RAG\RagRetrievalService;
use Illuminate\Console\Command;

class VerifyRagSetup extends Command
{
    protected $signature = 'rag:verify
        {--peek=25 : Number of documents to inspect from books collection}
        {--strict : Exit non-zero on warnings}';

    protected $description = 'Verify RAG setup health and basic data integrity';

    public function handle(
        EmbeddingService $embeddingService,
        ChromaClient $chromaClient,
        RagRetrievalService $ragRetrievalService,
    ): int {
        $fails = 0;
        $warns = 0;
        $checks = 0;

        $this->info('RAG verification started');

        $probeText = 'distributed systems consistency availability partition tolerance';
        $bookCollection = (string) config('rag.collection_books');
        $peekLimit = max(1, (int) $this->option('peek'));

        $embedding = [];
        $checks++;
        try {
            $embedding = $embeddingService->embed($probeText);
            $valid = is_array($embedding) && count($embedding) > 0 && is_numeric($embedding[0] ?? null);

            if (! $valid) {
                $fails++;
                $this->error('FAIL: Embedding service returned invalid vector.');
            } else {
                $this->line('PASS: Embedding service reachable and vector is valid.');
            }
        } catch (\Throwable $exception) {
            $fails++;
            $this->error('FAIL: Embedding service error: ' . $exception->getMessage());
        }

        $booksExists = false;
        $checks++;
        try {
            $booksExists = $chromaClient->collectionExists($bookCollection);
            if (! $booksExists) {
                $fails++;
                $this->error("FAIL: Books collection missing: {$bookCollection}");
            } else {
                $this->line("PASS: Books collection exists: {$bookCollection}");
            }
        } catch (\Throwable $exception) {
            $fails++;
            $this->error('FAIL: Could not verify books collection: ' . $exception->getMessage());
        }

        if ($booksExists) {
            $checks++;
            try {
                $peek = $chromaClient->peek($bookCollection, $peekLimit);
                $docs = is_array($peek['documents']) ? $peek['documents'] : [];
                $metas = is_array($peek['metadatas']) ? $peek['metadatas'] : [];

                if (count($docs) === 0) {
                    $warns++;
                    $this->warn('WARN: Books collection is empty. RAG retrieval will return no context.');
                } else {
                    $badDocs = 0;
                    $badMeta = 0;

                    foreach ($docs as $index => $doc) {
                        if (! is_string($doc) || trim($doc) === '') {
                            $badDocs++;
                        }

                        $meta = $metas[$index] ?? null;
                        if (! is_array($meta) || ! isset($meta['book']) || ! isset($meta['chunk_index'])) {
                            $badMeta++;
                        }
                    }

                    if ($badDocs > 0 || $badMeta > 0) {
                        $warns++;
                        $this->warn("WARN: Integrity issues in sampled docs (empty_docs={$badDocs}, bad_metadata={$badMeta}).");
                    } else {
                        $this->line('PASS: Sampled book documents and metadata look valid.');
                    }
                }
            } catch (\Throwable $exception) {
                $fails++;
                $this->error('FAIL: Could not inspect books collection documents: ' . $exception->getMessage());
            }
        }

        if (! empty($embedding) && $booksExists) {
            $checks++;
            try {
                $query = $chromaClient->query($bookCollection, $embedding, 3);
                $docs = is_array($query['documents']) ? $query['documents'] : [];
                $distances = is_array($query['distances']) ? $query['distances'] : [];

                if (count($docs) === 0) {
                    $warns++;
                    $this->warn('WARN: Query returned no book chunks for probe text.');
                } else {
                    $distanceValid = collect($distances)->every(fn ($value) => is_numeric($value));
                    if (! $distanceValid) {
                        $warns++;
                        $this->warn('WARN: Query returned non-numeric distance values.');
                    } else {
                        $this->line('PASS: Query retrieval from books collection works.');
                    }
                }
            } catch (\Throwable $exception) {
                $fails++;
                $this->error('FAIL: Chroma query failed: ' . $exception->getMessage());
            }

            $checks++;
            try {
                $context = $ragRetrievalService->retrieve(
                    userAnswer: 'Discuss CAP theorem trade-offs in real systems.',
                    topicTitle: 'CAP Theorem',
                    topicDescription: 'Consistency, availability, partition tolerance trade-offs.',
                );

                if (trim($context->combinedContext) === '') {
                    $warns++;
                    $this->warn('WARN: RagRetrievalService returned empty combined context.');
                } else {
                    $this->line('PASS: RagRetrievalService returned non-empty combined context.');
                }
            } catch (\Throwable $exception) {
                $fails++;
                $this->error('FAIL: RagRetrievalService failed: ' . $exception->getMessage());
            }
        }

        $this->newLine();
        $this->line("Checks: {$checks}, warnings: {$warns}, failures: {$fails}");

        if ($fails > 0) {
            $this->error('RAG verification failed. Fix failures before trusting evaluation quality.');

            return self::FAILURE;
        }

        if ($warns > 0) {
            $this->warn('RAG verification completed with warnings.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('RAG verification passed with no issues.');

        return self::SUCCESS;
    }
}
