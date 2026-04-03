<?php

namespace App\Jobs;

use App\Models\TopicAttempt;
use App\Services\Progress\UserProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $attemptId,
    ) {}

    public function handle(UserProgressService $progressService): void
    {
        try {
            $attempt = TopicAttempt::findOrFail($this->attemptId);

            // Stub: assign dummy evaluation data
            $score = 75;
            $attempt->update([
                'score' => $score,
                'passed' => $score >= config('evaluation.pass_threshold'),
                'evaluation' => [
                    'overall_score' => $score,
                    'feedback' => 'Stub evaluation — real LLM evaluation coming in a later phase.',
                    'criteria' => [
                        'understanding' => 80,
                        'completeness' => 70,
                        'accuracy' => 75,
                    ],
                ],
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $progressService->updateAfterAttempt(
                $attempt->user_id,
                $attempt->topic_id,
                $score,
            );
        } catch (\Throwable $e) {
            Log::error('EvaluateAttemptJob failed', [
                'attempt_id' => $this->attemptId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
