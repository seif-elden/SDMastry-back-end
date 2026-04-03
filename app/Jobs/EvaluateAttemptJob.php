<?php

namespace App\Jobs;

use App\Models\ChatSession;
use App\Models\TopicAttempt;
use App\Services\BadgeService;
use App\Services\Evaluation\AttemptEvaluationService;
use App\Services\Progress\UserProgressService;
use App\Services\StreakService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EvaluateAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(
        public int $attemptId,
    ) {}

    public function handle(AttemptEvaluationService $evaluationService, UserProgressService $progressService): void
    {
        try {
            $attempt = TopicAttempt::findOrFail($this->attemptId);
            $attempt->loadMissing('topic');

            $attempt->update([
                'status' => 'evaluating',
            ]);

            $result = $evaluationService->evaluate($attempt);

            $attempt->update([
                'score' => $result->score,
                'passed' => $result->passed,
                'evaluation' => $result->toArray(),
                'status' => 'complete',
                'completed_at' => now(),
            ]);

            $chatSession = ChatSession::firstOrCreate(
                ['topic_attempt_id' => $attempt->id],
                ['created_at' => now()],
            );

            if (! $chatSession->messages()->exists()) {
                $chatSession->messages()->create([
                    'role' => 'assistant',
                    'content' => $result->notes,
                    'created_at' => now(),
                ]);
            }

            $progressService->updateAfterAttempt(
                $attempt->user_id,
                $attempt->topic_id,
                $result->score,
            );

            $attempt->loadMissing('user');

            app(StreakService::class)->recordActivity($attempt->user);
            app(BadgeService::class)->checkAndAward($attempt->user, $attempt);

            Cache::forget("analytics:user:{$attempt->user_id}");
            Cache::forget("progress:user:{$attempt->user_id}");
            Cache::forget("attempt:status:{$attempt->id}");
        } catch (\Throwable $e) {
            $isFinalAttempt = $this->attempts() >= $this->tries;

            if ($isFinalAttempt) {
                TopicAttempt::whereKey($this->attemptId)->update([
                    'status' => 'failed',
                    'evaluation' => [
                        'error' => $e->getMessage(),
                    ],
                ]);
            }

            Log::error('EvaluateAttemptJob failed', [
                'attempt_id' => $this->attemptId,
                'attempt_number' => $this->attempts(),
                'is_final_attempt' => $isFinalAttempt,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
