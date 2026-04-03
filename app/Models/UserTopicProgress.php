<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTopicProgress extends Model
{
    const CREATED_AT = null;

    protected $table = 'user_topic_progress';

    protected $fillable = [
        'user_id',
        'topic_id',
        'best_score',
        'attempts_count',
        'passed',
        'passed_at',
    ];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'passed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
