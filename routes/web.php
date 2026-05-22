<?php

use App\Http\Controllers\DocsController;
use App\Http\Controllers\DynamicSiteController;
use App\Http\Controllers\MagazineViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

// Named login route — redirects to admin SPA (which handles its own login)
Route::get('/login', function () {
    return redirect('/admin');
})->name('login');

// ─── Dynamic site preview (auth-protected) ───
// Serves pages/posts dynamically with admin toolbar
Route::middleware(['auth', \App\Http\Middleware\SetTenantFromAuth::class])->prefix('sites/{siteSlug}')->group(function () {
    Route::get('/', [DynamicSiteController::class, 'home'])->name('site.home');
    Route::get('/{categorySlug}/{postSlug}', [DynamicSiteController::class, 'post'])->name('site.post');
    Route::get('/{slug}', [DynamicSiteController::class, 'page'])->name('site.page');
});

// ─── Public asset serve (for magazine viewer images) ───
Route::get('/assets/{siteId}/serve/{assetId}/{variant?}', function (string $siteId, string $assetId, ?string $variant = null) {
    // Set tenant context for RLS
    $tenant = \Illuminate\Support\Facades\DB::selectOne("SELECT id FROM tenants LIMIT 1");
    if ($tenant) {
        $tid = preg_replace('/[^a-f0-9\-]/', '', $tenant->id);
        \Illuminate\Support\Facades\DB::statement("SET app.current_tenant_id = '{$tid}'");
    }
    $site = \App\Models\Site::findOrFail($siteId);
    $asset = \App\Models\Asset::where('site_id', $site->id)->findOrFail($assetId);
    return app(\App\Http\Controllers\Api\V1\AssetServeController::class)->serve($site, $asset, $variant);
})->name('public.asset.serve');

// ─── Magazine viewer (public) ───
Route::get('/magazine', [MagazineViewController::class, 'index'])->name('magazine.index');
Route::get('/magazines', [MagazineViewController::class, 'index']); // alias
Route::get('/magazine/dtp/{issueId}', [MagazineViewController::class, 'showDtpIssue'])->name('magazine.dtp');
Route::get('/magazine/{slug}', [MagazineViewController::class, 'show'])->name('magazine.show');
Route::get('/issue/{slug}', [MagazineViewController::class, 'showPage'])->name('magazine.page');

// ─── Documentation (public) ───
Route::prefix('docs')->group(function () {
    Route::get('/', [DocsController::class, 'index'])->name('docs.index');
    Route::get('/download', [DocsController::class, 'download'])->name('docs.download');
    Route::get('/{slug}', [DocsController::class, 'show'])->name('docs.show');
});

// ─── Admin SPA ───
Route::get('/admin/{any?}', function () {
    return view('admin');
})->where('any', '.*');
