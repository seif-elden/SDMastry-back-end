<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TopicDetailResource;
use App\Http\Resources\TopicResource;
use App\Models\Topic;
use App\Traits\ApiResponse;
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

        if ($user && $user->hasVerifiedEmail()) {
            $progressMap = $user->topicProgress()
                ->get()
                ->keyBy('topic_id');

            $topics->each(function ($topic) use ($progressMap) {
                $topic->setRelation('currentUserProgress', collect([$progressMap->get($topic->id)])->filter());
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

        if ($user && $user->hasVerifiedEmail()) {
            $attempts = $topic->attempts()
                ->where('user_id', $user->id)
                ->orderByDesc('started_at')
                ->get();

            $topic->setRelation('currentUserAttempts', $attempts);
        }

        return $this->success(new TopicDetailResource($topic));
    }
}
