<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Grid\Services\GridRenderer;
use App\Domain\Menus\Services\MenuRenderer;
use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Models\Grid;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class SmartPublisher
{
    public function __construct(
        private BuildPageService $buildService,
        private SitemapGenerator $sitemapGenerator,
        private RobotsGenerator $robotsGenerator,
        private RssFeedGenerator $rssFeedGenerator,
        private MenuRenderer $menuRenderer,
        private DesignTokenGenerator $tokenGenerator,
        private GridRenderer $gridRenderer,
    ) {}

    /**
     * Publish only the targets identified by the dependency graph.
     * Returns the number of files rebuilt.
     */
    public function publishTargets(Site $site, array $targets): int
    {
        $site->load('theme');
        $docRoot = config('publishing.public_path');

        // For custom domain sites, write directly to docroot
        if ($site->custom_domain) {
            $docRoot = config('publishing.public_path');
        } else {
            $docRoot = config('publishing.public_path') . '/' . $site->slug;
        }

        // Resolve actual docroot for this site
        $publishPath = $site->custom_domain
            ? config('publishing.public_path')
            : config('publishing.public_path') . '/' . $site->slug;

        $count = 0;
        $settings = $site->settings ?? [];
        $homepageType = $settings['homepage_type'] ?? 'page';
        $homepageId = $settings['homepage_id'] ?? null;
        $homepageGridId = $settings['homepage_grid_id'] ?? null;

        // Build homepage based on type
        $this->publishHomepage($site, $publishPath, $homepageType, $homepageId, $homepageGridId);
        $count++;

        // Rebuild specific pages
        foreach ($targets['pages'] as $pageId) {
            $page = Page::find($pageId);
            if (!$page || $page->status !== 'published') continue;

            $html = $this->buildService->build($page, $site->theme, $site);
            // Check if this page is the homepage (don't duplicate it)
            $isHome = $homepageType === 'page' && (
                ($homepageId && $page->id === $homepageId) || (!$homepageId && $page->slug === 'home')
            );
            $slug = $isHome ? '' : $page->slug;
            $path = ($slug ? "{$slug}/" : '') . 'index.html';

            File::ensureDirectoryExists(dirname("{$publishPath}/{$path}"));
            File::put("{$publishPath}/{$path}", $html);
            $count++;
        }

        // Rebuild specific posts
        foreach ($targets['posts'] as $postId) {
            $post = Post::find($postId);
            if (!$post || $post->status !== 'published') continue;

            $html = $this->buildService->build($post, $site->theme, $site);
            $path = "blog/{$post->slug}/index.html";

            File::ensureDirectoryExists(dirname("{$publishPath}/{$path}"));
            File::put("{$publishPath}/{$path}", $html);
            $count++;
        }

        // Rebuild specific archives
        $archiveVars = $this->getArchiveVars($site);

        foreach ($targets['archives'] as $archive) {
            if ($archive === 'blog_index') {
                $this->rebuildBlogIndex($site, $publishPath, $archiveVars);
                $count++;
            } elseif (str_starts_with($archive, 'category:')) {
                $slug = substr($archive, 9);
                $this->rebuildCategoryArchive($site, $slug, $publishPath, $archiveVars);
                $count++;
            } elseif (str_starts_with($archive, 'tag:')) {
                $slug = substr($archive, 4);
                $this->rebuildTagArchive($site, $slug, $publishPath, $archiveVars);
                $count++;
            } elseif (str_starts_with($archive, 'author:')) {
                $authorId = substr($archive, 7);
                $this->rebuildAuthorArchive($site, $authorId, $publishPath, $archiveVars);
                $count++;
            } elseif ($archive === 'categories') {
                foreach ($site->categories()->get() as $cat) {
                    $this->rebuildCategoryArchive($site, $cat->slug, $publishPath, $archiveVars);
                    $count++;
                }
            } elseif ($archive === 'tags') {
                foreach ($site->tags()->get() as $tag) {
                    $this->rebuildTagArchive($site, $tag->slug, $publishPath, $archiveVars);
                    $count++;
                }
            }
        }

        // Rebuild feeds
        foreach ($targets['feeds'] as $feed) {
            if ($feed === 'rss') {
                File::put("{$publishPath}/feed.xml", $this->rssFeedGenerator->generate($site));
                $count++;
            } elseif ($feed === 'sitemap') {
                File::put("{$publishPath}/sitemap.xml", $this->sitemapGenerator->generate($site));
                $count++;
            }
        }

        return $count;
    }

    /**
     * Publish the homepage based on the configured type.
     */
    private function publishHomepage(Site $site, string $publishPath, string $type, ?string $pageId, ?string $gridId): void
    {
        $site->refresh();
        $site->load('theme');
        $html = null;

        if ($type === 'grid' && $gridId) {
            // Grid-based homepage — render the grid directly
            $grid = Grid::with('positions')->find($gridId);
            if ($grid) {
                $gridResult = $this->gridRenderer->render($grid, $this->createVirtualPage($site), $site);
                $html = $this->buildGridHomepageHtml($gridResult, $site);
            }
        } elseif ($type === 'blog') {
            // Blog feed as homepage — reuse blog index template
            $archiveVars = $this->getArchiveVars($site);
            $posts = $site->posts()->where('status', 'published')
                ->orderByDesc('published_at')->limit(20)->get();

            $html = View::make('publishing.blog-index', array_merge($archiveVars, [
                'posts' => $posts,
                'isHomepage' => true,
            ]))->render();
        } else {
            // Page-based homepage (default)
            $page = $pageId ? Page::find($pageId) : null;
            if (!$page) {
                $page = Page::where('site_id', $site->id)->where('slug', 'home')->where('status', 'published')->first();
            }
            if (!$page) {
                $page = Page::where('site_id', $site->id)->where('status', 'published')->orderBy('sort_order')->first();
            }
            if ($page) {
                $html = $this->buildService->build($page, $site->theme, $site);
            }
        }

        if ($html) {
            File::put("{$publishPath}/index.html", $html);
        }
    }

    /**
     * Create a virtual page for grid-only homepage rendering.
     */
    private function createVirtualPage(Site $site): Page
    {
        $page = new Page();
        $page->id = '00000000-0000-0000-0000-000000000000';
        $page->site_id = $site->id;
        $page->title = $site->name;
        $page->slug = '';
        $page->status = 'published';
        return $page;
    }

    /**
     * Build full HTML for a grid-only homepage.
     */
    private function buildGridHomepageHtml(array $gridResult, Site $site): string
    {
        $designTokensCss = $this->tokenGenerator->generate($site);
        $themeConfig = $site->theme?->config ?? [];
        $rssUrl = ($site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu") . '/feed.xml';

        return View::make('publishing.grid-layout', [
            'headContent' => '<title>' . e($site->name) . '</title>',
            'headScripts' => $site->settings['head_scripts'] ?? '',
            'bodyScripts' => $site->settings['body_scripts'] ?? '',
            'customCss' => $site->settings['custom_css'] ?? '',
            'criticalCss' => $themeConfig['critical_css'] ?? '',
            'fontPreloads' => '',
            'cssFile' => $themeConfig['css_file'] ?? null,
            'gridCss' => $gridResult['css'],
            'gridHtml' => $gridResult['html'],
            'designTokensCss' => $designTokensCss,
            'hookHeadScripts' => '',
            'hookBodyOpen' => '',
            'hookBodyClose' => '',
            'site' => $site,
            'rssUrl' => $rssUrl,
            'lang' => $site->settings['default_language'] ?? $themeConfig['lang'] ?? 'en',
        ])->render();
    }

    private function getArchiveVars(Site $site): array
    {
        $themeConfig = $site->theme?->config ?? [];
        return [
            'site' => $site,
            'baseUrl' => $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu",
            'lang' => $site->settings['default_language'] ?? $themeConfig['lang'] ?? 'en',
            'criticalCss' => $themeConfig['critical_css'] ?? '',
            'customCss' => $site->settings['custom_css'] ?? '',
            'designTokensCss' => $this->tokenGenerator->generate($site),
            'navigation' => $this->menuRenderer->renderByLocation($site, 'header'),
            'footerNavigation' => $this->menuRenderer->renderByLocation($site, 'footer'),
            'rssUrl' => ($site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu") . '/feed.xml',
        ];
    }

    private function rebuildBlogIndex(Site $site, string $path, array $vars): void
    {
        $posts = $site->posts()->where('status', 'published')->orderByDesc('published_at')->get();
        $html = View::make('publishing.blog-index', array_merge($vars, [
            'posts' => $posts, 'currentPage' => 1, 'totalPages' => 1,
        ]))->render();
        File::ensureDirectoryExists("{$path}/blog");
        File::put("{$path}/blog/index.html", $html);
    }

    private function rebuildCategoryArchive(Site $site, string $slug, string $path, array $vars): void
    {
        $category = $site->categories()->where('slug', $slug)->first();
        if (!$category) return;
        $posts = $category->posts()->where('status', 'published')->orderByDesc('published_at')->get();

        $allCategories = $site->categories()->get();
        $children = $allCategories->where('parent_id', $category->id);
        $childData = [];
        foreach ($children as $child) {
            $childPosts = $child->posts()->where('status', 'published')->orderByDesc('published_at')->get();
            if ($childPosts->isNotEmpty()) {
                $childData[] = ['category' => $child, 'posts' => $childPosts];
            }
        }

        $html = View::make('publishing.category-archive', array_merge($vars, [
            'category' => $category, 'posts' => $posts, 'childCategories' => $childData,
        ]))->render();
        File::ensureDirectoryExists("{$path}/{$slug}");
        File::put("{$path}/{$slug}/index.html", $html);
    }

    private function rebuildTagArchive(Site $site, string $slug, string $path, array $vars): void
    {
        $tag = $site->tags()->where('slug', $slug)->first();
        if (!$tag) return;
        $posts = $tag->posts()->where('status', 'published')->orderByDesc('published_at')->get();
        $html = View::make('publishing.tag-archive', array_merge($vars, [
            'tag' => $tag, 'posts' => $posts,
        ]))->render();
        File::ensureDirectoryExists("{$path}/blog/tag/{$slug}");
        File::put("{$path}/blog/tag/{$slug}/index.html", $html);
    }

    private function rebuildAuthorArchive(Site $site, string $authorId, string $path, array $vars): void
    {
        $author = \App\Models\User::find($authorId);
        if (!$author) return;
        $posts = $site->posts()->where('status', 'published')->where('author_id', $authorId)->orderByDesc('published_at')->get();
        $slug = Str::slug($author->name);
        $html = View::make('publishing.author-archive', array_merge($vars, [
            'author' => $author, 'posts' => $posts,
        ]))->render();
        File::ensureDirectoryExists("{$path}/blog/author/{$slug}");
        File::put("{$path}/blog/author/{$slug}/index.html", $html);
    }
}
