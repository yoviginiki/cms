<?php

namespace App\Domain\Publishing\Jobs;

use App\Domain\Grid\Services\GridRenderer;
use App\Domain\Publishing\Services\AssetPublisher;
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
use App\Models\Site;
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
        // Via config, NOT a hardcoded storage_path: the test suite sandboxes
        // publishing.staging_path — hardcoding it made tests stage into the
        // production builds dir (and retention then pruned live builds).
        $stagingPath = rtrim(config('publishing.staging_path'), '/') . "/{$this->deployment->id}";

        try {
            // Rollback (FIX-B6b): re-point the live site to a prior deployment's
            // build instead of rebuilding current DB content. Previously the job
            // ignored the target and republished today's content (a silent no-op).
            if ($this->type === 'rollback' && $this->rollbackTargetId) {
                $target = Deployment::find($this->rollbackTargetId);
                $targetBuild = rtrim(config('publishing.staging_path'), '/') . "/{$this->rollbackTargetId}";
                if (!$target || !is_dir($targetBuild)) {
                    throw new \RuntimeException('Rollback target build no longer exists (pruned); cannot roll back to this deployment.');
                }
                $this->updateStatus('deploying', 'Rolling back...');
                $deployService->deploy($this->deployment, $targetBuild);
                $this->deployment->update([
                    'status' => 'rolled_back',
                    'artifact_path' => $targetBuild,
                    'completed_at' => now(),
                    'metadata' => array_merge($this->deployment->metadata ?? [], [
                        'current_step' => 'rolled_back',
                        'rolled_back_to' => $this->rollbackTargetId,
                    ]),
                ]);
                $this->broadcast('Rolled back successfully!');
                return;
            }

            $this->updateStatus('building', 'Starting build...');
            File::ensureDirectoryExists($stagingPath);

            // Assets publish INTO THE STAGING TREE so they ship with the build.
            // Writing them straight to the docroot (the old behavior) lost them
            // on every deploy: copyDeploy prunes files absent from staging, and
            // the symlink strategy swaps the docroot away entirely.
            AssetPublisher::reset();
            AssetPublisher::setDeployTarget($stagingPath);

            // Compile theme CSS artifacts (before page rendering)
            $this->compileThemeArtifacts($site, $stagingPath);

            // Copy custom fonts to staging
            $this->copyCustomFonts($site, $stagingPath);

            // Verbatim design files (exact-copy imports) ship with the build.
            $siteFiles = \App\Domain\Publishing\Services\SiteFilesPublisher::publish($site, $stagingPath);
            if ($siteFiles > 0) {
                $this->broadcast("Copied {$siteFiles} design file(s)");
            }

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
                $html = \App\Domain\Publishing\Services\LocalePaths::localizeHtml($site, $page, $result['html']);
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
                $html = \App\Domain\Publishing\Services\LocalePaths::localizeHtml($site, $post, $result['html']);
                $validationResults["post:{$post->slug}"] = $result['validation'];
                $postPath = $this->getPostPath($post);
                File::ensureDirectoryExists(dirname("{$stagingPath}/{$postPath}"));
                File::put("{$stagingPath}/{$postPath}", $html);

                $this->createVersion($post, 'post');

                $built++;
                $this->updateProgress($built, $totalItems, "Building post: {$post->title}");
            }

            // Static magazine viewers (W3-9: tenant domains are static-only —
            // published DTP issues become self-contained /magazine/... pages)
            $magBuilt = app(\App\Domain\Publishing\Services\MagazineStaticPublisher::class)
                ->publishForSite($site, $stagingPath);
            if ($magBuilt > 0) {
                $this->updateStatus('building', "Built {$magBuilt} magazine viewer(s)");
            }

            // Collections (Track G2): record detail pages + paginated
            // archives + static search indexes for static-tier collections —
            // independent of whether the site has posts.
            $publisher = app(\App\Domain\Collections\Services\CollectionPublishService::class);
            $collectionWarnings = array_merge(
                $publisher->buildAll($site, $stagingPath),
                $publisher->buildQueryFeeds($site, $stagingPath),
            );
            if ($collectionWarnings !== []) {
                $validationResults['site:collections'] = ['passed' => true, 'warnings' => $collectionWarnings, 'errors' => [], 'score_estimate' => 100];
            }
            \App\Models\Record::where('site_id', $site->id)->where('needs_republish', true)
                ->update(['needs_republish' => false, 'needs_republish_reason' => null]);
            $this->updateStatus('building', 'Built collections');

            // Generate blog index, archives, and RSS
            if ($posts->isNotEmpty()) {
                // Blog index + category/tag/author archives (shared with delta
                // publish via ArchiveBuildService — §7 D1). Archive lint
                // warnings surface in the deploy log like page warnings.
                $archiveWarnings = app(\App\Domain\Publishing\Services\ArchiveBuildService::class)->buildAll($site, $stagingPath);
                if ($archiveWarnings !== []) {
                    $validationResults['site:archives'] = ['passed' => true, 'warnings' => $archiveWarnings, 'errors' => [], 'score_estimate' => 100];
                }

                // RSS feed
                $rssGenerator = app(RssFeedGenerator::class);
                File::put("{$stagingPath}/feed.xml", $rssGenerator->generate($site));

                // Per-category feeds at /{category}/feed.xml (F4)
                $feedCategories = $site->categories()
                    ->whereHas('posts', fn ($q) => $q->where('status', 'published'))
                    ->get();
                foreach ($feedCategories as $feedCategory) {
                    File::ensureDirectoryExists("{$stagingPath}/{$feedCategory->slug}");
                    File::put(
                        "{$stagingPath}/{$feedCategory->slug}/feed.xml",
                        $rssGenerator->generateForCategory($site, $feedCategory)
                    );
                }
            }

            // Build homepage based on homepage_type setting
            $this->buildHomepage($site, $stagingPath);

            // Generate sitemap, robots.txt, llms.txt, 404 page, and redirects
            File::put("{$stagingPath}/sitemap.xml", $sitemapGenerator->generate($site));
            File::put("{$stagingPath}/robots.txt", $robotsGenerator->generate($site));
            File::put("{$stagingPath}/favicon.svg", app(\App\Domain\Publishing\Services\FaviconGenerator::class)->generate($site));
            if ($llmsTxt = app(\App\Domain\Publishing\Services\LlmsTxtGenerator::class)->generate($site)) {
                File::put("{$stagingPath}/llms.txt", $llmsTxt);
            }
            $this->build404Page($site, $stagingPath);
            $this->buildRedirectsManifest($site, $stagingPath);

            // Clean up static files for unpublished/draft posts
            $this->cleanUnpublishedPosts($site, $stagingPath);

            // F5 SEO lint — cross-page broken internal link check (warning-only)
            try {
                $linkWarnings = app(\App\Domain\Publishing\Services\InternalLinkChecker::class)->check(
                    $stagingPath,
                    50,
                    $site->custom_domain ? null : '/' . trim($site->deploySlug(), '/'),
                );
                if ($linkWarnings !== []) {
                    $validationResults['site:internal-links'] = [
                        'passed' => true,
                        'warnings' => $linkWarnings,
                        'errors' => [],
                        'score_estimate' => 100,
                    ];
                }
            } catch (\Throwable $e) {
                logger()->warning("Internal link check failed for site {$site->id}: {$e->getMessage()}");
            }

            // Deploy
            $this->updateStatus('deploying', 'Deploying files...');
            $deployService->deploy($this->deployment, $stagingPath);

            // Post-deploy layout audit (regression gate): sample pages at
            // phone + desktop widths — overflow, squeezed grids, background
            // seams, non-full-width dividers, edge-flush text. Fixes verified
            // only against one complaint kept silently breaking earlier fixes;
            // this surfaces any layout regression on the deploy that ships it.
            try {
                $layoutWarnings = $this->runLayoutAudit($site);
                if ($layoutWarnings !== []) {
                    $validationResults['site:layout-audit'] = [
                        'passed' => true,
                        'warnings' => $layoutWarnings,
                        'errors' => [],
                        'score_estimate' => 90,
                    ];
                }
            } catch (\Throwable $e) {
                logger()->warning("Layout audit failed for site {$site->id}: {$e->getMessage()}");
            }

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

            // Purge the CDN edge so the new build is visible immediately.
            // No-op unless Cloudflare credentials are configured; never fatal.
            try {
                $purged = \App\Domain\Publishing\Services\CloudflarePurger::purgeSite(
                    $site,
                    (string) ($this->deployment->refresh()->artifact_path ?: $stagingPath)
                );
                if ($purged > 0) {
                    $this->broadcast("Cloudflare cache purged ({$purged} URLs)");
                }
            } catch (\Throwable $e) {
                logger()->warning("Cloudflare purge failed for site {$site->id}: {$e->getMessage()}");
            }

            // A successful FULL rebuild covers every page — clear staleness flags
            try {
                app(\App\Domain\References\Services\StalenessResolver::class)->clearForSite($site);
            } catch (\Throwable $e) {
                logger()->warning("Staleness clear failed for site {$site->id}: {$e->getMessage()}");
            }

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
        // identical to the old inline logic for default-locale content;
        // translated content publishes under /{locale}/ with the suffix stripped
        return \App\Domain\Publishing\Services\LocalePaths::pagePath($this->deployment->site, $page);
    }

    private function getPostPath($post): string
    {
        return \App\Domain\Publishing\Services\LocalePaths::postPath($this->deployment->site, $post);
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

        // Security headers for the published static site (FIX-A4b): the CMS
        // previously emitted no CSP/HSTS/X-Frame/etc on published output, so
        // XSS had no backstop. Written on EVERY publish, redirects or not.
        $htaccess = "# CMS security headers\n";
        $htaccess .= "<IfModule mod_headers.c>\n";
        $htaccess .= "  Header always set X-Content-Type-Options \"nosniff\"\n";
        $htaccess .= "  Header always set X-Frame-Options \"SAMEORIGIN\"\n";
        $htaccess .= "  Header always set Referrer-Policy \"strict-origin-when-cross-origin\"\n";
        $htaccess .= "  Header always set Strict-Transport-Security \"max-age=31536000; includeSubDomains\"\n";
        $htaccess .= "  Header always set Content-Security-Policy \"default-src 'self'; img-src 'self' data: https:; media-src 'self' https:; font-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com; frame-src 'self' https://www.youtube-nocookie.com https://player.vimeo.com; connect-src 'self' https:\"\n";
        $htaccess .= "</IfModule>\n";

        if (!$redirects->isEmpty()) {
            // _redirects file (Netlify/Cloudflare Pages format)
            $lines = [];
            foreach ($redirects as $r) {
                $lines[] = "{$r->source_path} {$r->target_url} {$r->status_code}";
            }
            File::put("{$stagingPath}/_redirects", implode("\n", $lines));

            // .htaccess RewriteRules for Apache
            $htaccess .= "\n# CMS Redirects\nRewriteEngine On\n";
            foreach ($redirects as $r) {
                $flag = $r->status_code === 301 ? 'R=301,L' : 'R=302,L';
                $htaccess .= "RewriteRule ^" . ltrim($r->source_path, '/') . "$ {$r->target_url} [{$flag}]\n";
            }
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
            $rssUrl = $site->publicBaseUrl() . '/feed.xml';

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
        // Live-safe pruning: never delete a build a live site symlink targets
        // (FIX-B6a). Keeps enough per-tenant history for a rollback window.
        \App\Domain\Publishing\Services\BuildRetention::prune(10);
    }

    /**
     * Copy custom font files to the staging directory.
     */
    private function copyCustomFonts(Site $site, string $stagingPath): void
    {
        $fonts = $site->settings['custom_fonts'] ?? [];
        if (empty($fonts)) return;

        $fontsDir = $stagingPath . '/fonts';
        File::ensureDirectoryExists($fontsDir);

        $disk = \Illuminate\Support\Facades\Storage::disk('assets');
        foreach ($fonts as $font) {
            $path = $font['path'] ?? '';
            $filename = $font['filename'] ?? '';
            if (!$path || !$filename || !$disk->exists($path)) continue;

            $dest = $fontsDir . '/' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', $filename);
            file_put_contents($dest, $disk->get($path));
        }
    }

    /**
     * Audit a sample of just-deployed pages for layout regressions at phone
     * and desktop widths (scripts/mobile-audit.mjs). Returns human-readable
     * warnings; empty when clean or when the audit tooling is unavailable.
     *
     * @return string[]
     */
    private function runLayoutAudit(Site $site): array
    {
        $script = base_path('scripts/mobile-audit.mjs');
        if (!file_exists($script)) {
            return [];
        }

        $base = $site->custom_domain
            ? "https://{$site->custom_domain}"
            : 'https://ensodo.eu/' . trim($site->deploySlug(), '/');

        // homepage + a recent page + a recent post — cheap but representative
        $urls = [$base . '/'];
        $page = Page::where('site_id', $site->id)->where('status', 'published')
            ->whereRaw("slug != ''")->orderByDesc('updated_at')->first();
        if ($page) {
            $urls[] = rtrim($base . \App\Domain\Publishing\Services\LocalePaths::urlPath($site, $page), '/') . '/';
        }
        $post = \App\Models\Post::where('site_id', $site->id)->where('status', 'published')
            ->orderByDesc('updated_at')->first();
        if ($post) {
            $urls[] = rtrim($base . \App\Domain\Publishing\Services\LocalePaths::urlPath($site, $post), '/') . '/';
        }

        $warnings = [];
        foreach (array_unique($urls) as $url) {
            foreach (['390x844' => '📱', '1280x900' => '🖥'] as $size => $icon) {
                $out = shell_exec('node ' . escapeshellarg($script) . ' ' . escapeshellarg($url) . ' ' . $size . ' 2>/dev/null');
                $r = json_decode((string) $out, true);
                if (!is_array($r) || !empty($r['error'])) {
                    continue;
                }
                $label = parse_url($url, PHP_URL_PATH) ?: '/';
                if (!empty($r['horizontalOverflow'])) {
                    $warnings[] = "{$icon} {$label}: horizontal overflow ({$r['scrollWidth']}px on {$r['viewport']}px)";
                }
                foreach ($r['squeezedGrids'] ?? [] as $g) {
                    $warnings[] = "{$icon} {$label}: squeezed grid {$g['el']} ({$g['columns']}×{$g['colWidth']}px)";
                }
                foreach ($r['sectionSeams'] ?? [] as $g) {
                    $warnings[] = "{$icon} {$label}: background seam {$g['gap']}px below content in {$g['el']}";
                }
                foreach ($r['narrowBanners'] ?? [] as $g) {
                    $warnings[] = "{$icon} {$label}: divider image {$g['width']}px on {$g['viewport']}px viewport";
                }
                if (($r['edgeFlushTextBlocks'] ?? 0) > 3) {
                    $warnings[] = "{$icon} {$label}: {$r['edgeFlushTextBlocks']} text blocks flush against the screen edge";
                }
            }
        }

        return $warnings;
    }
}
