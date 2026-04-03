<?php

namespace App\Console\Commands;

use App\Services\RAG\ChromaClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetAttemptsFresh extends Command
{
    protected $signature = 'attempts:reset-fresh {--yes : Skip confirmation prompt} {--keep-progress : Keep user_topic_progress records}';

    protected $description = 'Delete attempts, chats, and related attempt data so users can start fresh';

    public function __construct(
        private readonly ChromaClient $chromaClient,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->option('yes')) {
            $confirmed = $this->confirm('This will permanently delete attempts/chats (and progress unless --keep-progress). Continue?', false);
            if (! $confirmed) {
                $this->info('Cancelled. No data was changed.');

                return self::SUCCESS;
            }
        }

        $tables = [
            'chat_messages',
            'chat_sessions',
            'topic_attempts',
        ];

        if (! $this->option('keep-progress')) {
            $tables[] = 'user_topic_progress';
        }

        DB::beginTransaction();

        try {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $this->truncateTable($table);
                $this->line("Cleared {$table}");
            }

            $this->clearAttemptRelatedQueueEntries();

            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error('Reset failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->clearAttemptRagCollection();

        $this->info('Attempts reset complete. Fresh start is ready.');

        return self::SUCCESS;
    }

    private function truncateTable(string $table): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::table($table)->delete();
            DB::statement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);

            return;
        }

        DB::table($table)->truncate();
    }

    private function clearAttemptRelatedQueueEntries(): void
    {
        if (Schema::hasTable('jobs')) {
            DB::table('jobs')
                ->where('payload', 'like', '%EvaluateAttemptJob%')
                ->orWhere('payload', 'like', '%StoreModelAnswerInRagJob%')
                ->delete();
            $this->line('Cleared queued attempt jobs from jobs table');
        }

        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')
                ->where('payload', 'like', '%EvaluateAttemptJob%')
                ->orWhere('payload', 'like', '%StoreModelAnswerInRagJob%')
                ->delete();
            $this->line('Cleared attempt entries from failed_jobs table');
        }
    }

    private function clearAttemptRagCollection(): void
    {
        $collection = (string) config('rag.collection_answers');

        try {
            $deleted = $this->chromaClient->deleteCollectionIfExists($collection);
            $this->line($deleted
                ? "Deleted RAG collection '{$collection}' for attempt model answers"
                : "RAG collection '{$collection}' was already empty or missing");
        } catch (\Throwable $exception) {
            $this->warn("Could not clear RAG collection '{$collection}': {$exception->getMessage()}");
        }
    }
}
