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
use Illuminate\Support\Facades\Route;

// Auth routes
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('auth.login');

// Public preview via token
Route::get('/preview/{token}', [PreviewController::class, 'publicPreview']);

// Public read-only collections API (Track G3 — Tier 2 dynamic search).
// GET-only namespace; a security test asserts no write route ever appears here.
Route::middleware(['public.site', 'public.cors', 'throttle:60,1'])->prefix('public/{site}')->group(function () {
    Route::get('/collections/{collectionSlug}/records', [\App\Http\Controllers\Api\V1\PublicCollectionController::class, 'records']);
    Route::get('/collections/{collectionSlug}/records/{recordSlug}', [\App\Http\Controllers\Api\V1\PublicCollectionController::class, 'record']);
    Route::get('/queries/{querySlug}', [\App\Http\Controllers\Api\V1\PublicQueryController::class, 'show']);
});

// Public form submission (rate-limited, no auth)
Route::post('/sites/{site}/forms/submit', [\App\Http\Controllers\Api\V1\FormController::class, 'submit'])
    ->middleware(['public.site', 'throttle:10,1']);

// Public comments (rate-limited)
Route::get('/sites/{site}/comments/{postSlug}', function (\App\Models\Site $site, string $postSlug) {
    $path = storage_path("app/comments/{$site->id}/" . preg_replace('/[^a-z0-9\-]/', '', $postSlug) . '.json');
    if (!file_exists($path)) return response()->json(['data' => []]);
    $comments = json_decode(file_get_contents($path), true) ?: [];
    // Only return approved comments
    return response()->json(['data' => array_values(array_filter($comments, fn($c) => ($c['status'] ?? 'pending') === 'approved'))]);
})->middleware(['public.site', 'throttle:60,1']);

Route::post('/sites/{site}/comments/{postSlug}', function (\Illuminate\Http\Request $request, \App\Models\Site $site, string $postSlug) {
    $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:200', 'body' => 'required|string|max:2000']);
    if (!empty($request->input('_honeypot'))) return response()->json(['success' => true]);
    $safeSlug = preg_replace('/[^a-z0-9\-]/', '', $postSlug);
    $dir = storage_path("app/comments/{$site->id}");
    \Illuminate\Support\Facades\File::ensureDirectoryExists($dir);
    $path = "{$dir}/{$safeSlug}.json";
    $comments = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
    $comments[] = [
        'id' => uniqid('cmt_'), 'name' => $request->input('name'), 'email' => $request->input('email'),
        'body' => strip_tags($request->input('body')), 'status' => 'pending',
        'created_at' => now()->toIso8601String(), 'ip' => $request->ip(),
    ];
    if (count($comments) > 500) $comments = array_slice($comments, -500);
    file_put_contents($path, json_encode($comments, JSON_PRETTY_PRINT));
    return response()->json(['success' => true, 'message' => 'Comment submitted for moderation.']);
})->middleware(['public.site', 'throttle:5,1']);

