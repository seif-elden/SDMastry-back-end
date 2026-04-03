<?php

namespace App\Console\Commands;

use App\Models\TopicAttempt;
use Illuminate\Console\Command;

class FixPlaceholderModelAnswers extends Command
{
    protected $signature = 'evaluation:fix-placeholder-answers {--dry-run}';

    protected $description = 'Find and remove placeholder notes from evaluation records';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $placeholders = [
            'comprehensive 200-400 word notes grounded in the reference material',
            'comprehensive 200-400 word model answer grounded in the reference material',
            'the answer should connect core definitions, trade-offs, and practical implementation details while grounding claims in the supplied reference context.',
        ];

        $query = TopicAttempt::where('status', 'complete')
            ->where('score', '>', 0)
            ->whereNotNull('evaluation');

        $attempts = $query->get();
        $fixedCount = 0;

        foreach ($attempts as $attempt) {
            $evaluation = $attempt->evaluation;

            if (! is_array($evaluation)) {
                continue;
            }

            $notes = strtolower(trim((string) ($evaluation['notes'] ?? $evaluation['model_answer'] ?? '')));

            $isPlaceholder = false;
            foreach ($placeholders as $placeholder) {
                if ($notes === $placeholder) {
                    $isPlaceholder = true;
                    break;
                }
            }

            if (! $isPlaceholder) {
                continue;
            }

            $fixedCount++;
            $this->line("Removing placeholder from attempt {$attempt->id} (score: {$attempt->score})");

            if (! $dryRun) {
                $evaluation['notes'] = '[Placeholder removed - re-evaluation needed]';
                $attempt->update(['evaluation' => $evaluation]);
            }
        }

        $mode = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$mode}Found and fixed {$fixedCount} attempts with placeholder notes.");

        return 0;
    }
}
