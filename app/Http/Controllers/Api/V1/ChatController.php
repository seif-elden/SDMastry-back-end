<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\AttemptAccessDeniedException;
use App\Exceptions\ChatProviderUnavailableException;
use App\Exceptions\EvaluationInProgressException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Services\Chat\ChatService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function index(Request $request, int $attempt_id): JsonResponse
    {
        try {
            $data = $this->chatService->listMessages($request->user(), $attempt_id);

            return $this->success($data);
        } catch (ModelNotFoundException) {
            return $this->error('Attempt not found.', 404);
        } catch (AttemptAccessDeniedException $exception) {
            return $this->error($exception->getMessage(), 403);
        } catch (EvaluationInProgressException $exception) {
            return $this->error($exception->getMessage(), 409);
        }
    }

    public function store(SendChatMessageRequest $request, int $attempt_id): JsonResponse
    {
        try {
            $data = $this->chatService->sendMessage(
                $request->user(),
                $attempt_id,
                $request->string('message')->toString(),
            );

            return $this->success($data);
        } catch (ModelNotFoundException) {
            return $this->error('Attempt not found.', 404);
        } catch (AttemptAccessDeniedException $exception) {
            return $this->error($exception->getMessage(), 403);
        } catch (EvaluationInProgressException $exception) {
            return $this->error($exception->getMessage(), 409);
        } catch (ChatProviderUnavailableException $exception) {
            return $this->error($exception->getMessage(), 503);
        }
    }
}
