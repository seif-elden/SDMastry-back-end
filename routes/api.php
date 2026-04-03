<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes — /api/v1/auth/*
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // Public (rate-limited)
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:register');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:forgot-password');

    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::post('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->name('verification.verify');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/email/resend', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:resend-verification');
    });
});
