<?php

namespace App\Services\Analytics;

use App\Models\StreakLog;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Models\UserTopicProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * @return array{
     *   progress: array{total: int, passed: int, core_passed: int, advanced_passed: int, completion_pct: float},
     *   streak: array{current: int, longest: int, last_active: string|null},
     *   category_breakdown: array<int, array{category: string, total: int, passed: int, completion_pct: float}>,
     *   score_timeline: array<int, array{topic_slug: string, topic_title: string, attempts: array<int, array{score: int|null, created_at: string|null}>}>,
     *   weak_areas: array<int, array{topic_slug: string, topic_title: string, best_score: int, attempts_count: int}>,
     *   time_spent: array<int, array{topic_slug: string, avg_minutes: float}>,
     *   activity_calendar: array<int, array{date: string, count: int}>
     * }
     */
    public function getUserAnalytics(int $userId): array
    {
        return Cache::remember("analytics:user:{$userId}", 300, function () use ($userId): array {
            $user = User::query()->findOrFail($userId);

            $totalTopics = Topic::query()->count();

            $progressRows = UserTopicProgress::query()
                ->where('user_id', $userId)
                ->with('topic:id,slug,title,category,section')
                ->get();

            $passedRows = $progressRows->where('passed', true);
            $passedCount = $passedRows->count();
            $corePassed = $passedRows->filter(fn (UserTopicProgress $row) => $row->topic?->section === 'core')->count();
            $advancedPassed = $passedRows->filter(fn (UserTopicProgress $row) => $row->topic?->section === 'advanced')->count();

            $topicCategoryTotals = Topic::query()
                ->select('category', DB::raw('COUNT(*) as total'))
                ->groupBy('category')
                ->orderBy('category')
                ->get();

            $passedCategoryCounts = UserTopicProgress::query()
                ->join('topics', 'topics.id', '=', 'user_topic_progress.topic_id')
                ->where('user_topic_progress.user_id', $userId)
                ->where('user_topic_progress.passed', true)
                ->select('topics.category', DB::raw('COUNT(*) as passed'))
                ->groupBy('topics.category')
                ->pluck('passed', 'category');

            $categoryBreakdown = $topicCategoryTotals->map(function ($row) use ($passedCategoryCounts): array {
                $total = (int) $row->total;
                $passed = (int) ($passedCategoryCounts[$row->category] ?? 0);

                return [
                    'category' => (string) $row->category,
                    'total' => $total,
                    'passed' => $passed,
                    'completion_pct' => $this->percentage($passed, $total),
                ];
            })->values()->all();

            $timelineAttempts = TopicAttempt::query()
                ->where('user_id', $userId)
                ->with('topic:id,slug,title')
                ->orderBy('completed_at')
                ->orderBy('id')
                ->get();


            $weakAreas = UserTopicProgress::query()
                ->where('user_id', $userId)
                ->where('attempts_count', '>=', 1)
                ->with('topic:id,slug,title')
                ->orderBy('best_score')
                ->limit(5)
                ->get()
                ->map(fn (UserTopicProgress $row) => [
                    'topic_slug' => (string) ($row->topic?->slug ?? ''),
                    'topic_title' => (string) ($row->topic?->title ?? ''),
                    'category' => (string) ($row->topic?->category ?? ''),
                    'best_score' => (int) $row->best_score,
                    'attempts_count' => (int) $row->attempts_count,
                ])
                ->values()
                ->all();

            $startDate = now()->subDays(364)->toDateString();
            $activityCalendar = StreakLog::query()
                ->where('user_id', $userId)
                ->where('activity_date', '>=', $startDate)
                ->select('activity_date as date', DB::raw('COUNT(*) as count'))
                ->groupBy('activity_date')
                ->orderBy('activity_date')
                ->get()
                ->map(fn ($row) => [
                    'date' => Carbon::parse($row->date)->toDateString(),
                    'count' => (int) $row->count,
                ])
                ->values()
                ->all();

            // Average score across all attempts with a score
            $avgScore = TopicAttempt::query()
                ->where('user_id', $userId)
                ->whereNotNull('score')
                ->avg('score') ?? 0;

            // Last 7 days streak data
            $last7Days = collect(range(6, 0))->map(function (int $daysAgo) use ($userId): array {
                $date = now()->subDays($daysAgo)->toDateString();
                $active = StreakLog::query()
                    ->where('user_id', $userId)
                    ->where('activity_date', $date)
                    ->exists();

                return ['date' => $date, 'active' => $active];
            })->values()->all();

            // Flatten score_timeline: grouped → flat with attempted_at
            $flatTimeline = $timelineAttempts->map(fn (TopicAttempt $attempt) => [
                'topic_slug' => (string) ($attempt->topic?->slug ?? ''),
                'topic_title' => (string) ($attempt->topic?->title ?? ''),
                'attempted_at' => optional($attempt->completed_at ?? $attempt->started_at)?->toISOString(),
                'score' => (int) ($attempt->score ?? 0),
            ])->values()->all();

            // Add topic_title to time_spent and rename avg_minutes
            $timeSpentWithTitle = TopicAttempt::query()
                ->join('topics', 'topics.id', '=', 'topic_attempts.topic_id')
                ->where('topic_attempts.user_id', $userId)
                ->whereNotNull('topic_attempts.started_at')
                ->whereNotNull('topic_attempts.completed_at')
                ->select(
                    'topics.slug as topic_slug',
                    'topics.title as topic_title',
                    DB::raw('AVG((julianday(topic_attempts.completed_at) - julianday(topic_attempts.started_at)) * 24 * 60) as average_minutes')
                )
                ->groupBy('topics.slug', 'topics.title')
                ->orderBy('topics.slug')
                ->get()
                ->map(fn ($row) => [
                    'topic_slug' => (string) $row->topic_slug,
                    'topic_title' => (string) $row->topic_title,
                    'average_minutes' => round((float) $row->average_minutes, 2),
                ])
                ->values()
                ->all();

            return [
                'overview' => [
                    'topics_mastered' => $passedCount,
                    'total_topics' => $totalTopics,
                    'current_streak' => (int) $user->current_streak,
                    'longest_streak' => (int) $user->longest_streak,
                    'average_score' => round((float) $avgScore, 1),
                ],
                'category_heatmap' => collect($categoryBreakdown)->map(fn (array $row) => [
                    'category' => $row['category'],
                    'completed_topics' => $row['passed'],
                    'total_topics' => $row['total'],
                    'completion_percentage' => $row['completion_pct'],
                ])->values()->all(),
                'score_timeline' => $flatTimeline,
                'activity_calendar' => collect($activityCalendar)->map(fn (array $row) => [
                    'date' => $row['date'],
                    'activity_count' => $row['count'],
                ])->values()->all(),
                'weak_areas' => $weakAreas,
                'time_spent' => $timeSpentWithTitle,
                'streak' => [
                    'current' => (int) $user->current_streak,
                    'longest' => (int) $user->longest_streak,
                    'last_7_days' => $last7Days,
                ],
            ];
        });
    }

    private function percentage(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }
}
