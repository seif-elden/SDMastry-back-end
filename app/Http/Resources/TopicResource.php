<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAuthenticated = (bool) $user;
        $isVerified = $user && $user->hasVerifiedEmail();

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category,
            'section' => $this->section,
            'level' => $this->level,
            'hook_question' => $this->hook_question,
            'sort_order' => $this->sort_order,
        ];

        if (!$isVerified) {
            $data['locked'] = true;
        }

        if ($isAuthenticated) {
            $progress = $this->whenLoaded('currentUserProgress', function () {
                $p = $this->currentUserProgress->first();
                if ($p) {
                    return [
                        'best_score' => $p->best_score,
                        'attempts_count' => $p->attempts_count,
                        'passed' => $p->passed,
                        'passed_at' => $p->passed_at?->toIso8601String(),
                    ];
                }
                return null;
            });

            $data['progress'] = $progress;
        }

        return $data;
    }
}
