<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'topic_attempt_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function topicAttempt(): BelongsTo
    {
        return $this->belongsTo(TopicAttempt::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
