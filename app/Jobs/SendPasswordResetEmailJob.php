<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Auth\Notifications\ResetPassword;
use App\Models\User;

class SendPasswordResetEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public User $user,
        public string $token,
    ) {}

    public function handle(): void
    {
        try {
            $this->user->notify(new ResetPassword($this->token));
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
