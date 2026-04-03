<?php

namespace App\Services\Progress;

use App\Models\UserTopicProgress;
use Illuminate\Support\Facades\Cache;

class UserProgressService
{
    public function updateAfterAttempt(int $userId, int $topicId, int $score): void
    {
        $passThreshold = config('evaluation.pass_threshold');

        $progress = UserTopicProgress::firstOrCreate(
            ['user_id' => $userId, 'topic_id' => $topicId],
            ['best_score' => 0, 'attempts_count' => 0, 'passed' => false],
        );

        $progress->attempts_count++;

        if ($score > $progress->best_score) {
            $progress->best_score = $score;
        }

        if (!$progress->passed && $score >= $passThreshold) {
            $progress->passed = true;
            $progress->passed_at = now();
        }

        $progress->save();

        Cache::forget("progress:user:{$userId}");
    }

    public function getSummary(int $userId): array
    {
        return Cache::remember("progress:user:{$userId}", 300, function () use ($userId) {
            $progress = UserTopicProgress::where('user_id', $userId)
                ->with('topic:id,section')
                ->get();

            $passed = $progress->where('passed', true);

            return [
                'total_topics' => \App\Models\Topic::count(),
                'passed' => $passed->count(),
                'core_passed' => $passed->filter(fn ($p) => $p->topic?->section === 'core')->count(),
                'advanced_passed' => $passed->filter(fn ($p) => $p->topic?->section === 'advanced')->count(),
                'best_scores' => $progress->map(fn ($p) => [
                    'topic_id' => $p->topic_id,
                    'best_score' => $p->best_score,
                ])->values()->all(),
            ];
        });
    }
}
