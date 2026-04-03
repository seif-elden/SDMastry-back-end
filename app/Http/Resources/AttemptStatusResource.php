<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttemptStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'attempt_id' => $this->id,
            'status' => $this->status,
            'score' => $this->score,
            'passed' => $this->passed,
        ];
    }
}
