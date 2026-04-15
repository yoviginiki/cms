<?php

use Illuminate\Support\Facades\Route;

// Public auth routes
Route::post('/auth/login', function () {
    // TODO: implement
})->name('auth.login');

Route::post('/auth/logout', function () {
    // TODO: implement
})->middleware('auth:sanctum')->name('auth.logout');

// Protected API routes
Route::middleware(['auth:sanctum', 'tenant.scope'])->group(function () {

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
