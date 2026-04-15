<?php

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AssetServeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PublishController;
use App\Http\Controllers\Api\V1\SiteController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Tenant-scoped API routes
    Route::middleware('tenant.scope')->group(function () {

        // Sites
        Route::apiResource('sites', SiteController::class);

        // Pages
        Route::post('sites/{site}/pages/reorder', [PageController::class, 'reorder']);
        Route::apiResource('sites.pages', PageController::class);

        // Posts
        Route::apiResource('sites.posts', PostController::class);

        // Categories
        Route::post('sites/{site}/categories/reorder', [CategoryController::class, 'reorder']);
        Route::apiResource('sites.categories', CategoryController::class);

        // Blocks
        Route::get('blocks/types', [BlockController::class, 'types']);
        Route::get('sites/{site}/pages/{page}/blocks', [BlockController::class, 'indexForPage']);
        Route::put('sites/{site}/pages/{page}/blocks', [BlockController::class, 'syncForPage']);
        Route::get('sites/{site}/posts/{post}/blocks', [BlockController::class, 'indexForPost']);
        Route::put('sites/{site}/posts/{post}/blocks', [BlockController::class, 'syncForPost']);

        // Assets
        Route::apiResource('sites.assets', AssetController::class)->except(['update']);
        Route::get('sites/{site}/assets/{asset}/serve/{variant?}', [AssetServeController::class, 'serve']);

        // Publishing
        Route::post('sites/{site}/publish', [PublishController::class, 'publish']);
        Route::get('sites/{site}/deployments', [PublishController::class, 'history']);
        Route::get('sites/{site}/deployments/{deployment}', [PublishController::class, 'status']);
        Route::post('sites/{site}/deployments/{deployment}/rollback', [PublishController::class, 'rollback']);
    });
});
