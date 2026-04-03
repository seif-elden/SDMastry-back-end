<?php

namespace Tests\Unit\Services\RAG;

use App\Services\RAG\ChunkSplitter;
use PHPUnit\Framework\TestCase;

class ChunkSplitterTest extends TestCase
{
    private ChunkSplitter $splitter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->splitter = new ChunkSplitter;
    }

    public function test_splits_text_into_correct_chunk_sizes(): void
    {
        $words = array_map(fn ($i) => "word{$i}", range(1, 100));
        $text = implode(' ', $words);

        $chunks = $this->splitter->split($text, 30, 5);

        // Each chunk should have at most 30 words
        foreach ($chunks as $chunk) {
            $wordCount = count(explode(' ', $chunk));
            $this->assertLessThanOrEqual(30, $wordCount);
        }
    }

    public function test_overlap_is_preserved_between_chunks(): void
    {
        $words = array_map(fn ($i) => "word{$i}", range(1, 50));
        $text = implode(' ', $words);

        $chunks = $this->splitter->split($text, 20, 5);

        $this->assertGreaterThan(1, count($chunks));

        // Extract last 5 words of first chunk and first 5 words of second chunk
        $firstChunkWords = explode(' ', $chunks[0]);
        $secondChunkWords = explode(' ', $chunks[1]);

        $lastFiveOfFirst = array_slice($firstChunkWords, -5);
        $firstFiveOfSecond = array_slice($secondChunkWords, 0, 5);

        $this->assertEquals($lastFiveOfFirst, $firstFiveOfSecond);
    }

    public function test_returns_single_chunk_for_short_text(): void
    {
        $text = 'This is a short text with few words.';

        $chunks = $this->splitter->split($text, 512, 50);

        $this->assertCount(1, $chunks);
        $this->assertEquals('This is a short text with few words.', $chunks[0]);
    }

    public function test_returns_empty_array_for_empty_text(): void
    {
        $chunks = $this->splitter->split('', 512, 50);

        $this->assertCount(0, $chunks);
    }

    public function test_handles_whitespace_only_text(): void
    {
        $chunks = $this->splitter->split("   \n\t  ", 512, 50);

        $this->assertCount(0, $chunks);
    }

    public function test_all_words_are_covered(): void
    {
        $words = array_map(fn ($i) => "unique{$i}", range(1, 80));
        $text = implode(' ', $words);

        $chunks = $this->splitter->split($text, 25, 5);

        $allChunkWords = [];
        foreach ($chunks as $chunk) {
            foreach (explode(' ', $chunk) as $word) {
                $allChunkWords[$word] = true;
            }
        }

        foreach ($words as $word) {
            $this->assertArrayHasKey($word, $allChunkWords, "Word '{$word}' missing from chunks");
        }
    }
}
