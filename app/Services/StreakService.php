<?php

namespace App\Services;

use App\Models\StreakLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StreakService
{
    public function recordActivity(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();

            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            $alreadyLogged = StreakLog::query()
                ->where('user_id', $lockedUser->id)
                ->whereDate('activity_date', $today)
                ->exists();

            if ($alreadyLogged) {
                return;
            }

            StreakLog::query()->create([
                'user_id' => $lockedUser->id,
                'activity_date' => $today->toDateString(),
            ]);

            $hadYesterdayActivity = StreakLog::query()
                ->where('user_id', $lockedUser->id)
                ->whereDate('activity_date', $yesterday)
                ->exists();

            $currentStreak = $hadYesterdayActivity
                ? ($lockedUser->current_streak + 1)
                : 1;

            $lockedUser->current_streak = $currentStreak;
            $lockedUser->longest_streak = max($lockedUser->longest_streak, $currentStreak);
            $lockedUser->last_activity_date = $today->toDateString();
            $lockedUser->save();
        });
    }
}
