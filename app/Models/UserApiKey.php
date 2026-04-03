<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserApiKey extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'encrypted_key',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_key' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
