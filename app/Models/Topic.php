<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Topic extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'slug',
        'title',
        'category',
        'section',
        'level',
        'hook_question',
        'description',
        'key_points',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'key_points' => 'array',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TopicAttempt::class);
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserTopicProgress::class);
    }
}
