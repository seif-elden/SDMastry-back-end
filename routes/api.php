<?php

use App\Http\Controllers\Api\V1\AttemptController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\TopicController;
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

/*
|--------------------------------------------------------------------------
| Topic Routes — /api/v1/topics/*
|--------------------------------------------------------------------------
*/
Route::get('/topics', [TopicController::class, 'index']);
Route::get('/topics/{slug}', [TopicController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    // Attempt submission requires verified email
    Route::middleware('verified')->group(function () {
        Route::post('/topics/{slug}/attempts', [AttemptController::class, 'store']);
    });

    // Attempt reading only requires auth
    Route::get('/attempts/{id}', [AttemptController::class, 'show']);
    Route::get('/attempts/{id}/status', [AttemptController::class, 'status']);
    Route::get('/topics/{slug}/attempts', [AttemptController::class, 'indexByTopic']);

    // Progress
    Route::get('/progress', [ProgressController::class, 'index']);
});
