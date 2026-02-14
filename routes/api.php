<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\MinuteController;
use App\Http\Controllers\Api\V1\ResolutionController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Middleware\EnforceApiTokenLimitsMiddleware;
use Illuminate\Support\Facades\Route;

/**
 * Public Calendar Feed — هیچ Auth ندارد، token در URL است.
 * این route خارج از prefix api/v1 است.
 */
Route::get('/calendar/feed/{token}', [CalendarFeedController::class, 'show'])
    ->where('token', '[A-Za-z0-9\._-]+')
    ->name('calendar.feed');

/**
 * API V1 — همه endpoint های authenticated
 *
 * Sanctum personal access tokens برای احراز هویت.
 * Middleware اضافی برای rate limit و IP whitelist.
 */
Route::prefix('api/v1')
    ->middleware(['auth:sanctum', EnforceApiTokenLimitsMiddleware::class])
    ->group(function () {
        // Health و info
        Route::get('/me', function () {
            $user = request()->user();
            return response()->json([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
            ]);
        });

        // Meetings
        Route::prefix('meetings')->group(function () {
            Route::get('/', [MeetingController::class, 'index']);
            Route::post('/', [MeetingController::class, 'store']);
            Route::get('/{id}', [MeetingController::class, 'show'])->whereNumber('id');
            Route::delete('/{id}', [MeetingController::class, 'destroy'])->whereNumber('id');
        });

        // Minutes
        Route::prefix('minutes')->group(function () {
            Route::get('/', [MinuteController::class, 'index']);
            Route::get('/{id}', [MinuteController::class, 'show'])->whereNumber('id');
        });

        // Resolutions
        Route::prefix('resolutions')->group(function () {
            Route::get('/', [ResolutionController::class, 'index']);
            Route::get('/{id}', [ResolutionController::class, 'show'])->whereNumber('id');
        });

        // Tasks
        Route::prefix('tasks')->group(function () {
            Route::get('/', [TaskController::class, 'index']);
            Route::get('/{id}', [TaskController::class, 'show'])->whereNumber('id');
            Route::patch('/{id}/progress', [TaskController::class, 'updateProgress'])->whereNumber('id');
        });
    });
