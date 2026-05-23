<?php

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AssetServeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DiffController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\PageController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PreviewController;
use App\Http\Controllers\Api\V1\PublishController;
use App\Http\Controllers\Api\V1\SiteCloneController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\VersionController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\EditorPresenceController;
use App\Http\Controllers\Api\V1\MagEditorController;
use App\Http\Controllers\Api\V1\MagStyleController;
use App\Http\Controllers\Magazine\WizardController;
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('auth.login');

// Public preview via token
Route::get('/preview/{token}', [PreviewController::class, 'publicPreview']);

// Public form submission (rate-limited, no auth)
Route::post('/sites/{site}/forms/submit', [\App\Http\Controllers\Api\V1\FormController::class, 'submit'])
    ->middleware('throttle:10,1');

// Analytics tracking pixel (public, rate-limited)
Route::post('/sites/{site}/t', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'track'])
    ->middleware('throttle:60,1');

// Password reset (public, rate-limited)
Route::post('/auth/forgot-password', [\App\Http\Controllers\Api\V1\PasswordResetController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [\App\Http\Controllers\Api\V1\PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    // User management (admin+)
    Route::get('/users', [\App\Http\Controllers\Api\V1\UserController::class, 'index']);
    Route::post('/users/invite', [\App\Http\Controllers\Api\V1\UserController::class, 'invite']);
    Route::put('/users/{user}/role', [\App\Http\Controllers\Api\V1\UserController::class, 'updateRole']);
    Route::delete('/users/{user}', [\App\Http\Controllers\Api\V1\UserController::class, 'destroy']);

    // Tenant-scoped API routes
    Route::middleware('tenant.scope')->group(function () {

        // Sites
        Route::apiResource('sites', SiteController::class);

        // Pages
        Route::post('sites/{site}/pages/reorder', [PageController::class, 'reorder']);
        Route::apiResource('sites.pages', PageController::class);

        // Posts
        Route::apiResource('sites.posts', PostController::class);

        // Magazines
        Route::apiResource('sites.magazines', \App\Http\Controllers\Api\V1\MagazineController::class);
        Route::put('sites/{site}/magazines/{magazine}/pages', [\App\Http\Controllers\Api\V1\MagazineController::class, 'savePages']);

        // Categories
        Route::post('sites/{site}/categories/reorder', [CategoryController::class, 'reorder']);
        Route::apiResource('sites.categories', CategoryController::class);

        // Tags
        Route::apiResource('sites.tags', TagController::class);
        Route::post('sites/{site}/tags/{tag}/merge', [TagController::class, 'merge']);

        // Menus
        Route::apiResource('sites.menus', MenuController::class);
        Route::put('sites/{site}/menus/{menu}/items', [MenuController::class, 'syncItems']);

        // Redirects
        Route::get('sites/{site}/redirects', [\App\Http\Controllers\Api\V1\RedirectController::class, 'index']);
        Route::post('sites/{site}/redirects', [\App\Http\Controllers\Api\V1\RedirectController::class, 'store']);
        Route::put('sites/{site}/redirects/{redirect}', [\App\Http\Controllers\Api\V1\RedirectController::class, 'update']);
        Route::delete('sites/{site}/redirects/{redirect}', [\App\Http\Controllers\Api\V1\RedirectController::class, 'destroy']);

        // Grid System
        Route::apiResource('sites.grids', \App\Http\Controllers\Api\V1\GridController::class);
        Route::put('sites/{site}/grids/{grid}/positions', [\App\Http\Controllers\Api\V1\GridController::class, 'syncPositions']);
        Route::get('sites/{site}/grid-assignments', [\App\Http\Controllers\Api\V1\GridController::class, 'assignments']);
        Route::post('sites/{site}/grid-assignments', [\App\Http\Controllers\Api\V1\GridController::class, 'storeAssignment']);
        Route::put('sites/{site}/grid-assignments/{assignment}', [\App\Http\Controllers\Api\V1\GridController::class, 'updateAssignment']);
        Route::delete('sites/{site}/grid-assignments/{assignment}', [\App\Http\Controllers\Api\V1\GridController::class, 'destroyAssignment']);
        Route::post('sites/{site}/grid-positions/{position}/override', [\App\Http\Controllers\Api\V1\GridController::class, 'storeOverride']);
        Route::delete('sites/{site}/position-overrides/{override}', [\App\Http\Controllers\Api\V1\GridController::class, 'destroyOverride']);
        Route::post('sites/{site}/grids/seed-presets', [\App\Http\Controllers\Api\V1\GridController::class, 'seedPresets']);

        // Magazine Editor
        Route::get('sites/{site}/pages/{page}/magazine', [MagEditorController::class, 'show']);
        Route::put('sites/{site}/pages/{page}/magazine', [MagEditorController::class, 'sync']);
        Route::post('sites/{site}/pages/{page}/magazine/pages', [MagEditorController::class, 'addPage']);
        Route::delete('sites/{site}/pages/{page}/magazine/pages/{pageNumber}', [MagEditorController::class, 'deletePage']);

        // Magazine Styles
        Route::apiResource('sites.magazine-styles', MagStyleController::class)->parameters(['magazine-styles' => 'style']);

        // Issue Composer

        // Magazine Wizard
        Route::post('magazine/wizard/sessions', [WizardController::class, 'store']);
        Route::get('magazine/wizard/sessions', [WizardController::class, 'index']);
        Route::get('magazine/wizard/sessions/{session}', [WizardController::class, 'show']);
        Route::delete('magazine/wizard/sessions/{session}', [WizardController::class, 'destroy']);
        Route::post('magazine/wizard/sessions/{session}/messages', [WizardController::class, 'sendMessage']);
        Route::post('magazine/wizard/sessions/{session}/lock', [WizardController::class, 'lockStep']);
        Route::post('magazine/wizard/sessions/{session}/unlock', [WizardController::class, 'unlockStep']);
        Route::post('magazine/wizard/sessions/{session}/provision', [WizardController::class, 'provision']);

        // Blocks
        Route::get('blocks/types', [BlockController::class, 'types']);
        Route::get('sites/{site}/pages/{page}/blocks', [BlockController::class, 'indexForPage']);
        Route::put('sites/{site}/pages/{page}/blocks', [BlockController::class, 'syncForPage']);
        Route::get('sites/{site}/posts/{post}/blocks', [BlockController::class, 'indexForPost']);
        Route::put('sites/{site}/posts/{post}/blocks', [BlockController::class, 'syncForPost']);

        // Assets
        Route::apiResource('sites.assets', AssetController::class)->except(['update']);
        Route::get('sites/{site}/assets/{asset}/serve/{variant?}', [AssetServeController::class, 'serve']);

        // Custom Fonts
        Route::get('sites/{site}/fonts', [\App\Http\Controllers\Api\V1\CustomFontController::class, 'index']);
        Route::post('sites/{site}/fonts', [\App\Http\Controllers\Api\V1\CustomFontController::class, 'store']);
        Route::delete('sites/{site}/fonts/{fontId}', [\App\Http\Controllers\Api\V1\CustomFontController::class, 'destroy']);
        Route::get('sites/{site}/fonts/{filename}/serve', [\App\Http\Controllers\Api\V1\CustomFontController::class, 'serve']);

        // Publishing
        Route::post('sites/{site}/publish', [PublishController::class, 'publish']);
        Route::post('sites/{site}/publish/clear', [PublishController::class, 'clear']);
        Route::get('sites/{site}/download-zip', [PublishController::class, 'downloadZip']);
        Route::get('sites/{site}/deployments', [PublishController::class, 'history']);
        Route::get('sites/{site}/deployments/{deployment}', [PublishController::class, 'status']);
        Route::post('sites/{site}/deployments/{deployment}/rollback', [PublishController::class, 'rollback']);

        // Layouts
        Route::get('sites/{site}/layouts', function (\App\Models\Site $site) {
            $layouts = \Illuminate\Support\Facades\DB::table('layouts')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($site) {
                    $q->whereNull('tenant_id')->orWhere('tenant_id', $site->tenant_id);
                })
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get();
            return response()->json(['data' => $layouts->map(function ($l) {
                $l->supports = json_decode($l->supports ?? '{}', true);
                $l->allowed_block_types = json_decode($l->allowed_block_types ?? 'null', true);
                $l->promoted_block_types = json_decode($l->promoted_block_types ?? 'null', true);
                $l->config = json_decode($l->config ?? 'null', true);
                return $l;
            })]);
        });

        // Theme Engine
        Route::get('sites/{site}/theme-engine/themes', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'index']);
        Route::get('sites/{site}/theme-engine/themes/{theme}', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'show']);
        Route::put('sites/{site}/theme-engine/themes/{theme}', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'update']);
        Route::post('sites/{site}/theme-engine/themes/{theme}/fork', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'fork']);
        Route::get('sites/{site}/theme-engine/themes/{theme}/export', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'export']);
        Route::get('sites/{site}/theme-engine/resolve', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'resolve']);
        Route::post('sites/{site}/theme-engine/assign', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'assign']);
        Route::post('sites/{site}/theme-engine/overrides', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'saveOverrides']);
        Route::post('sites/{site}/theme-engine/import', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'import']);
        Route::get('sites/{site}/theme-engine/versions', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'versions']);
        Route::post('sites/{site}/theme-engine/versions/{version}/restore', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'restoreVersion']);
        Route::get('sites/{site}/theme-engine/themes/{theme}/coverage', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'coverage']);
        Route::get('sites/{site}/theme-engine/studio/frames', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'studioFrames']);
        Route::get('sites/{site}/theme-engine/studio/frame/{slug}', [\App\Http\Controllers\Api\V1\ThemeEngineController::class, 'studioFrame']);

        // Theme Templates (Theme Builder)
        Route::get('sites/{site}/templates', [\App\Http\Controllers\Api\V1\ThemeTemplateController::class, 'index']);
        Route::post('sites/{site}/templates', [\App\Http\Controllers\Api\V1\ThemeTemplateController::class, 'store']);
        Route::get('sites/{site}/templates/{themeTemplate}', [\App\Http\Controllers\Api\V1\ThemeTemplateController::class, 'show']);
        Route::put('sites/{site}/templates/{themeTemplate}', [\App\Http\Controllers\Api\V1\ThemeTemplateController::class, 'update']);
        Route::delete('sites/{site}/templates/{themeTemplate}', [\App\Http\Controllers\Api\V1\ThemeTemplateController::class, 'destroy']);
        Route::get('sites/{site}/templates/{themeTemplate}/blocks', [BlockController::class, 'indexForTemplate']);
        Route::put('sites/{site}/templates/{themeTemplate}/blocks', [BlockController::class, 'syncForTemplate']);

        // Preview
        Route::get('sites/{site}/pages/{page}/preview', [PreviewController::class, 'previewPage']);
        Route::get('sites/{site}/posts/{post}/preview', [PreviewController::class, 'previewPost']);
        Route::post('sites/{site}/blocks/render', [PreviewController::class, 'renderBlock']);
        Route::post('sites/{site}/{contentType}/{contentId}/preview-token', [PreviewController::class, 'createPreviewToken']);

        // Visual Diff
        Route::get('sites/{site}/pages/{page}/diff', [DiffController::class, 'diffPage']);
        Route::get('sites/{site}/posts/{post}/diff', [DiffController::class, 'diffPost']);

        // Version History & Restore
        Route::get('sites/{site}/pages/{page}/versions', [VersionController::class, 'indexForPage']);
        Route::get('sites/{site}/pages/{page}/versions/{version}', [VersionController::class, 'showForPage']);
        Route::post('sites/{site}/pages/{page}/versions/{version}/restore', [VersionController::class, 'restoreForPage']);
        Route::get('sites/{site}/posts/{post}/versions', [VersionController::class, 'indexForPost']);
        Route::get('sites/{site}/posts/{post}/versions/{version}', [VersionController::class, 'showForPost']);
        Route::post('sites/{site}/posts/{post}/versions/{version}/restore', [VersionController::class, 'restoreForPost']);

        // Magazine Issues CRUD (DTP)
        Route::get('sites/{site}/magazine-issues', [\App\Http\Controllers\Api\V1\MagazineIssueController::class, 'index']);
        Route::post('sites/{site}/magazine-issues', [\App\Http\Controllers\Api\V1\MagazineIssueController::class, 'store']);
        Route::patch('sites/{site}/magazine-issues/{issue}', [\App\Http\Controllers\Api\V1\MagazineIssueController::class, 'update']);
        Route::delete('sites/{site}/magazine-issues/{issue}', [\App\Http\Controllers\Api\V1\MagazineIssueController::class, 'destroy']);

        // DTP Rollout status (always available — reports status even when flag is off)
        Route::get('sites/{site}/magazine-issues/{issue}/dtp-rollout', [\App\Http\Controllers\Api\V1\DtpRolloutController::class, 'status']);

        // DTP Designer (feature-flagged)
        Route::middleware(\App\Http\Middleware\RequireDtpDesigner::class)->group(function () {
            Route::get('sites/{site}/magazine-issues/{issue}/dtp-document', [\App\Http\Controllers\Api\V1\DtpDocumentController::class, 'show']);
            Route::put('sites/{site}/magazine-issues/{issue}/dtp-document', [\App\Http\Controllers\Api\V1\DtpDocumentController::class, 'save']);
            Route::get('sites/{site}/magazine-issues/{issue}/dtp-preview', [\App\Http\Controllers\Api\V1\DtpPreviewController::class, 'preview']);
            Route::get('sites/{site}/magazine-issues/{issue}/dtp-preflight', [\App\Http\Controllers\Api\V1\DtpPreflightController::class, 'run']);
        });

        // WordPress Import
        Route::post('sites/{site}/import/upload', [ImportController::class, 'upload']);
        Route::get('sites/{site}/import/{importId}/preview', [ImportController::class, 'preview']);
        Route::post('sites/{site}/import/{importId}/execute', [ImportController::class, 'execute']);
        Route::get('sites/{site}/import/{importId}/status', [ImportController::class, 'status']);


        // AI Content Assistant
        Route::post('ai/generate', [AiController::class, 'generate'])->middleware('throttle:20,1');
        Route::post('ai/rewrite', [AiController::class, 'rewrite'])->middleware('throttle:20,1');
        Route::post('ai/translate', [AiController::class, 'translate'])->middleware('throttle:20,1');
        Route::post('sites/{site}/pages/{page}/ai/seo', [AiController::class, 'seoSuggest'])->middleware('throttle:20,1');
        Route::post('sites/{site}/assets/{asset}/ai/alt-text', [AiController::class, 'altText'])->middleware('throttle:20,1');

        // Site Cloning & Templates
        Route::post('sites/{site}/clone', [SiteCloneController::class, 'clone']);
        Route::post('sites/{site}/export', [SiteCloneController::class, 'export']);
        Route::post('sites/{site}/import-template', [SiteCloneController::class, 'importTemplate']);
        Route::get('templates', [TemplateController::class, 'index']);
        Route::get('templates/{template}/preview', [TemplateController::class, 'preview']);
        Route::post('templates/{template}/install/{site}', [TemplateController::class, 'install']);

        // Editor Presence / Collaboration
        Route::post('editor/heartbeat', [EditorPresenceController::class, 'heartbeat']);
        Route::get('editor/presence/{contentType}/{contentId}', [EditorPresenceController::class, 'presence']);

        // System (admin only)
        Route::get('system/updates', [SystemController::class, 'checkUpdate']);
        Route::post('system/updates/apply', [SystemController::class, 'applyUpdate']);
        Route::post('cms-export/generate', [SystemController::class, 'generateExport']);
        Route::get('cms-export/status', [SystemController::class, 'exportStatus']);
        Route::get('cms-export/download', [SystemController::class, 'downloadExport']);

        // Site Reset (owner only — destructive)
        Route::get('sites/{site}/reset/preview', [\App\Http\Controllers\Api\V1\SiteResetController::class, 'preview']);
        Route::post('sites/{site}/reset/content', [\App\Http\Controllers\Api\V1\SiteResetController::class, 'resetContent']);
        Route::post('sites/{site}/reset/factory', [\App\Http\Controllers\Api\V1\SiteResetController::class, 'factoryReset']);

        // Analytics Dashboard
        Route::get('sites/{site}/analytics', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'dashboard']);

        // Dependency Graph
        Route::get('sites/{site}/dependency-graph', function (\App\Models\Site $site) {
            $graph = app(\App\Domain\Publishing\Services\DependencyGraph::class);
            return response()->json(['data' => $graph->getGraph($site)]);
        });

        // Debug Console (admin only)
        Route::get('debug', [\App\Http\Controllers\Api\V1\DebugController::class, 'index']);
        Route::get('debug/logs', [\App\Http\Controllers\Api\V1\DebugController::class, 'logs']);
        Route::delete('debug/logs', [\App\Http\Controllers\Api\V1\DebugController::class, 'clearLogs']);
        Route::post('debug/retry-failed', [\App\Http\Controllers\Api\V1\DebugController::class, 'retryFailedJobs']);
        Route::post('debug/flush-failed', [\App\Http\Controllers\Api\V1\DebugController::class, 'flushFailedJobs']);
        Route::get('debug/cache', [\App\Http\Controllers\Api\V1\DebugController::class, 'cacheStatus']);
        Route::post('debug/clear-cache', [\App\Http\Controllers\Api\V1\DebugController::class, 'clearCache']);
    });
});
