<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isVerified = $user && $user->hasVerifiedEmail();

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category,
            'section' => $this->section,
            'level' => $this->level,
            'hook_question' => $this->hook_question,
            'description' => $this->description,
            'key_points' => $this->key_points,
            'sort_order' => $this->sort_order,
        ];

        if ($isVerified) {
            $data['attempts'] = $this->whenLoaded('currentUserAttempts', function () {
                return $this->currentUserAttempts->map(fn ($a) => [
                    'attempt_id' => $a->id,
                    'score' => $a->score,
                    'status' => $a->status,
                    'created_at' => $a->started_at?->toIso8601String(),
                ]);
            });
        }

        return $data;
    }
}
