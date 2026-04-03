<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $analytics = $this->analyticsService->getUserAnalytics($request->user()->id);

        return $this->success($analytics);
    }
}
