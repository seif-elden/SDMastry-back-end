<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $keyPoints = $this->key_points;
        if (is_string($keyPoints)) {
            $keyPoints = json_decode($keyPoints, true) ?? [];
        }

        $data = [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category,
            'section' => $this->section,
            'level' => $this->level,
            'hook_question' => $this->hook_question,
            'description' => $this->description,
            'key_points' => $keyPoints,
            'sort_order' => $this->sort_order,
        ];

        $data['attempts'] = $this->whenLoaded('currentUserAttempts', function () {
            return $this->currentUserAttempts->map(fn ($a) => [
                'attempt_id' => $a->id,
                'score' => $a->score,
                'status' => $a->status,
                'created_at' => $a->started_at?->toIso8601String(),
            ]);
        }, []);

        return $data;
    }
}
