<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Auth\Notifications\VerifyEmail;

class SendVerificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public User $user,
    ) {}

    public function handle(): void
    {
        try {
            $this->user->notify(new VerifyEmail);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
