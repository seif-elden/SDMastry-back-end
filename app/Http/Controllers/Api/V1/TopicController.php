<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TopicDetailResource;
use App\Http\Resources\TopicResource;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Models\UserTopicProgress;
use App\Traits\ApiResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TopicController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $topics = Cache::rememberForever('topics:all', function () {
            return Topic::orderBy('sort_order')->get();
        });

        $user = $request->user();

        if (! $user) {
            $token = $request->bearerToken();

            if ($token) {
                $tokenModel = PersonalAccessToken::findToken($token);
                $tokenable = $tokenModel?->tokenable;
                $user = $tokenable instanceof \App\Models\User ? $tokenable : null;

                if ($user) {
                    $request->setUserResolver(fn () => $user);
                }
            }
        }

        if ($user) {
            $passThreshold = (int) config('evaluation.pass_threshold', 80);

            $progressMap = $user->topicProgress()
                ->get()
                ->keyBy('topic_id');

            $derivedProgressMap = TopicAttempt::query()
                ->where('user_id', $user->id)
                ->whereNotNull('score')
                ->selectRaw('topic_id, MAX(score) as best_score, COUNT(*) as attempts_count, MAX(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_flag, MAX(CASE WHEN passed = 1 THEN completed_at ELSE NULL END) as passed_at')
                ->groupBy('topic_id')
                ->get()
                ->mapWithKeys(function (TopicAttempt $attempt) use ($user, $passThreshold) {
                    $bestScore = (int) ($attempt->best_score ?? 0);
                    $passed = ((int) ($attempt->passed_flag ?? 0) === 1) || $bestScore >= $passThreshold;

                    return [
                        $attempt->topic_id => new UserTopicProgress([
                            'user_id' => $user->id,
                            'topic_id' => $attempt->topic_id,
                            'best_score' => $bestScore,
                            'attempts_count' => (int) ($attempt->attempts_count ?? 0),
                            'passed' => $passed,
                            'passed_at' => $attempt->passed_at,
                        ]),
                    ];
                });

            $topics->each(function ($topic) use ($progressMap, $derivedProgressMap) {
                $progress = $progressMap->get($topic->id) ?? $derivedProgressMap->get($topic->id);
                $topic->setRelation('currentUserProgress', collect([$progress])->filter());
            });
        } else {
            $topics->each(function ($topic) {
                $topic->setRelation('currentUserProgress', collect());
            });
        }

        return $this->success(TopicResource::collection($topics));
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $topic = Topic::where('slug', $slug)->first();

        if (!$topic) {
            return $this->error('Topic not found.', 404);
        }

        $user = $request->user();

        if (! $user) {
            $token = $request->bearerToken();

            if ($token) {
                $tokenModel = PersonalAccessToken::findToken($token);
                $tokenable = $tokenModel?->tokenable;
                $user = $tokenable instanceof \App\Models\User ? $tokenable : null;
            }
        }

        if ($user) {
            $attempts = $topic->attempts()
                ->where('user_id', $user->id)
                ->orderByDesc('started_at')
                ->get();

            $topic->setRelation('currentUserAttempts', $attempts);
        }

        return $this->success(new TopicDetailResource($topic));
    }
}
