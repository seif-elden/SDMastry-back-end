<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Jobs\SendPasswordResetEmailJob;
use App\Jobs\SendVerificationEmailJob;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        SendVerificationEmailJob::dispatch($user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => null,
            ],
            'token' => $token,
        ], 'Registration successful. Please verify your email.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->error('Invalid credentials.', 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
            ],
            'token' => $token,
        ], 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'selected_agent' => $user->selected_agent,
            'current_streak' => $user->current_streak,
            'longest_streak' => $user->longest_streak,
            'last_activity_date' => $user->last_activity_date?->toDateString(),
            'created_at' => $user->created_at,
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5174');
        $user = User::find($id);

        if (!$user || !hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->verifyHtmlResponse(false, 'Invalid verification link.', $frontendUrl);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->verifyHtmlResponse(true, 'Email already verified.', $frontendUrl);
        }

        $user->markEmailAsVerified();

        return $this->verifyHtmlResponse(true, 'Email verified successfully!', $frontendUrl);
    }

    private function verifyHtmlResponse(bool $success, string $message, string $frontendUrl): \Illuminate\Http\Response
    {
        $color = $success ? '#22c55e' : '#ef4444';
        $icon = $success ? '&#10003;' : '&#10007;';
        $loginUrl = rtrim($frontendUrl, '/') . '/login';

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Verification — SDMastery</title>
            <style>
                body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#09090b; font-family:system-ui,sans-serif; color:#fafafa; }
                .card { text-align:center; padding:3rem 2rem; border-radius:1rem; border:1px solid #27272a; background:#18181b; max-width:420px; }
                .icon { font-size:3rem; color:{$color}; margin-bottom:1rem; }
                h1 { font-size:1.25rem; margin:0 0 0.5rem; }
                p { color:#a1a1aa; margin:0 0 1.5rem; }
                a { display:inline-block; padding:0.75rem 2rem; background:#6366f1; color:#fff; text-decoration:none; border-radius:0.5rem; font-weight:600; }
                a:hover { background:#4f46e5; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon">{$icon}</div>
                <h1>{$message}</h1>
                <p>You can now close this tab or go back to the app.</p>
                <a href="{$loginUrl}">Go to SDMastery</a>
            </div>
        </body>
        </html>
        HTML;

        return response($html, $success ? 200 : 403)
            ->header('Content-Type', 'text/html');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->success(null, 'Email already verified.');
        }

        SendVerificationEmailJob::dispatch($user);

        return $this->success(null, 'Verification email resent.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()],
            );

            SendPasswordResetEmailJob::dispatch($user, $token);
        }

        // Always return success to prevent email enumeration
        return $this->success(null, 'If that email exists, a reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return $this->error('Invalid reset token.', 400);
        }

        // Check expiry (60 minutes)
        $expireMinutes = config('auth.passwords.users.expire', 60);
        if (Carbon::parse($record->created_at)->addMinutes($expireMinutes)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return $this->error('Reset token has expired.', 400);
        }

        if (!Hash::check($request->token, $record->token)) {
            return $this->error('Invalid reset token.', 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->error('Invalid reset token.', 400);
        }

        $user->update(['password' => $request->password]);

        // Single-use: delete token after successful reset
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return $this->success(null, 'Password reset successfully.');
    }
}
