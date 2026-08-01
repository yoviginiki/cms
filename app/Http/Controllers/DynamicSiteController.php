<?php

namespace App\Http\Controllers;

use App\Domain\Grid\Services\GridResolver;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Rendering\RenderContext;
use App\Domain\Publishing\Rendering\RenderMode;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DynamicSiteController extends Controller
{
    public function __construct(
        private BuildPageService $buildService,
        private GridResolver $gridResolver,
    ) {}

    /**
     * Serve the site homepage dynamically.
     */
    public function home(Request $request, string $siteSlug): Response
    {
        $site = $this->resolveSite($siteSlug);
        $settings = $site->settings ?? [];
        $homepageType = $settings['homepage_type'] ?? 'page';

        if ($homepageType === 'blog') {
            return $this->blogIndex($request, $siteSlug);
        }

        if ($homepageType === 'grid' && !empty($settings['homepage_grid_id'])) {
            $grid = \App\Models\Grid::with('positions')->find($settings['homepage_grid_id']);
            if ($grid) {
                $virtualPage = $this->findHomePage($site) ?? new Page([
                    'site_id' => $site->id, 'title' => $site->name, 'slug' => '', 'status' => 'published',
                ]);
                return $this->renderContent($virtualPage, $site);
            }
        }

        $page = $this->findHomePage($site);
        if (!$page) {
            abort(404, 'No homepage found. Go to Settings → Front page to configure one.');
        }

        return $this->renderContent($page, $site);
    }

    /**
     * Serve a page by slug.
     */
    public function page(Request $request, string $siteSlug, string $slug): Response
    {
        $site = $this->resolveSite($siteSlug);
        $page = Page::where('site_id', $site->id)->where('slug', $slug)->first();

        if (!$page) {
            // A collection archive lives at its prefix path (/products/).
            if ($collection = $this->findCollectionByPrefix($site, $slug)) {
                return $this->respondCollectionHtml(
                    $this->collectionPublish()->renderArchivePageHtml($site, $collection), $site);
            }
            // /{locale} — the homepage translation (slug convention {home}-{locale}).
            if ($this->enabledLocalePrefix($site, $slug)) {
                $home = $this->findHomePage($site);
                $translated = $home
                    ? Page::where('site_id', $site->id)->where('slug', "{$home->slug}-{$slug}")->first()
                    : null;
                if ($translated) {
                    return $this->renderContent($translated, $site);
                }
            }
            abort(404, "Page not found: {$slug}");
        }

        return $this->renderContent($page, $site);
    }

    /**
     * Serve a blog post by category/post slug.
     */
    public function post(Request $request, string $siteSlug, string $categorySlug, string $postSlug): Response
    {
        $site = $this->resolveSite($siteSlug);
        $post = Post::where('site_id', $site->id)->where('slug', $postSlug)->first();

        if (!$post) {
            // /{locale}/{prefix} — a localized collection archive.
            if ($this->enabledLocalePrefix($site, $categorySlug)
                && ($collection = $this->findCollectionByPrefix($site, $postSlug))) {
                return $this->respondCollectionHtml(
                    $this->collectionPublish()->renderArchivePageHtml($site, $collection, 1, "{$categorySlug}/"), $site);
            }
            // /{locale}/{page-base-slug} — a translated page (slug convention {base}-{locale}).
            if ($this->enabledLocalePrefix($site, $categorySlug)) {
                $translated = Page::where('site_id', $site->id)->where('slug', "{$postSlug}-{$categorySlug}")->first();
                if ($translated) {
                    return $this->renderContent($translated, $site);
                }
            }
            // /{prefix}/{record-slug} — a collection record detail page.
            if ($collection = $this->findCollectionByPrefix($site, $categorySlug)) {
                $record = \App\Models\Record::where('collection_id', $collection->id)
                    ->where('status', 'published')->where('slug', $postSlug)->first();
                if ($record) {
                    return $this->respondCollectionHtml(
                        $this->collectionPublish()->renderRecordPageHtml($site, $collection, $record), $site);
                }
            }
            abort(404, "Post not found: {$postSlug}");
        }

        return $this->renderContent($post, $site);
    }

    /**
     * Deep collection paths the fixed routes can't match:
     *   /{prefix}/category/{root…leaf}/ — category listing page
     *   /{prefix}/page/{n}/             — archive pagination
     *   /{prefix}/{ancestors…}/{slug}/  — nested record detail
     */
    public function collectionPath(Request $request, string $siteSlug, string $path): Response
    {
        $site = $this->resolveSite($siteSlug);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        // Leading locale segment (/en/products/…) — strip it; the renderers
        // emit locale-prefixed URLs themselves.
        $localePrefix = '';
        if ($segments !== [] && $this->enabledLocalePrefix($site, $segments[0])) {
            $localePrefix = array_shift($segments) . '/';
        }
        if (count($segments) < 2) {
            abort(404);
        }

        $collection = $this->findCollectionByPrefix($site, $segments[0]);
        if (!$collection) {
            abort(404, "Not found: /{$path}");
        }
        $publish = $this->collectionPublish();

        // /{prefix}/page/{n}
        if ($segments[1] === 'page' && isset($segments[2]) && ctype_digit($segments[2])) {
            return $this->respondCollectionHtml(
                $publish->renderArchivePageHtml($site, $collection, (int) $segments[2], $localePrefix), $site);
        }

        // /{prefix}/category/{root…leaf}
        if ($segments[1] === 'category' && count($segments) > 2) {
            $node = null;
            $parentId = null;
            foreach (array_slice($segments, 2) as $slug) {
                $node = \App\Models\CollectionCategoryNode::where('collection_id', $collection->id)
                    ->where('slug', $slug)
                    ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $parentId))
                    ->first();
                if (!$node) {
                    abort(404, "Category not found: {$slug}");
                }
                $parentId = $node->id;
            }
            return $this->respondCollectionHtml(
                $publish->renderCategoryPageHtml($site, $collection, $node, trim($localePrefix, '/') ?: null), $site);
        }

        // /{prefix}/{ancestors…}/{slug} — record URL; the public slug is the
        // translation group's canonical (default-language) slug, so resolve
        // the canonical record first, then swap to the requested locale's
        // group sibling when a locale prefix is present.
        $record = \App\Models\Record::where('collection_id', $collection->id)
            ->where('status', 'published')->where('slug', end($segments))->first();
        if ($record && $localePrefix !== '' && $record->translation_group_id) {
            $wanted = trim($localePrefix, '/');
            $localeKey = \App\Support\Blocks\RecordDisplay::localeField($collection);
            if ($localeKey) {
                $sibling = \App\Models\Record::where('collection_id', $collection->id)
                    ->where('translation_group_id', $record->translation_group_id)
                    ->where('status', 'published')
                    ->whereRaw('data->>? = ?', [$localeKey, $wanted])
                    ->first();
                $record = $sibling ?: $record;
            }
        }
        if ($record) {
            return $this->respondCollectionHtml(
                $publish->renderRecordPageHtml($site, $collection, $record), $site);
        }

        abort(404, "Not found: /{$path}");
    }

    /** True when the segment is an enabled non-default site locale (URL prefix). */
    private function enabledLocalePrefix(Site $site, string $segment): bool
    {
        return preg_match('/^[a-z]{2}$/', $segment) === 1
            && $segment !== \App\Domain\Publishing\Services\LocalePaths::defaultLanguage($site)
            && in_array($segment, \App\Domain\Publishing\Services\LocalePaths::languages($site), true);
    }

    /** Collection whose public prefix (settings.path_prefix or slug) matches. */
    private function findCollectionByPrefix(Site $site, string $prefix): ?\App\Models\ContentCollection
    {
        return \App\Models\ContentCollection::where('site_id', $site->id)->get()
            ->first(fn ($c) => (($c->settings['path_prefix'] ?? null) ?: $c->slug) === $prefix);
    }

    private function collectionPublish(): \App\Domain\Collections\Services\CollectionPublishService
    {
        return app(\App\Domain\Collections\Services\CollectionPublishService::class);
    }

    /** Preview response for publisher-rendered collection HTML (links rewritten to the /sites prefix). */
    private function respondCollectionHtml(string $html, Site $site): Response
    {
        $html = $this->rewriteLinksForPreview($html, $site);

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Blog index — list all published posts.
     */
    public function blogIndex(Request $request, string $siteSlug): Response
    {
        $site = $this->resolveSite($siteSlug);
        $posts = Post::where('site_id', $site->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        $html = $this->renderBlogIndex($posts, $site);

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Render any page or post with admin toolbar.
     */
    private function renderContent(Page|Post $content, Site $site): Response
    {
        $site->load('theme');

        // ?_grid={id} — force-render through a specific grid (used by the
        // admin grid editor's live preview). Grid must belong to this site.
        $gridOverride = null;
        if ($overrideId = request()->query('_grid')) {
            $gridOverride = \App\Models\Grid::with('positions')
                ->where('site_id', $site->id)
                ->find($overrideId);
        }

        // Inline edit mode — gated on ?sp_edit=1 AND the update ability
        // (provisional gate; Phase 4 swaps it for page.inline_edit). Renders the
        // SAME preview through RenderMode::Edit so partials emit data-sp-*.
        $editMode = request()->query('sp_edit') === '1' && Gate::allows('inlineEdit', $content);

        $build = fn () => $this->buildService->build($content, $site->theme, $site, isPreview: true, gridOverride: $gridOverride);
        $html = $editMode
            ? app(RenderContext::class)->runIn(RenderMode::Edit, $build)
            : $build();

        // Rewrite internal links for the dynamic preview prefix
        // /about → /sites/cytechno/about, / → /sites/cytechno/
        $html = $this->rewriteLinksForPreview($html, $site);

        // Resolve grid for toolbar info
        $grid = $gridOverride ?? $this->gridResolver->resolve($content, $site);

        // Experience Mode runtime — inject when cinematic or ?experience=1
        $isExperience = ($content->experience_mode ?? 'standard') === 'cinematic'
            || request()->query('experience') === '1';
        if ($isExperience) {
            $experienceAssets = '<link rel="stylesheet" href="/assets/experience/experience-runtime.a44ae8ee.css">'
                . "\n" . '<script defer src="/assets/experience/experience-runtime.a44ae8ee.js"></script>';
            $html = str_replace('</head>', $experienceAssets . "\n</head>", $html);
        }

        // Inject admin toolbar (?_toolbar=0 hides it, e.g. inside admin iframes)
        if (request()->query('_toolbar') !== '0') {
            $toolbar = $this->buildToolbar($content, $site, $grid, $editMode);
            $html = str_replace('</body>', $toolbar . '</body>', $html);
        }

        // Inline-edit overlay + toolbar — only in edit mode, lazily loaded.
        if ($editMode) {
            $html = str_replace('</body>', $this->inlineEditAssets($content, $site) . '</body>', $html);
        }

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Rewrite internal links so they work under /sites/{slug}/ prefix.
     * Only rewrites links that start with / and point to site pages/posts/categories.
     * Preserves external URLs, anchors, and API paths.
     */
    private function rewriteLinksForPreview(string $html, Site $site): string
    {
        $prefix = '/sites/' . $site->slug;

        // Collect all valid page slugs and category slugs for this site
        $pageSlugs = $site->pages()->pluck('slug')->toArray();
        $categorySlugs = \App\Models\Category::where('site_id', $site->id)->pluck('slug')->toArray();
        $postSlugs = Post::where('site_id', $site->id)->pluck('slug')->toArray();

        $allSlugs = array_merge($pageSlugs, $categorySlugs, $postSlugs);

        return preg_replace_callback(
            '/href="(\/[^"]*)"/',
            function ($match) use ($prefix, $allSlugs) {
                $path = $match[1];

                // Skip API paths, admin paths, assets, fonts, already-prefixed paths
                if (str_starts_with($path, '/api/')
                    || str_starts_with($path, '/admin')
                    || str_starts_with($path, '/sites/')
                    || str_starts_with($path, '/fonts/')
                    || str_starts_with($path, '/assets/')
                    || str_starts_with($path, '/media/')
                ) {
                    return $match[0];
                }

                // Root path → site home
                if ($path === '/') {
                    return 'href="' . $prefix . '/"';
                }

                // Check if first segment matches a known slug
                $segments = explode('/', trim($path, '/'));
                if (!empty($segments[0]) && in_array($segments[0], $allSlugs)) {
                    return 'href="' . $prefix . $path . '"';
                }

                // Default: rewrite it (could be a page or post path)
                return 'href="' . $prefix . $path . '"';
            },
            $html
        );
    }

    /**
     * Config + lazy loaders for the inline-edit overlay and its parent-side
     * toolbar. Injected only in edit mode.
     */
    private function inlineEditAssets(Page|Post $content, Site $site): string
    {
        $type = $content instanceof Post ? 'post' : 'page';
        $editUrl = "/admin/sites/{$site->id}/{$type}s/{$content->id}/edit";

        // apiBase drives the inline save/session endpoints — pages and posts
        // both have the inline API ($type is 'page' or 'post').
        $apiBase = "/api/v1/sites/{$site->id}/{$type}s/{$content->id}/inline";

        $config = json_encode([
            'pageId' => $content->id,
            'versionId' => null,
            'parentOrigin' => rtrim(config('app.url'), '/'),
            'editUrl' => $editUrl,
            'apiBase' => $apiBase,
            'assetPickerUrl' => "/admin/sites/{$site->id}/asset-picker",
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<script>window.__SP_EDIT=' . $config . ';</script>'
            . '<script src="/inline-edit/overlay.js" defer></script>'
            . '<script src="/inline-edit/toolbar.js" defer></script>';
    }

    /**
     * Build admin toolbar HTML injected into every page.
     */
    private function buildToolbar(Page|Post $content, Site $site, $grid, bool $editMode = false): string
    {
        $user = Auth::user();

        // Inline-edit toggle — for pages and posts, only for users who may
        // inline-edit. Toggles ?sp_edit=1 on this same preview URL.
        $inlineBtn = '';
        if (Gate::allows('inlineEdit', $content)) {
            if ($editMode) {
                $exit = e(request()->fullUrlWithoutQuery(['sp_edit']));
                $inlineBtn = '<a href="' . $exit . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;background:#22c55e;color:#052e16;border-radius:6px;text-decoration:none;font-size:12px;font-weight:700;">✓ Изход от редакция</a>';
            } else {
                $enter = e(request()->fullUrlWithQuery(['sp_edit' => '1']));
                $inlineBtn = '<a href="' . $enter . '" style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;background:#6366f1;color:#fff;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">✏️ Редактирай на място</a>';
            }
        }

        $type = $content instanceof Post ? 'post' : 'page';
        $typeLabel = $content instanceof Post ? 'Пост' : 'Страница';
        $editUrl = "/admin/sites/{$site->id}/{$type}s/{$content->id}/edit";
        $gridName = $grid ? e($grid->name) : 'default';
        $gridId = $grid ? $grid->id : '';
        $gridEditUrl = $grid ? "/admin/sites/{$site->id}/grids/{$grid->id}" : '';
        $statusColor = match ($content->status) {
            'published' => '#22c55e',
            'draft' => '#f59e0b',
            'archived' => '#6b7280',
            default => '#9ca3af',
        };
        $categoryName = '';
        if ($content instanceof Post && $content->category_id) {
            $content->load('category');
            $categoryName = $content->category?->name ?? '';
        }
        $shortId = substr($content->id, 0, 8);
        $categoryHtml = $categoryName
            ? '<span style="color:#475569;">|</span><span style="color:#94a3b8;">Cat: ' . e($categoryName) . '</span>'
            : '';

        return <<<HTML
<div id="cms-toolbar" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:#1e293b;color:#e2e8f0;font-family:system-ui,-apple-system,sans-serif;font-size:13px;padding:0 16px;display:flex;align-items:center;gap:12px;height:40px;box-shadow:0 2px 8px rgba(0,0,0,0.3);">
  <span style="font-weight:700;color:#60a5fa;">sys.ensodo.eu</span>
  <span style="color:#475569;">|</span>
  <span style="display:inline-flex;align-items:center;gap:4px;">
    <span style="width:8px;height:8px;border-radius:50%;background:{$statusColor};display:inline-block;"></span>
    {$typeLabel}: <strong>{$content->title}</strong>
  </span>
  <span style="color:#64748b;font-family:monospace;font-size:11px;">#{$shortId}</span>
  <span style="color:#475569;">|</span>
  <span style="color:#94a3b8;">Grid: <a href="{$gridEditUrl}" style="color:#a78bfa;text-decoration:none;" target="_blank">{$gridName}</a></span>
  {$categoryHtml}
  <span style="flex:1;"></span>
  {$inlineBtn}
  <a href="{$editUrl}" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;background:#3b82f6;color:#fff;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    Page Editor
  </a>
  <a href="/admin/sites/{$site->id}" target="_blank" style="padding:4px 12px;background:#334155;color:#94a3b8;border-radius:6px;text-decoration:none;font-size:12px;">Dashboard</a>
  <span style="color:#64748b;font-size:11px;">{$user?->name}</span>
</div>
<style>body { padding-top: 40px; }</style>
HTML;
    }

    /**
     * Render blog index page.
     */
    private function renderBlogIndex(mixed $posts, Site $site): string
    {
        $site->load('theme');
        $siteName = e($site->name);

        $postsHtml = '';
        foreach ($posts as $post) {
            $date = $post->published_at?->format('M j, Y') ?? '';
            $cat = $post->category_id ? ($post->category?->name ?? '') : '';
            $catSlug = $post->category?->slug ?? 'uncategorized';
            $shortId = substr($post->id, 0, 8);
            $catBadge = $cat ? '<span style="font-size:11px;padding:2px 8px;background:#f1f5f9;border-radius:999px;color:#475569;">' . e($cat) . '</span>' : '';
            $statusBg = $post->status === 'published' ? '#dcfce7;color:#166534' : '#fef9c3;color:#854d0e';
            $excerptHtml = $post->excerpt ? '<p style="font-size:0.875rem;color:#64748b;margin-top:4px;">' . e($post->excerpt) . '</p>' : '';
            $title = e($post->title);
            $slug = e($post->slug);
            $status = $post->status;
            $postsHtml .= <<<HTML
<article style="padding:1.5rem 0;border-bottom:1px solid var(--color-border,#e5e7eb);">
  <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
    <span style="font-family:monospace;font-size:10px;color:#94a3b8;">#{$shortId}</span>
    <span style="font-size:12px;color:#64748b;">{$date}</span>
    {$catBadge}
    <span style="font-size:11px;padding:2px 8px;border-radius:999px;background:{$statusBg};">{$status}</span>
  </div>
  <a href="/sites/{$site->slug}/{$catSlug}/{$slug}" style="font-size:1.25rem;font-weight:600;color:var(--color-text,#1e293b);text-decoration:none;">{$title}</a>
  {$excerptHtml}
</article>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blog — {$siteName}</title>
  <meta name="robots" content="noindex">
  <style>body{font-family:system-ui,-apple-system,sans-serif;margin:0;padding:40px 0 0;color:#1e293b;background:#fff;}</style>
</head>
<body>
  <div style="max-width:900px;margin:0 auto;padding:2rem 24px;">
    <h1 style="font-size:2rem;font-weight:800;margin-bottom:0.5rem;">Blog Posts</h1>
    <p style="color:#64748b;margin-bottom:2rem;">{$posts->count()} posts (all statuses)</p>
    {$postsHtml}
  </div>
</body>
</html>
HTML;
    }

    /**
     * Resolve site by slug (must belong to user's tenant).
     */
    private function resolveSite(string $siteSlug): Site
    {
        $user = Auth::user();

        $site = Site::where('tenant_id', $user->tenant_id)
            ->where('slug', $siteSlug)
            ->first();

        if (!$site) {
            abort(404, "Site not found: {$siteSlug}");
        }

        return $site;
    }

    /**
     * Find homepage — slug "home" or first page.
     */
    private function findHomePage(Site $site): ?Page
    {
        $configuredId = $site->settings['homepage_id'] ?? null;
        if ($configuredId) {
            $page = Page::where('site_id', $site->id)->where('id', $configuredId)->first();
            if ($page) return $page;
        }

        return Page::where('site_id', $site->id)->where('slug', 'home')->first()
            ?? Page::where('site_id', $site->id)->orderBy('sort_order')->first();
    }
}