// Public site search (no auth, rate-limited)
Route::get('/sites/{site}/search', function (\Illuminate\Http\Request $request, \App\Models\Site $site) {
    $q = $request->input('q', '');
    if (strlen($q) < 2) return response()->json(['data' => []]);
    $safeQ = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

    $pages = \App\Models\Page::where('site_id', $site->id)
        ->where('status', 'published')
        ->where('title', 'ilike', $safeQ)
        ->select('id', 'title', 'slug')
        ->limit(10)->get()
        ->map(fn($p) => ['type' => 'page', 'title' => $p->title, 'url' => '/' . $p->slug]);

    $posts = \App\Models\Post::where('site_id', $site->id)
        ->where('status', 'published')
        ->where(fn($q2) => $q2->where('title', 'ilike', $safeQ)->orWhere('excerpt', 'ilike', $safeQ))
        ->select('id', 'title', 'slug', 'category_id')
        ->with('category:id,slug')
        ->limit(10)->get()
        ->map(fn($p) => ['type' => 'post', 'title' => $p->title, 'url' => '/' . ($p->category?->slug ?? 'uncategorized') . '/' . $p->slug]);

    return response()->json(['data' => $pages->concat($posts)->take(15)]);
})->middleware(['public.site', 'throttle:30,1']);

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
        Route::post('sites/{site}/pages/{page}/duplicate', [PageController::class, 'duplicate']);
        Route::post('sites/{site}/pages/{page}/duplicate-as-canvas', [PageController::class, 'duplicateAsCanvas']);
        Route::post('sites/{site}/pages/{page}/translate', [PageController::class, 'translate']);
        Route::get('sites/{site}/pages/{page}/translations', [PageController::class, 'translations']);
        Route::apiResource('sites.pages', PageController::class);

        // Posts
        Route::post('sites/{site}/posts/{post}/duplicate', [PostController::class, 'duplicate']);
        Route::post('sites/{site}/posts/{post}/translate', [PostController::class, 'translate']);
        Route::get('sites/{site}/posts/{post}/translations', [PostController::class, 'translations']);
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

        // Issue Studio (conversational magazine wizard — replaced the legacy Issue Composer wizard)
        Route::middleware('role:admin')->prefix('issue-studio')->group(function () {
            Route::get('sessions', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'index']);
            Route::post('sessions', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'store']);
            Route::get('sessions/{studioSession}', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'show']);
            Route::delete('sessions/{studioSession}', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'destroy']);
            Route::post('sessions/{studioSession}/messages', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'sendMessage'])->middleware('throttle:30,1');
            Route::post('sessions/{studioSession}/materials', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'addMaterial']);
            Route::delete('sessions/{studioSession}/materials/{materialId}', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'removeMaterial']);
            Route::post('sessions/{studioSession}/complete-interview', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'completeInterview']);
            Route::post('sessions/{studioSession}/flatplan/generate', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'generateFlatplan'])->middleware('throttle:10,1');
            Route::post('sessions/{studioSession}/flatplan/revise', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'reviseFlatplanSpread'])->middleware('throttle:20,1');
            Route::post('sessions/{studioSession}/flatplan/reorder', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'reorderFlatplan']);
            Route::post('sessions/{studioSession}/flatplan/approve', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'approveFlatplan']);
            Route::post('sessions/{studioSession}/spreads/generate-next', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'generateNextSpread'])->middleware('throttle:10,1');
            Route::post('sessions/{studioSession}/spreads/{position}/keep', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'keepSpread'])->whereNumber('position');
            Route::post('sessions/{studioSession}/spreads/{position}/revise', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'reviseSpread'])->whereNumber('position')->middleware('throttle:10,1');
            Route::post('sessions/{studioSession}/spreads/{position}/rethink', [\App\Http\Controllers\IssueStudio\IssueStudioController::class, 'rethinkSpread'])->whereNumber('position')->middleware('throttle:10,1');
        });

        // Blocks
        Route::get('blocks/types', [BlockController::class, 'types']);
        Route::get('sites/{site}/pages/{page}/blocks', [BlockController::class, 'indexForPage']);
        Route::put('sites/{site}/pages/{page}/blocks', [BlockController::class, 'syncForPage']);
        Route::get('sites/{site}/posts/{post}/blocks', [BlockController::class, 'indexForPost']);
        Route::put('sites/{site}/posts/{post}/blocks', [BlockController::class, 'syncForPost']);

        // Assets
        Route::apiResource('sites.assets', AssetController::class);
        Route::get('sites/{site}/assets/{asset}/serve/{variant?}', [AssetServeController::class, 'serve']);

        // Entity references ("used on N pages")
        Route::get('sites/{site}/references/usage', [\App\Http\Controllers\Api\V1\ReferenceController::class, 'usage']);

        // Slider library entities
        Route::apiResource('sites.sliders', \App\Http\Controllers\Api\V1\SliderController::class);
        Route::put('sites/{site}/sliders/{slider}/blocks', [\App\Http\Controllers\Api\V1\SliderController::class, 'syncBlocks']);
        Route::post('sites/{site}/sliders/{slider}/publish', [\App\Http\Controllers\Api\V1\SliderController::class, 'publish']);
        Route::post('sites/{site}/sliders/{slider}/duplicate', [\App\Http\Controllers\Api\V1\SliderController::class, 'duplicate']);

        // Style Presets (Builder Experience P3)
        $sp = \App\Http\Controllers\Api\V1\StylePresetController::class;
        Route::get('sites/{site}/style-presets/export', [$sp, 'export']);
        Route::post('sites/{site}/style-presets/import', [$sp, 'import']);
        Route::post('sites/{site}/style-presets/{stylePreset}/adopt', [$sp, 'adopt']);
        Route::apiResource('sites.style-presets', $sp)->parameters(['style-presets' => 'stylePreset']);

        // Global Sections (Builder Experience P2) — reusable-by-reference sections
        $gs = \App\Http\Controllers\Api\V1\GlobalSectionController::class;
        Route::apiResource('sites.global-sections', $gs)->parameters(['global-sections' => 'globalSection']);
        Route::post('sites/{site}/global-sections/promote', [$gs, 'promote']);
        Route::put('sites/{site}/global-sections/{globalSection}/blocks', [$gs, 'syncBlocks']);
        Route::post('sites/{site}/global-sections/{globalSection}/publish', [$gs, 'publish']);
        Route::post('sites/{site}/global-sections/{globalSection}/unpublish', [$gs, 'unpublish']);

        // Stale content: list, staged batch republish, human-confirmed promote
        // Collections (Track G) — user-defined structured data + records + import/export
        Route::post('sites/{site}/collections/{collection}/records/bulk', [\App\Http\Controllers\Api\V1\RecordController::class, 'bulk']);
        Route::get('sites/{site}/collections/{collection}/export', [\App\Http\Controllers\Api\V1\CollectionImportController::class, 'export']);
        Route::post('sites/{site}/collections/{collection}/import', [\App\Http\Controllers\Api\V1\CollectionImportController::class, 'upload']);
        Route::post('sites/{site}/collections/{collection}/import/{importId}/execute', [\App\Http\Controllers\Api\V1\CollectionImportController::class, 'execute']);
        Route::get('sites/{site}/collections/{collection}/import/{importId}/status', [\App\Http\Controllers\Api\V1\CollectionImportController::class, 'status']);
        Route::apiResource('sites.collections', \App\Http\Controllers\Api\V1\CollectionController::class);
        Route::apiResource('sites.collections.records', \App\Http\Controllers\Api\V1\RecordController::class);

        // Saved queries (Track G-Q) — authoring is admin/owner only
        Route::middleware('role:admin')->group(function () {
            Route::post('sites/{site}/saved-queries/preview', [\App\Http\Controllers\Api\V1\SavedQueryController::class, 'preview']);
            Route::post('sites/{site}/saved-queries/show-sql', [\App\Http\Controllers\Api\V1\SavedQueryController::class, 'showSql']);
            Route::apiResource('sites.saved-queries', \App\Http\Controllers\Api\V1\SavedQueryController::class)
                ->parameters(['saved-queries' => 'savedQuery']);
        });

        Route::get('sites/{site}/stale', [\App\Http\Controllers\Api\V1\StaleContentController::class, 'index']);
        Route::post('sites/{site}/stale/republish', [\App\Http\Controllers\Api\V1\StaleContentController::class, 'republish']);
        Route::post('sites/{site}/stale/{deployment}/promote', [\App\Http\Controllers\Api\V1\StaleContentController::class, 'promote']);

        // Custom Fonts
        Route::get('sites/{site}/fonts', [\App\Http\Controllers\Api\V1\CustomFontController::class, 'index']);
        Route::post('sites/{site}/fonts', [\App\Http\Controllers\Api\V1\CustomFontController::class, 'store']);
        Route::delete('sites/{site}/fonts/{fontId}', [\App\Http\Controllers\Api\V1\CustomFontController::class, 'destroy']);
        Route::get('sites/{site}/fonts/{filename}/serve', [\App\Http\Controllers\Api\V1\CustomFontController::class, 'serve']);

        // Publishing
        // Starter templates
        // Available themes (system themes — used by Dashboard)
        Route::get('available-themes', function () {
            $themes = \App\Models\Theme::where('is_system', true)
                ->select('id', 'name', 'slug', 'description', 'modes')
                ->get()->map(fn($t) => [
                    'id' => $t->id, 'name' => $t->name, 'slug' => $t->slug,
                    'description' => $t->description, 'modes' => $t->modes ?? ['light'],
                ]);
            return response()->json(['data' => $themes]);
        });

        Route::get('starter-templates', function () {
            return response()->json(['data' => app(\App\Domain\Sites\Services\StarterTemplateService::class)->getTemplates()]);
        });
        Route::post('sites/{site}/apply-template', function (\Illuminate\Http\Request $request, \App\Models\Site $site) {
            $request->validate([
                'template' => 'required|string|max:50',
                'topic' => 'sometimes|nullable|string|max:120', // business type → AI-tailored copy (Full Site)
            ]);
            $result = app(\App\Domain\Sites\Services\StarterTemplateService::class)
                ->apply($site, $request->input('template'), $request->input('topic'));
            return response()->json(['data' => $result], $result['success'] ? 200 : 422);
        });

        // Form submissions
        Route::get('sites/{site}/form-submissions', [\App\Http\Controllers\Api\V1\FormController::class, 'submissions']);
        Route::delete('sites/{site}/form-submissions/{index}', [\App\Http\Controllers\Api\V1\FormController::class, 'deleteSubmission']);

        // Block templates / Library (Builder Experience P1)
        Route::get('sites/{site}/block-templates', [\App\Http\Controllers\Api\V1\BlockTemplateController::class, 'index']);
        Route::post('sites/{site}/block-templates', [\App\Http\Controllers\Api\V1\BlockTemplateController::class, 'store']);
        Route::post('sites/{site}/block-templates/import', [\App\Http\Controllers\Api\V1\BlockTemplateController::class, 'import']);
        Route::get('sites/{site}/block-templates/{template}', [\App\Http\Controllers\Api\V1\BlockTemplateController::class, 'show']);
        Route::patch('sites/{site}/block-templates/{template}', [\App\Http\Controllers\Api\V1\BlockTemplateController::class, 'update']);
        Route::delete('sites/{site}/block-templates/{template}', [\App\Http\Controllers\Api\V1\BlockTemplateController::class, 'destroy']);

        Route::post('sites/{site}/publish', [PublishController::class, 'publish']);
        Route::post('sites/{site}/publish/clear', [PublishController::class, 'clear']);
        Route::get('sites/{site}/download-zip', [PublishController::class, 'downloadZip']);
        Route::get('sites/{site}/deployments', [PublishController::class, 'history']);
        Route::get('sites/{site}/deployments/{deployment}', [PublishController::class, 'status']);
        Route::post('sites/{site}/deployments/{deployment}/rollback', [PublishController::class, 'rollback']);

        // Activity Logs
        Route::get('sites/{site}/activity', function (\App\Models\Site $site, \Illuminate\Http\Request $request) {
            $logs = \App\Models\ActivityLog::where('site_id', $site->id)
                ->orderByDesc('created_at')
                ->limit($request->integer('limit', 50))
                ->get();
            return response()->json(['data' => $logs]);
        });

        // Backup Export
        Route::get('sites/{site}/backup', function (\App\Models\Site $site) {
            $service = app(\App\Services\BackupExportService::class);
            $manifest = $service->export($site);
            return response()->json(['data' => $manifest]);
        });

        // Backup Restore Dry-Run
        Route::post('sites/{site}/backup/validate', function (\Illuminate\Http\Request $request, \App\Models\Site $site) {
            $request->validate(['manifest' => ['required', 'array']]);
            $service = app(\App\Services\BackupExportService::class);
            $result = $service->validateForRestore($request->input('manifest'));
            return response()->json(['data' => $result]);
        });

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

        // Theme Wizard (T3 — conversational, AI-assisted theme creation)
        Route::prefix('sites/{site}/theme-wizard')->group(function () {
            $c = \App\Http\Controllers\ThemeWizard\ThemeWizardController::class;
            Route::get('sessions', [$c, 'index']);
            Route::post('sessions/from-url', [$c, 'startUrl'])->middleware('throttle:10,1');
            Route::post('sessions/from-upload', [$c, 'startUpload'])->middleware('throttle:10,1');
            Route::post('sessions/from-conversation', [$c, 'startConversation'])->middleware('throttle:15,1');
            Route::get('sessions/{wizardSession}', [$c, 'show']);
            Route::post('sessions/{wizardSession}/nudge', [$c, 'nudge'])->middleware('throttle:20,1');
            Route::post('sessions/{wizardSession}/accept', [$c, 'accept']);
            Route::post('sessions/{wizardSession}/abandon', [$c, 'abandon']);
            Route::get('sessions/{wizardSession}/preview/{slug?}', [$c, 'preview']);
        });

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
            Route::get('sites/{site}/magazine-issues/{issue}/dtp-pdf', [\App\Http\Controllers\Api\V1\DtpPreviewController::class, 'pdf']);
            Route::get('sites/{site}/magazine-issues/{issue}/dtp-zip', [\App\Http\Controllers\Api\V1\DtpPreviewController::class, 'zip']);
            Route::get('sites/{site}/magazine-issues/{issue}/dtp-ad-clicks', [\App\Http\Controllers\Api\V1\DtpPreviewController::class, 'adClicks']);
            Route::get('sites/{site}/magazine-issues/{issue}/dtp-versions', [\App\Http\Controllers\Api\V1\DtpVersionController::class, 'index']);
            Route::post('sites/{site}/magazine-issues/{issue}/dtp-versions/{versionId}/restore', [\App\Http\Controllers\Api\V1\DtpVersionController::class, 'restore']);
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

