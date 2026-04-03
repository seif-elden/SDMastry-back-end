<?php

namespace Tests\Feature\Analytics;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BadgesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BadgeSeeder::class);
    }

    public function test_unearned_badge_shows_earned_false(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->getJson('/api/v1/badges');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $badges = $response->json('data.badges');
        $firstBlood = collect($badges)->firstWhere('slug', 'first-blood');

        $this->assertNotNull($firstBlood);
        $this->assertFalse((bool) $firstBlood['earned']);
        $this->assertNull($firstBlood['earned_at']);
    }

    public function test_earned_badge_shows_earned_true_with_earned_at(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $badge = Badge::query()->where('slug', 'first-blood')->firstOrFail();

        UserBadge::query()->create([
            'user_id' => $user->id,
            'badge_id' => $badge->id,
            'earned_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/badges');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $badges = $response->json('data.badges');
        $firstBlood = collect($badges)->firstWhere('slug', 'first-blood');

        $this->assertNotNull($firstBlood);
        $this->assertTrue((bool) $firstBlood['earned']);
        $this->assertNotNull($firstBlood['earned_at']);
    }
}
