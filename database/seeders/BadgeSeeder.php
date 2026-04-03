<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            // Progress Badges
            ['slug' => 'first-blood', 'name' => 'First Blood', 'description' => 'Completed your first topic', 'icon' => "\u{1FA78}", 'group' => 'progress'],
            ['slug' => 'core-explorer', 'name' => 'Core Explorer', 'description' => 'Passed 10 Core topics', 'icon' => "\u{1F680}", 'group' => 'progress'],
            ['slug' => 'core-cleared', 'name' => 'Core Cleared', 'description' => 'Mastered all 26 Core topics', 'icon' => "\u{1F680}", 'group' => 'progress'],
            ['slug' => 'advanced-scout', 'name' => 'Advanced Scout', 'description' => 'Passed 5 Advanced topics', 'icon' => "\u{1F680}", 'group' => 'progress'],
            ['slug' => 'advanced-cleared', 'name' => 'Advanced Cleared', 'description' => 'Mastered all 27 Advanced topics', 'icon' => "\u{1F680}", 'group' => 'progress'],
            ['slug' => 'sd-master', 'name' => 'SDMaster', 'description' => 'Mastered all 53 topics', 'icon' => "\u{1F3C6}", 'group' => 'progress'],
            ['slug' => 'halfway', 'name' => 'Halfway There', 'description' => 'Passed 26 of 53 topics', 'icon' => "\u{1F680}", 'group' => 'progress'],

            // Score Badges
            ['slug' => 'perfect-100', 'name' => 'Perfect Score', 'description' => 'Got 100/100 on a topic', 'icon' => "\u{1F4AF}", 'group' => 'score'],
            ['slug' => 'perfectionist', 'name' => 'Perfectionist', 'description' => 'Got 100/100 on 5 different topics', 'icon' => "\u{1F4AF}", 'group' => 'score'],
            ['slug' => 'high-scorer', 'name' => 'High Scorer', 'description' => 'Averaged >= 90 across 10 topics', 'icon' => "\u{2B50}", 'group' => 'score'],
            ['slug' => 'close-call', 'name' => 'Close Call', 'description' => 'Passed a topic with exactly 80', 'icon' => "\u{1F605}", 'group' => 'score'],

            // Streak Badges
            ['slug' => 'streak-3', 'name' => 'On a Roll', 'description' => '3-day learning streak', 'icon' => "\u{1F525}", 'group' => 'streak'],
            ['slug' => 'streak-7', 'name' => 'Week Warrior', 'description' => '7-day learning streak', 'icon' => "\u{1F525}", 'group' => 'streak'],
            ['slug' => 'streak-30', 'name' => 'Monthly Master', 'description' => '30-day learning streak', 'icon' => "\u{1F525}", 'group' => 'streak'],
            ['slug' => 'streak-100', 'name' => 'Centurion', 'description' => '100-day learning streak', 'icon' => "\u{1F525}", 'group' => 'streak'],

            // Engagement Badges
            ['slug' => 'comeback-kid', 'name' => 'Comeback Kid', 'description' => 'Failed a topic then passed it', 'icon' => "\u{1F4AA}", 'group' => 'engagement'],
            ['slug' => 'deep-diver', 'name' => 'Deep Diver', 'description' => 'Had a 10+ message chat on a topic', 'icon' => "\u{1F4AC}", 'group' => 'engagement'],
            ['slug' => 'multi-agent', 'name' => 'Agent Collector', 'description' => 'Used 3 different LLM providers', 'icon' => "\u{1F916}", 'group' => 'engagement'],
            ['slug' => 'persistent', 'name' => 'Persistent', 'description' => 'Retried a topic 5+ times', 'icon' => "\u{1F504}", 'group' => 'engagement'],

            // Speed Badges
            ['slug' => 'speed-runner', 'name' => 'Speed Runner', 'description' => 'Passed a topic in under 3 minutes', 'icon' => "\u{26A1}", 'group' => 'speed'],
        ];

        foreach ($badges as $badge) {
            Badge::updateOrCreate(
                ['slug' => $badge['slug']],
                $badge,
            );
        }
    }
}
