<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Grid\Services\GridRenderer;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\DeployService;
use App\Domain\Publishing\Services\SeoService;
use App\Domain\Publishing\Services\RssFeedGenerator;
use App\Domain\Publishing\Services\SitemapGenerator;
use App\Domain\Publishing\Services\RobotsGenerator;
use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Domain\Menus\Services\MenuRenderer;
use App\Events\DeploymentProgressEvent;
use App\Models\Deployment;
use App\Models\Grid;
use App\Models\Page;
use App\Models\PageVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class PublishSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 300;

    public string $deploymentId;
    public ?string $rollbackTargetId;
    public string $tenantId;
    public Deployment $deployment;

    public function __construct(
        Deployment $deployment,
        public string $type = 'partial',
        ?Deployment $rollbackTarget = null,
    ) {
        // Store IDs instead of models to avoid RLS issues during deserialization
        $this->deploymentId = $deployment->id;
        $this->rollbackTargetId = $rollbackTarget?->id;
        $this->tenantId = $deployment->site->tenant_id;
    }

    /**
     * Restore models manually with RLS context set.
     */
    public function restoreModels(): void
    {
        // Set RLS context for PostgreSQL
        $tenantId = preg_replace('/[^a-f0-9\-]/', '', $this->tenantId);
        DB::unprepared("SET app.current_tenant_id = '{$tenantId}'");
    }

    public function handle(
        BuildPageService $buildService,
        DeployService $deployService,
        SitemapGenerator $sitemapGenerator,
        RobotsGenerator $robotsGenerator,
    ): void {
        $this->restoreModels();

        $this->deployment = Deployment::findOrFail($this->deploymentId);
        $site = $this->deployment->site;
        $site->load('theme');
        $stagingPath = storage_path("app/builds/{$this->deployment->id}");

        try {
            $this->updateStatus('building', 'Starting build...');
            File::ensureDirectoryExists($stagingPath);

            // Compile theme CSS artifacts (before page rendering)
            $this->compileThemeArtifacts($site, $stagingPath);

            // Get publishable content
            $pages = $site->pages()->where('status', 'published')->orderBy('sort_order')->get();
            $posts = $site->posts()->with('category')->where('status', 'published')->orderByDesc('published_at')->get();

            $totalItems = $pages->count() + $posts->count();
            $this->deployment->update(['metadata' => array_merge(
                $this->deployment->metadata ?? [],
                ['pages_total' => $totalItems, 'pages_built' => 0]
            )]);

            $built = 0;
            $validationResults = [];

            // Build pages
            foreach ($pages as $page) {
                $result = $buildService->buildAndValidate($page, $site->theme, $site);
                $html = $result['html'];
                $validationResults["page:{$page->slug}"] = $result['validation'];

                $pagePath = $this->getPagePath($page);
                File::ensureDirectoryExists(dirname("{$stagingPath}/{$pagePath}"));
                File::put("{$stagingPath}/{$pagePath}", $html);

                // Create version snapshot
                $this->createVersion($page, 'page');

                $built++;
                $this->updateProgress($built, $totalItems, "Building page: {$page->title}");
            }

            // Build posts
            foreach ($posts as $post) {
                $result = $buildService->buildAndValidate($post, $site->theme, $site);
                $html = $result['html'];
                $validationResults["post:{$post->slug}"] = $result['validation'];
                $postPath = $this->getPostPath($post);
                File::ensureDirectoryExists(dirname("{$stagingPath}/{$postPath}"));
                File::put("{$stagingPath}/{$postPath}", $html);

                $this->createVersion($post, 'post');

                $built++;
                $this->updateProgress($built, $totalItems, "Building post: {$post->title}");
            }

            // Generate blog index, archives, and RSS
            if ($posts->isNotEmpty()) {
                $this->buildBlogIndex($site, $posts, $stagingPath);
                $this->buildCategoryArchives($site, $stagingPath);
                $this->buildTagArchives($site, $stagingPath);
                $this->buildAuthorArchives($site, $stagingPath);

                // RSS feed
                $rssGenerator = app(RssFeedGenerator::class);
                File::put("{$stagingPath}/feed.xml", $rssGenerator->generate($site));
            }

            // Build homepage based on homepage_type setting
            $this->buildHomepage($site, $stagingPath);

            // Generate sitemap, robots.txt, 404 page, and redirects
            File::put("{$stagingPath}/sitemap.xml", $sitemapGenerator->generate($site));
            File::put("{$stagingPath}/robots.txt", $robotsGenerator->generate($site));
            $this->build404Page($site, $stagingPath);
            $this->buildRedirectsManifest($site, $stagingPath);

            // Clean up static files for unpublished/draft posts
            $this->cleanUnpublishedPosts($site, $stagingPath);

            // Deploy
            $this->updateStatus('deploying', 'Deploying files...');
            $deployService->deploy($this->deployment, $stagingPath);

            // Mark live with validation results
            $allPassed = collect($validationResults)->every(fn($v) => $v['passed']);
            $totalWarnings = collect($validationResults)->sum(fn($v) => count($v['warnings']));

            $this->deployment->update([
                'status' => 'live',
                'completed_at' => now(),
                'metadata' => array_merge($this->deployment->metadata ?? [], [
                    'current_step' => 'live',
                    'pages_built' => $totalItems,
                    'lighthouse_checks' => [
                        'all_passed' => $allPassed,
                        'total_warnings' => $totalWarnings,
                        'results' => $validationResults,
                    ],
                ]),
            ]);

            $this->broadcast('Published successfully!');

            // Clean old builds (keep last 3)
            $this->cleanOldBuilds();
        } catch (\Throwable $e) {
            $this->deployment->update([
                'status' => 'failed',
                'error_log' => $e->getMessage() . "\n" . $e->getTraceAsString(),
                'completed_at' => now(),
            ]);
            $this->broadcast("Build failed: {$e->getMessage()}");
            throw $e;
        }
    }

    private function createVersion($content, string $type): void
    {
        $blocks = $content->blocks()->orderBy('order')->get()->toArray();
        $lastVersion = PageVersion::where("{$type}_id", $content->id)
            ->orderByDesc('version_number')
            ->first();

        PageVersion::create([
            "{$type}_id" => $content->id,
            'blocks_snapshot' => $blocks,
            'seo_snapshot' => $content->seo_meta ?? [],
            'published_by' => $this->deployment->triggered_by,
            'published_at' => now(),
            'version_number' => ($lastVersion?->version_number ?? 0) + 1,
        ]);
    }

    private function getPagePath($page): string
    {
        $site = $this->deployment->site;
        $homepageId = $site->settings['homepage_id'] ?? null;

        // Page is homepage if: explicitly set as homepage OR slug is 'home' (legacy fallback)
        $isHomepage = ($homepageId && $page->id === $homepageId) || (!$homepageId && $page->slug === 'home');
        $slug = $isHomepage ? '' : $page->slug;

        return ($slug ? "{$slug}/" : '') . 'index.html';
    }

    private function getPostPath($post): string
    {
        if ($post->category && $post->category->slug) {
            return "{$post->category->slug}/{$post->slug}/index.html";
        }
        return "{$post->slug}/index.html";
    }

    private function updateStatus(string $status, string $message): void
    {
        $this->deployment->update([
            'status' => $status,
            'started_at' => $this->deployment->started_at ?? now(),
            'metadata' => array_merge($this->deployment->metadata ?? [], ['current_step' => $status]),
        ]);
        $this->broadcast($message);
    }

    private function updateProgress(int $built, int $total, string $message): void
    {
        $this->deployment->update([
            'metadata' => array_merge($this->deployment->metadata ?? [], [
                'pages_built' => $built,
                'pages_total' => $total,
            ]),
        ]);
        $this->broadcast($message);
    }

    private function broadcast(string $message): void
    {
        try {
            event(new DeploymentProgressEvent(
                $this->deployment->site_id,
                $this->deployment->id,
                $this->deployment->status,
                $message,
                $this->deployment->metadata ?? [],
            ));
        } catch (\Throwable) {
            // Broadcasting may be disabled
        }
    }

    /**
     * Common template variables for all archive pages (nav, design tokens, CSS).
     */
    private function getArchiveVars($site): array
    {
        $themeConfig = $site->theme?->config ?? [];
        $menuRenderer = app(MenuRenderer::class);
        $tokenGenerator = app(\App\Domain\Theme\Services\DesignTokenGenerator::class);

        return [
            'site' => $site,
            'baseUrl' => $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu",
            'lang' => $themeConfig['lang'] ?? 'en',
            'criticalCss' => $themeConfig['critical_css'] ?? '',
            'customCss' => $site->settings['custom_css'] ?? '',
            'designTokensCss' => $tokenGenerator->generate($site),
            'navigation' => $menuRenderer->renderByLocation($site, 'header'),
            'footerNavigation' => $menuRenderer->renderByLocation($site, 'footer'),
            'rssUrl' => ($site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu") . '/feed.xml',
        ];
    }

    private function buildBlogIndex($site, $posts, string $stagingPath): void
    {
        $vars = $this->getArchiveVars($site);
        $perPage = 10;
        $totalPages = max(1, (int) ceil($posts->count() / $perPage));

        for ($page = 1; $page <= $totalPages; $page++) {
            $pagePosts = $posts->forPage($page, $perPage);
            $html = View::make('publishing.blog-index', array_merge($vars, [
                'posts' => $pagePosts,
                'currentPage' => $page,
                'totalPages' => $totalPages,
            ]))->render();

            $path = $page === 1 ? 'blog/index.html' : "blog/page/{$page}/index.html";
            File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
            File::put("{$stagingPath}/{$path}", $html);
        }
    }

    private function buildCategoryArchives($site, string $stagingPath): void
    {
        $vars = $this->getArchiveVars($site);
        $categories = $site->categories()->withCount('posts')->get();
        $buildService = app(\App\Domain\Publishing\Services\BuildPageService::class);

        foreach ($categories as $category) {
            $posts = $category->posts()->with(['category', 'author'])->where('status', 'published')->orderByDesc('published_at')->get();

            // Check for archive template
            $archiveTemplate = \App\Models\ThemeTemplate::resolveForArchive($site->id, $category->id);

            if ($archiveTemplate) {
                // Render archive using template blocks with context
                $html = $this->renderArchiveWithTemplate($archiveTemplate, $category, $posts, $site, $vars, $buildService);
            } else {
                // Collect child categories with their posts
                $children = $categories->where('parent_id', $category->id);
                $childData = [];
                foreach ($children as $child) {
                    $childPosts = $child->posts()->with('category')->where('status', 'published')->orderByDesc('published_at')->get();
                    if ($childPosts->isNotEmpty()) {
                        $childData[] = ['category' => $child, 'posts' => $childPosts];
                    }
                }

                $html = View::make('publishing.category-archive', array_merge($vars, [
                    'category' => $category,
                    'posts' => $posts,
                    'childCategories' => $childData,
                ]))->render();
            }

            $path = "blog/category/{$category->slug}/index.html";
            File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
            File::put("{$stagingPath}/{$path}", $html);
        }
    }

    private function renderArchiveWithTemplate(
        \App\Models\ThemeTemplate $template,
        $category,
        $posts,
        $site,
        array $vars,
        \App\Domain\Publishing\Services\BuildPageService $buildService,
    ): string {
        // Set archive context for dynamic blocks
        $archiveContext = [
            '__category' => $category,
            '__archivePosts' => $posts,
            '__archivePostCount' => $posts->count(),
            '__archiveCurrentPage' => 1,
            '__archiveTotalPages' => 1,
            '__archiveBaseUrl' => "/blog/category/{$category->slug}",
        ];

        // Render template blocks with archive context (safe try/finally inside)
        $templateBlocks = $template->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        $renderedBlocks = $buildService->renderBlocksWithContext($templateBlocks, $site, $archiveContext);

        $themeConfig = $site->theme?->config ?? [];
        $tokenGenerator = app(\App\Domain\Theme\Services\DesignTokenGenerator::class);

        $seoService = app(\App\Domain\Publishing\Services\SeoService::class);
        $headContent = '<title>' . e($category->name) . ' | ' . e($site->name) . '</title>';

        return View::make('publishing.layout', array_merge($vars, [
            'headContent' => $headContent,
            'headScripts' => '',
            'bodyScripts' => '',
            'fontPreloads' => $vars['fontPreloads'] ?? '',
            'hookHeadScripts' => '',
            'hookBodyOpen' => '',
            'hookBodyClose' => '',
            'renderedBlocks' => $renderedBlocks,
            'mainStyle' => 'max-width:var(--container-width,1080px);margin:0 auto;padding:0 1.5rem;',
            'content' => (object) ['title' => $category->name, 'seo_meta' => []],
            'themeConfig' => $themeConfig,
        ]))->render();
    }

    private function buildTagArchives($site, string $stagingPath): void
    {
        $vars = $this->getArchiveVars($site);
        $tags = $site->tags()->get();

        foreach ($tags as $tag) {
            $posts = $tag->posts()->where('status', 'published')->orderByDesc('published_at')->get();
            if ($posts->isEmpty()) continue;

            $html = View::make('publishing.tag-archive', array_merge($vars, [
                'tag' => $tag,
                'posts' => $posts,
            ]))->render();

            $path = "blog/tag/{$tag->slug}/index.html";
            File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
            File::put("{$stagingPath}/{$path}", $html);
        }
    }

    private function buildAuthorArchives($site, string $stagingPath): void
    {
        $vars = $this->getArchiveVars($site);

        $authorIds = $site->posts()->where('status', 'published')->whereNotNull('author_id')
            ->distinct()->pluck('author_id');

        foreach ($authorIds as $authorId) {
            $author = \App\Models\User::find($authorId);
            if (!$author) continue;

            $posts = $site->posts()->where('status', 'published')->where('author_id', $authorId)
                ->orderByDesc('published_at')->get();

            $html = View::make('publishing.author-archive', array_merge($vars, [
                'author' => $author,
                'posts' => $posts,
            ]))->render();

            $slug = \Illuminate\Support\Str::slug($author->name);
            $path = "blog/author/{$slug}/index.html";
            File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
            File::put("{$stagingPath}/{$path}", $html);
        }
    }

    private function build404Page($site, string $stagingPath): void
    {
        $themeConfig = $site->theme?->config ?? [];
        $menuRenderer = app(MenuRenderer::class);

        $html = View::make('publishing.error-404', [
            'site' => $site,
            'lang' => $themeConfig['lang'] ?? 'en',
            'criticalCss' => $themeConfig['critical_css'] ?? '',
            'customCss' => $site->settings['custom_css'] ?? '',
            'navigation' => $menuRenderer->renderByLocation($site, 'header'),
            'footerNavigation' => $menuRenderer->renderByLocation($site, 'footer'),
        ])->render();

        File::put("{$stagingPath}/404.html", $html);
    }

    private function buildRedirectsManifest($site, string $stagingPath): void
    {
        $redirects = \App\Models\Redirect::where('site_id', $site->id)->get();
        if ($redirects->isEmpty()) return;

        // Generate _redirects file (Netlify/Cloudflare Pages format)
        $lines = [];
        foreach ($redirects as $r) {
            $lines[] = "{$r->source_path} {$r->target_url} {$r->status_code}";
        }
        File::put("{$stagingPath}/_redirects", implode("\n", $lines));

        // Also generate .htaccess rules for Apache
        $htaccess = "# CMS Redirects\n";
        $htaccess .= "RewriteEngine On\n";
        foreach ($redirects as $r) {
            $flag = $r->status_code === 301 ? 'R=301,L' : 'R=302,L';
            $source = preg_quote($r->source_path, '/');
            $htaccess .= "RewriteRule ^" . ltrim($r->source_path, '/') . "$ {$r->target_url} [{$flag}]\n";
        }

        // Append to existing .htaccess or create new
        $htaccessPath = "{$stagingPath}/.htaccess";
        if (file_exists($htaccessPath)) {
            File::append($htaccessPath, "\n" . $htaccess);
        } else {
            File::put($htaccessPath, $htaccess);
        }
    }

    private function buildHomepage($site, string $stagingPath): void
    {
        $settings = $site->settings ?? [];
        $homepageType = $settings['homepage_type'] ?? 'page';

        if ($homepageType === 'grid') {
            $gridId = $settings['homepage_grid_id'] ?? null;
            if (!$gridId) return;

            $grid = Grid::with('positions')->find($gridId);
            if (!$grid) return;

            // Create a virtual page for the grid renderer
            $virtualPage = new Page();
            $virtualPage->id = '00000000-0000-0000-0000-000000000000';
            $virtualPage->site_id = $site->id;
            $virtualPage->title = $site->name;
            $virtualPage->slug = '';
            $virtualPage->status = 'published';

            $gridRenderer = app(GridRenderer::class);
            $gridResult = $gridRenderer->render($grid, $virtualPage, $site);

            $tokenGenerator = app(DesignTokenGenerator::class);
            $themeConfig = $site->theme?->config ?? [];
            $rssUrl = ($site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu") . '/feed.xml';

            $html = View::make('publishing.grid-layout', [
                'headContent' => '<title>' . e($site->name) . '</title>',
                'headScripts' => $settings['head_scripts'] ?? '',
                'bodyScripts' => $settings['body_scripts'] ?? '',
                'customCss' => $settings['custom_css'] ?? '',
                'criticalCss' => $themeConfig['critical_css'] ?? '',
                'fontPreloads' => '',
                'cssFile' => $themeConfig['css_file'] ?? null,
                'gridCss' => $gridResult['css'],
                'gridHtml' => $gridResult['html'],
                'designTokensCss' => $tokenGenerator->generate($site),
                'hookHeadScripts' => '',
                'hookBodyOpen' => '',
                'hookBodyClose' => '',
                'site' => $site,
                'rssUrl' => $rssUrl,
                'lang' => $themeConfig['lang'] ?? 'bg',
            ])->render();

            File::put("{$stagingPath}/index.html", $html);
        } elseif ($homepageType === 'blog') {
            // Blog feed as homepage — copy blog index to root
            if (file_exists("{$stagingPath}/blog/index.html")) {
                File::copy("{$stagingPath}/blog/index.html", "{$stagingPath}/index.html");
            }
        }
        // For 'page' type, getPagePath() already handles writing index.html
    }

    /**
     * Remove static files for posts that are no longer published (draft, archived, deleted).
     * This ensures unpublished posts don't remain accessible on the public site.
     */
    /**
     * Remove static files for posts no longer published + clean old /blog/ paths.
     */
    private function cleanUnpublishedPosts($site, string $stagingPath): void
    {
        $publicPath = config('publishing.public_path');

        // Build set of all valid published post paths
        $publishedPaths = [];
        $publishedSlugs = [];
        $posts = $site->posts()->with('category')->where('status', 'published')->get();
        foreach ($posts as $post) {
            $publishedPaths[] = $this->getPostPath($post);
            $publishedSlugs[] = $post->slug;
        }

        // Also get all page slugs so we don't accidentally delete pages
        $pageSlugs = $site->pages()->pluck('slug')->toArray();

        // Clean old /blog/ post directories (legacy paths from before URL change)
        $blogPath = $publicPath . '/blog';
        if (is_dir($blogPath)) {
            foreach (scandir($blogPath) as $entry) {
                if ($entry === '.' || $entry === '..' || !is_dir($blogPath . '/' . $entry)) continue;
                // Skip known blog infrastructure dirs
                if (in_array($entry, ['category', 'tag', 'author', 'page'])) continue;

                $fullPath = $blogPath . '/' . $entry;
                // If it has index.html, it's a post at /blog/{slug}/ — remove it
                if (file_exists($fullPath . '/index.html')) {
                    File::deleteDirectory($fullPath);
                } else {
                    // Category subfolder — clean post dirs inside
                    foreach (scandir($fullPath) as $sub) {
                        if ($sub === '.' || $sub === '..') continue;
                        $subPath = $fullPath . '/' . $sub;
                        if (is_dir($subPath) && file_exists($subPath . '/index.html')) {
                            File::deleteDirectory($subPath);
                        }
                    }
                    // Remove category dir if empty
                    if (is_dir($fullPath) && count(scandir($fullPath)) === 2) rmdir($fullPath);
                }
            }
        }

        // Clean category dirs at root level for unpublished posts
        $categories = $site->categories()->get();
        foreach ($categories as $category) {
            $catPath = $publicPath . '/' . $category->slug;
            if (!is_dir($catPath)) continue;

            foreach (scandir($catPath) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $postDir = $catPath . '/' . $entry;
                if (!is_dir($postDir) || !file_exists($postDir . '/index.html')) continue;

                $expectedPath = $category->slug . '/' . $entry . '/index.html';
                if (!in_array($expectedPath, $publishedPaths)) {
                    File::deleteDirectory($postDir);
                }
            }
            // Remove category dir if empty
            if (is_dir($catPath) && count(scandir($catPath)) === 2) rmdir($catPath);
        }
    }

    /**
     * Compile theme CSS artifacts for each mode the site supports.
     */
    private function compileThemeArtifacts($site, string $stagingPath): void
    {
        try {
            $compiler = app(\App\Services\Theme\ThemeCompiler::class);
            $modes = ['light']; // Always compile light mode

            // Check if theme has dark mode
            if ($site->theme?->modes && in_array('dark', $site->theme->modes)) {
                $modes[] = 'dark';
            }

            foreach ($modes as $mode) {
                $version = $compiler->compile($site->id, $mode);
                if ($version && $version->css_artifact_path) {
                    // Copy CSS artifact to staging path
                    $cssContent = \Illuminate\Support\Facades\Storage::disk('local')->get($version->css_artifact_path);
                    if ($cssContent) {
                        File::ensureDirectoryExists("{$stagingPath}/themes/site-{$site->id}");
                        File::put("{$stagingPath}/{$version->css_artifact_path}", $cssContent);
                    }
                    $this->broadcast("Compiled theme ({$mode} mode)");
                }
            }
        } catch (\Throwable $e) {
            // Theme compilation failure should not block the publish
            $this->broadcast("Theme compilation skipped: {$e->getMessage()}");
        }
    }

    private function cleanOldBuilds(): void
    {
        $buildPath = storage_path('app/builds');
        if (!File::isDirectory($buildPath)) return;

        $dirs = collect(File::directories($buildPath))
            ->sortByDesc(fn($d) => File::lastModified($d))
            ->values();

        foreach ($dirs->slice(3) as $old) {
            File::deleteDirectory($old);
        }
    }
}