// Magazine viewer ad-click beacon — PUBLIC (works from static/standalone
// deployments), throttled, no PII. sendBeacon posts text/plain JSON.
Route::post('mag-metric', function (\Illuminate\Http\Request $request) {
    $raw = $request->getContent();
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return response()->noContent();
    }
    $issueId = (string) ($data['issue'] ?? '');
    $href = substr((string) ($data['href'] ?? ''), 0, 500);
    if (!preg_match('/^[0-9a-f\-]{36}$/i', $issueId) || !preg_match('#^https?://#i', $href)) {
        return response()->noContent();
    }
    try {
        $tenant = \Illuminate\Support\Facades\DB::selectOne('SELECT tenant_id FROM magazine_issues WHERE id = ?', [$issueId]);
        if ($tenant) {
            $tid = preg_replace('/[^a-f0-9\-]/', '', $tenant->tenant_id);
            \Illuminate\Support\Facades\DB::statement("SET app.current_tenant_id = '{$tid}'");
            \Illuminate\Support\Facades\DB::table('mag_ad_clicks')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'issue_id' => $issueId,
                'href' => $href,
                'created_at' => now(),
            ]);
        }
    } catch (\Throwable $e) {
        // beacon must never error outward
    }
    return response()->noContent();
})->middleware('throttle:60,1');
