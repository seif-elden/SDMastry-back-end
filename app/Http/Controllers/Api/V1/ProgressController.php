<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Progress\UserProgressService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    use ApiResponse;

    public function __construct(
        private UserProgressService $progressService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $summary = $this->progressService->getSummary($request->user()->id);

        return $this->success($summary);
    }
}
