<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\TopicAttempt;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\UserTopicProgress;
use Carbon\CarbonInterface;

class BadgeService
{
    /**
     * @return array<int, array{slug: string, name: string, description: string, icon: string, group: string, earned_at: string}>
     */
    public function checkAndAward(User $user, TopicAttempt $attempt): array
    {
        $user->refresh();

        $allBadges = Badge::query()->get()->keyBy('slug');

        if ($allBadges->isEmpty()) {
            return [];
        }

        $awardedBadgeIds = UserBadge::query()
            ->where('user_id', $user->id)
            ->pluck('badge_id')
            ->all();

        $alreadyAwardedLookup = array_flip($awardedBadgeIds);

        $progressRows = UserTopicProgress::query()
            ->where('user_id', $user->id)
            ->with('topic:id,section')
            ->get();

        $passedRows = $progressRows->where('passed', true);

        $totalPassed = $passedRows->count();
        $corePassed = $passedRows->filter(fn (UserTopicProgress $row) => $row->topic?->section === 'core')->count();
        $advancedPassed = $passedRows->filter(fn (UserTopicProgress $row) => $row->topic?->section === 'advanced')->count();

        $perfectionistTopics = $progressRows
            ->where('best_score', 100)
            ->pluck('topic_id')
            ->unique()
            ->count();

        $highScorerRows = $progressRows->where('attempts_count', '>=', 1);
        $highScorerTopicCount = $highScorerRows->count();
        $highScorerAverage = $highScorerTopicCount > 0
            ? (float) $highScorerRows->avg('best_score')
            : 0.0;

        $closeCallScore = (int) config('gamification.badges.close_call_score', 80);
        $comebackFailureThreshold = $closeCallScore;
        $streakBadges = (array) config('gamification.badges.streak_badges', [3, 7, 30, 100]);

        $hadPreviousFailure = TopicAttempt::query()
            ->where('user_id', $user->id)
            ->where('topic_id', $attempt->topic_id)
            ->where('id', '<', $attempt->id)
            ->where('score', '<', $comebackFailureThreshold)
            ->exists();

        $deepDiverThreshold = (int) config('gamification.badges.deep_diver_messages', 10);
        $hasDeepDiveSession = ChatMessage::query()
            ->join('chat_sessions', 'chat_sessions.id', '=', 'chat_messages.chat_session_id')
            ->join('topic_attempts', 'topic_attempts.id', '=', 'chat_sessions.topic_attempt_id')
            ->where('topic_attempts.user_id', $user->id)
            ->groupBy('chat_messages.chat_session_id')
            ->havingRaw('COUNT(*) >= ?', [$deepDiverThreshold])
            ->exists();

        $requiredProviderCount = (int) config('gamification.badges.multi_agent_providers', 3);
        $providerCount = ChatSession::query()
            ->whereNotNull('provider')
            ->whereHas('topicAttempt', fn ($query) => $query->where('user_id', $user->id))
            ->distinct('provider')
            ->count('provider');

        $persistentAttemptsThreshold = (int) config('gamification.badges.persistent_attempts', 5);
        $sameTopicAttempts = TopicAttempt::query()
            ->where('user_id', $user->id)
            ->where('topic_id', $attempt->topic_id)
            ->count();

        $speedRunnerThreshold = (int) config('gamification.badges.speed_runner_seconds', 180);
        $durationSeconds = $this->attemptDurationInSeconds($attempt);

        $conditions = [
            'first-blood' => $totalPassed >= 1,
            'core-explorer' => $corePassed >= (int) config('gamification.badges.core_explorer_passed', 10),
            'core-cleared' => $corePassed === (int) config('gamification.totals.core_topics', 26),
            'advanced-scout' => $advancedPassed >= (int) config('gamification.badges.advanced_scout_passed', 5),
            'advanced-cleared' => $advancedPassed === (int) config('gamification.totals.advanced_topics', 27),
            'sd-master' => $totalPassed === (int) config('gamification.totals.all_topics', 53),
            'halfway' => $totalPassed >= (int) config('gamification.badges.halfway_passed', 26),
            'perfect-100' => (int) $attempt->score === 100,
            'perfectionist' => $perfectionistTopics >= (int) config('gamification.badges.perfectionist_topics', 5),
            'high-scorer' => $highScorerTopicCount >= (int) config('gamification.badges.high_scorer_topics', 10)
                && $highScorerAverage >= (float) config('gamification.badges.high_scorer_average', 90),
            'close-call' => (int) $attempt->score === $closeCallScore && (bool) $attempt->passed,
            'comeback-kid' => (bool) $attempt->passed && $hadPreviousFailure,
            'deep-diver' => $hasDeepDiveSession,
            'multi-agent' => $providerCount >= $requiredProviderCount,
            'persistent' => $sameTopicAttempts >= $persistentAttemptsThreshold,
            'speed-runner' => (bool) $attempt->passed && $durationSeconds !== null && $durationSeconds < $speedRunnerThreshold,
        ];

        foreach ($streakBadges as $days) {
            $days = (int) $days;
            $conditions['streak-' . $days] = (int) $user->current_streak >= $days;
        }

        $newlyAwarded = [];

        foreach ($conditions as $slug => $conditionMet) {
            if (! $conditionMet || ! isset($allBadges[$slug])) {
                continue;
            }

            $badge = $allBadges[$slug];

            if (isset($alreadyAwardedLookup[$badge->id])) {
                continue;
            }

            $earnedAt = now();

            UserBadge::query()->create([
                'user_id' => $user->id,
                'badge_id' => $badge->id,
                'earned_at' => $earnedAt,
            ]);

            $alreadyAwardedLookup[$badge->id] = true;

            $newlyAwarded[] = [
                'slug' => $badge->slug,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'group' => $badge->group,
                'earned_at' => $earnedAt->toISOString(),
            ];
        }

        return $newlyAwarded;
    }

    private function attemptDurationInSeconds(TopicAttempt $attempt): ?int
    {
        if (! $attempt->started_at instanceof CarbonInterface || ! $attempt->completed_at instanceof CarbonInterface) {
            return null;
        }

        return abs($attempt->completed_at->diffInSeconds($attempt->started_at));
    }
}
