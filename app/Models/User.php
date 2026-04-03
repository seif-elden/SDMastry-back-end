<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'selected_agent',
        'current_streak',
        'longest_streak',
        'last_activity_date',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_activity_date' => 'date',
        ];
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(UserApiKey::class);
    }

    public function topicAttempts(): HasMany
    {
        return $this->hasMany(TopicAttempt::class);
    }

    public function topicProgress(): HasMany
    {
        return $this->hasMany(UserTopicProgress::class);
    }

    public function badges(): HasMany
    {
        return $this->hasMany(UserBadge::class);
    }

    public function streakLogs(): HasMany
    {
        return $this->hasMany(StreakLog::class);
    }
}
