<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAttemptRequest;
use App\Http\Resources\AttemptResource;
use App\Http\Resources\AttemptStatusResource;
use App\Jobs\EvaluateAttemptJob;
use App\Models\Topic;
use App\Models\TopicAttempt;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AttemptController extends Controller
{
    use ApiResponse;

    public function store(SubmitAttemptRequest $request, string $slug): JsonResponse
    {
        $topic = Topic::where('slug', $slug)->first();

        if (!$topic) {
            return $this->error('Topic not found.', 404);
        }

        $user = $request->user();

        // Rate limit: 10 attempts per hour
        $limit = config('evaluation.attempts_rate_limit');
        $recentCount = TopicAttempt::where('user_id', $user->id)
            ->where('started_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= $limit) {
            return $this->error('Too many attempts. Please wait before trying again.', 429);
        }

        $attempt = TopicAttempt::create([
            'user_id' => $user->id,
            'topic_id' => $topic->id,
            'answer_text' => $request->answer,
            'status' => 'pending',
            'started_at' => now(),
        ]);

        EvaluateAttemptJob::dispatch($attempt->id)->onQueue('evaluation');

        return $this->success([
            'attempt_id' => $attempt->id,
            'status' => 'pending',
        ], 'Attempt submitted for evaluation.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $attempt = TopicAttempt::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$attempt) {
            return $this->error('Attempt not found.', 404);
        }

        return $this->success(new AttemptResource($attempt));
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        $attempt = Cache::remember("attempt:status:{$id}", 30, function () use ($id, $userId) {
            return TopicAttempt::where('id', $id)
                ->where('user_id', $userId)
                ->first();
        });

        if (!$attempt || $attempt->user_id !== $userId) {
            return $this->error('Attempt not found.', 404);
        }

        return $this->success(new AttemptStatusResource($attempt));
    }

    public function indexByTopic(Request $request, string $slug): JsonResponse
    {
        $topic = Topic::where('slug', $slug)->first();

        if (!$topic) {
            return $this->error('Topic not found.', 404);
        }

        $attempts = TopicAttempt::where('user_id', $request->user()->id)
            ->where('topic_id', $topic->id)
            ->orderByDesc('started_at')
            ->paginate(10);

        return $this->success([
            'attempts' => AttemptResource::collection($attempts),
            'pagination' => [
                'current_page' => $attempts->currentPage(),
                'last_page' => $attempts->lastPage(),
                'per_page' => $attempts->perPage(),
                'total' => $attempts->total(),
            ],
        ]);
    }
}
