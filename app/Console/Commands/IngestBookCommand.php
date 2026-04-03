<?php

namespace App\Console\Commands;

use App\Jobs\IngestBookChunkJob;
use App\Services\RAG\ChunkSplitter;
use App\Services\RAG\EmbeddingService;
use Illuminate\Console\Command;
use Smalot\PdfParser\Parser as PdfParser;

class IngestBookCommand extends Command
{
    protected $signature = 'rag:ingest
        {--file= : Path to a PDF or TXT file (relative to storage/rag/books/ or absolute)}
        {--collection=books : ChromaDB collection name alias (books)}
        {--chunk-size= : Approximate tokens per chunk}
        {--overlap= : Token overlap between chunks}';

    protected $description = 'Ingest a book (PDF/TXT) into ChromaDB for RAG retrieval';

    public function handle(EmbeddingService $embeddingService, ChunkSplitter $splitter): int
    {
        $filePath = $this->resolveFilePath();

        if (! $filePath || ! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");

            return self::FAILURE;
        }

        $chunkSize = (int) ($this->option('chunk-size') ?: config('rag.default_chunk_size'));
        $overlap = (int) ($this->option('overlap') ?: config('rag.default_chunk_overlap'));
        $collectionAlias = $this->option('collection');
        $collection = $collectionAlias === 'books'
            ? config('rag.collection_books')
            : $collectionAlias;

        $this->info("Extracting text from: {$filePath}");
        $text = $this->extractText($filePath);

        if (empty(trim($text))) {
            $this->error('No text could be extracted from the file.');

            return self::FAILURE;
        }

        $chunks = $splitter->split($text, $chunkSize, $overlap);
        $totalChunks = count($chunks);
        $this->info("Split into {$totalChunks} chunks (size={$chunkSize}, overlap={$overlap})");

        $bookSlug = str(pathinfo($filePath, PATHINFO_FILENAME))->slug()->toString();
        $bookName = pathinfo($filePath, PATHINFO_FILENAME);
        $batchSize = config('rag.ingest_batch_size');

        $bar = $this->output->createProgressBar($totalChunks);
        $bar->setFormat("Ingesting [{$bookName}]: chunk %current%/%max% [%bar%] %percent%%");
        $bar->start();

        $batch = [];

        foreach ($chunks as $index => $chunkText) {
            $embedding = $embeddingService->embed($chunkText);

            $batch[] = [
                'text' => $chunkText,
                'embedding' => $embedding,
                'metadata' => [
                    'book' => $bookName,
                    'chapter_hint' => $this->guessChapter($chunkText),
                    'chunk_index' => $index,
                ],
                'id' => "{$bookSlug}-chunk-{$index}",
            ];

            $bar->advance();

            if (count($batch) >= $batchSize) {
                IngestBookChunkJob::dispatch($collection, $batch)->onQueue('rag');
                $batch = [];
            }
        }

        if (! empty($batch)) {
            IngestBookChunkJob::dispatch($collection, $batch)->onQueue('rag');
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Dispatched ingestion jobs for {$totalChunks} chunks.");

        return self::SUCCESS;
    }

    private function resolveFilePath(): ?string
    {
        $file = $this->option('file');

        if (! $file) {
            $this->error('Please provide a file path with --file=');

            return null;
        }

        if (str_starts_with($file, '/')) {
            return $file;
        }

        return storage_path("rag/books/{$file}");
    }

    private function extractText(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
        } else {
            $text = file_get_contents($filePath);
        }

        // Sanitize malformed UTF-8 characters from PDF extraction
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);

        return $text;
    }

    private function guessChapter(string $text): string
    {
        if (preg_match('/\bchapter\s+(\d+|[IVXLC]+)\b/i', $text, $matches)) {
            return "Chapter {$matches[1]}";
        }

        return 'unknown';
    }
}
