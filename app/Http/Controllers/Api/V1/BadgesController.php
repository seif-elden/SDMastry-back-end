<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgesController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $earnedByBadgeId = UserBadge::query()
            ->where('user_id', $userId)
            ->get()
            ->keyBy('badge_id');

        $badges = Badge::query()
            ->orderBy('id')
            ->get()
            ->map(function (Badge $badge) use ($earnedByBadgeId): array {
                $earned = $earnedByBadgeId->get($badge->id);

                return [
                    'slug' => $badge->slug,
                    'name' => $badge->name,
                    'description' => $badge->description,
                    'icon' => $badge->icon,
                    'group' => $badge->group,
                    'earned' => $earned !== null,
                    'earned_at' => optional($earned?->earned_at)?->toISOString(),
                ];
            })
            ->values()
            ->all();

        return $this->success([
            'badges' => $badges,
        ]);
    }
}
