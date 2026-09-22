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
    // Deep collection paths (category pages, archive pagination, nested records).
    Route::get('/{path}', [DynamicSiteController::class, 'collectionPath'])
        ->where('path', '.*')->name('site.collection-path');
});

// ─── Public asset serve (for magazine viewer images) ───
Route::get('/media/{siteId}/{assetId}/{variant?}', function (string $siteId, string $assetId, ?string $variant = null) {
    try {
        // F22: tenant context from the site id (any tenant), never "the first tenant".
        $site = app(\App\Domain\Tenancy\PublicTenantResolver::class)->siteById($siteId);
        if (!$site || !preg_match('/^[0-9a-f\-]{36}$/', $assetId)) {
            abort(404);
        }
        $asset = \App\Models\Asset::where('site_id', $site->id)->findOrFail($assetId);
        return app(\App\Http\Controllers\Api\V1\AssetServeController::class)->serve($site, $asset, $variant);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        throw $e;
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('Public asset serve failed: ' . $e->getMessage());
        abort(404);
    }
})->name('public.asset.serve');

// ─── Public Library preview thumbnails (streamed from the assets disk) ───
// No file extension in the URL — nginx serves .png/.jpg statically and would
// never reach Laravel (same reason the font route below omits extensions).
Route::get('/library-thumbnails/{id}', [\App\Http\Controllers\Api\V1\LibraryThumbnailController::class, 'serve'])
    ->where('id', '[0-9a-fA-F-]{36}')
    ->name('public.library.thumbnail');

// ─── Public font serve (nginx catches .ttf/.woff, so no extension in URL) ───
Route::get('/serve-font/{siteId}/{fontSlug}', function (string $siteId, string $fontSlug) {
    try {
        $site = app(\App\Domain\Tenancy\PublicTenantResolver::class)->siteById($siteId); // F22
        if (!$site) abort(404);
        $fonts = $site->settings['custom_fonts'] ?? [];
        $font = collect($fonts)->first(fn($f) => pathinfo($f['filename'] ?? '', PATHINFO_FILENAME) === $fontSlug);
        if (!$font) abort(404);
        return app(\App\Http\Controllers\Api\V1\CustomFontController::class)->serve($site, $font['filename']);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        throw $e;
    } catch (\Throwable) { abort(404); }
});

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

// ─── Fallback: resolve /{slug} to a site page/category (for menu links on sys.ensodo.eu) ───
Route::get('/{slug}', function (string $slug) {
    try {
        // F22: only meaningful on a site's own host; resolve THAT site's tenant.
        $site = app(\App\Domain\Tenancy\PublicTenantResolver::class)->siteByHost(request()->getHost());
        if ($site) {
            $page = \App\Models\Page::where('site_id', $site->id)->where('slug', $slug)->where('status', 'published')->first();
            if ($page) {
                return redirect("/sites/{$page->site_id}/{$slug}");
            }
            $category = \App\Models\Category::where('site_id', $site->id)->where('slug', $slug)->first();
            if ($category) {
                return redirect("/sites/{$category->site_id}/{$slug}");
            }
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::debug("Fallback slug resolve failed for /{$slug}: " . $e->getMessage());
    }
    abort(404);
})->where('slug', '[a-zA-Z0-9\-]+');
