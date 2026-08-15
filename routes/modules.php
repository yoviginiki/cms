<?php

use App\Http\Controllers\Api\Modules\CultureDraftController;
use App\Http\Controllers\Api\Modules\CultureEventSyncController;
use App\Http\Controllers\Api\Modules\CultureVenueSyncController;
use Illuminate\Support\Facades\Route;

/**
 * Module receiving endpoints — mounted at prefix `api/modules` (see
 * bootstrap/app.php `then:`), deliberately OUTSIDE the `api/v1` app namespace.
 * These are the external integration surface for standalone module services;
 * they authenticate by module token (not a user session) and set their own
 * tenant context, so they do NOT use the `api` middleware group.
 */

// POST /api/modules/culture-engine/drafts
Route::post('culture-engine/drafts', [CultureDraftController::class, 'store'])
    ->middleware([
        'throttle:module-api',
        'module.token:drafts:create',
        'module:culture-engine',
    ]);

// POST /api/modules/culture-engine/events — sync approved events into the
// tenant's cultural-events collection (Track G).
Route::post('culture-engine/events', [CultureEventSyncController::class, 'store'])
    ->middleware([
        'throttle:module-api',
        'module.token:events:sync',
        'module:culture-engine',
    ]);

// POST /api/modules/culture-engine/venues — sync venue objects into the
// tenant's "Обекти" collection (reuses the events:sync token ability).
Route::post('culture-engine/venues', [CultureVenueSyncController::class, 'store'])
    ->middleware([
        'throttle:module-api',
        'module.token:events:sync',
        'module:culture-engine',
    ]);
