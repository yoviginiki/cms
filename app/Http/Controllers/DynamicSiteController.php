<?php

namespace App\Http\Controllers;

use App\Domain\Grid\Services\GridResolver;
use App\Domain\Publishing\Services\BuildPageService;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

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
            return $this->blogIndex($request);
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
            abort(404, "Page not found: {$slug}");
        }

        return $this->renderContent($page, $site);
    }

    /**
     * Serve a blog post by slug.
     */
    public function post(Request $request, string $siteSlug, string $slug): Response
    {
        $site = $this->resolveSite($siteSlug);
        $post = Post::where('site_id', $site->id)->where('slug', $slug)->first();

        if (!$post) {
            abort(404, "Post not found: {$slug}");
        }

        return $this->renderContent($post, $site);
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
        $html = $this->buildService->build($content, $site->theme, $site);

        // Resolve grid for toolbar info
        $grid = $this->gridResolver->resolve($content, $site);

        // Inject admin toolbar
        $toolbar = $this->buildToolbar($content, $site, $grid);
        $html = str_replace('</body>', $toolbar . '</body>', $html);

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Build admin toolbar HTML injected into every page.
     */
    private function buildToolbar(Page|Post $content, Site $site, $grid): string
    {
        $user = Auth::user();
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
  <a href="{$editUrl}" target="_blank" style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;background:#3b82f6;color:#fff;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    Редактирай
  </a>
  <a href="/admin/sites/{$site->id}" target="_blank" style="padding:4px 12px;background:#334155;color:#94a3b8;border-radius:6px;text-decoration:none;font-size:12px;">Dashboard</a>
  <span style="color:#64748b;font-size:11px;">{$user?->name}</span>
</div>
<style>#cms-toolbar ~ *:first-of-type, body > :not(#cms-toolbar):first-child { margin-top: 40px !important; } .site-grid-wrap, .site-grid { margin-top: 0 !important; } body { padding-top: 40px; }</style>
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
  <a href="/sites/{$site->slug}/blog/{$slug}" style="font-size:1.25rem;font-weight:600;color:var(--color-text,#1e293b);text-decoration:none;">{$title}</a>
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
