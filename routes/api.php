<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

// Auth routes (login doesn't require auth but needs session)
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Tenant-scoped API routes
    Route::middleware('tenant.scope')->group(function () {

        // Sites
        Route::prefix('sites')->group(function () {
            // TODO
        });

        // Pages
        Route::prefix('sites/{site}/pages')->group(function () {
            // TODO
        });

        // Posts
        Route::prefix('sites/{site}/posts')->group(function () {
            // TODO
        });

        // Categories
        Route::prefix('sites/{site}/categories')->group(function () {
            // TODO
        });

        // Blocks
        Route::prefix('blocks')->group(function () {
            // TODO
        });

        // Assets
        Route::prefix('sites/{site}/assets')->group(function () {
            // TODO
        });

        // Publishing
        Route::prefix('sites/{site}/publish')->group(function () {
            // TODO
        });
    });
});
