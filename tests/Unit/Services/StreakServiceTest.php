<?php

namespace Tests\Unit\Services;

use App\Models\StreakLog;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_first_activity_sets_streak_to_one(): void
    {
        Carbon::setTestNow('2026-04-03 10:00:00');

        $user = User::factory()->create();

        app(StreakService::class)->recordActivity($user);

        $user->refresh();

        $this->assertSame(1, $user->current_streak);
        $this->assertSame(1, $user->longest_streak);
        $this->assertSame('2026-04-03', optional($user->last_activity_date)->toDateString());
        $this->assertTrue(
            StreakLog::query()
                ->where('user_id', $user->id)
                ->whereDate('activity_date', '2026-04-03')
                ->exists()
        );
    }

    public function test_consecutive_days_increment_streak(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-04-03 10:00:00');
        app(StreakService::class)->recordActivity($user);

        Carbon::setTestNow('2026-04-04 10:00:00');
        app(StreakService::class)->recordActivity($user);

        $user->refresh();

        $this->assertSame(2, $user->current_streak);
        $this->assertSame(2, $user->longest_streak);
    }

    public function test_gap_in_days_resets_streak_to_one(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-04-01 10:00:00');
        app(StreakService::class)->recordActivity($user);

        Carbon::setTestNow('2026-04-02 10:00:00');
        app(StreakService::class)->recordActivity($user);

        Carbon::setTestNow('2026-04-05 10:00:00');
        app(StreakService::class)->recordActivity($user);

        $user->refresh();

        $this->assertSame(1, $user->current_streak);
        $this->assertSame(2, $user->longest_streak);
    }

    public function test_same_day_activity_does_not_double_count(): void
    {
        Carbon::setTestNow('2026-04-03 10:00:00');

        $user = User::factory()->create();

        app(StreakService::class)->recordActivity($user);
        app(StreakService::class)->recordActivity($user);

        $user->refresh();

        $this->assertSame(1, $user->current_streak);
        $this->assertSame(1, $user->longest_streak);
        $this->assertSame(1, StreakLog::query()->where('user_id', $user->id)->count());
    }

    public function test_longest_streak_updates_correctly(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-04-01 10:00:00');
        app(StreakService::class)->recordActivity($user);

        Carbon::setTestNow('2026-04-02 10:00:00');
        app(StreakService::class)->recordActivity($user);

        Carbon::setTestNow('2026-04-03 10:00:00');
        app(StreakService::class)->recordActivity($user);

        Carbon::setTestNow('2026-04-06 10:00:00');
        app(StreakService::class)->recordActivity($user);

        Carbon::setTestNow('2026-04-07 10:00:00');
        app(StreakService::class)->recordActivity($user);

        $user->refresh();

        $this->assertSame(2, $user->current_streak);
        $this->assertSame(3, $user->longest_streak);
    }
}
