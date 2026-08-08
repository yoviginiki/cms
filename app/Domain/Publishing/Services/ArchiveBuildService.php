<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Menus\Services\MenuRenderer;
use App\Domain\Theme\Services\DesignTokenGenerator;
use App\Models\Site;
use App\Models\ThemeTemplate;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Builds the blog index and category/tag/author archives into a staging
 * tree. Extracted from PublishSiteJob (§7 D1) so DELTA publishes can rebuild
 * archives too — a new/edited/removed post changes every archive that lists
 * it, and leaving them to the next full publish meant stale listings and
 * dead links in the meantime.
 */
class ArchiveBuildService
{
    /** Posts per page for category/tag/author archives (blog index has its own). */
    private const ARCHIVE_PER_PAGE = 12;

    /**
     * Rebuild the blog index + all archives for a site into $stagingPath.
     * Returns lint warnings (never blocking) — e.g. an archive template that
     * renders none of its category's posts, which otherwise fails silently.
     */
    public function buildAll(Site $site, string $stagingPath): array
    {
        $all = $site->posts()->with('category')->where('status', 'published')->orderByDesc('published_at')->get();
        if ($all->isEmpty()) {
            return [];
        }

        // Multilingual sites publish one blog index + category archives per
        // language: the default at /blog/ and /{cat}/, others at /{locale}/…
        // (same slugs, only the prefix differs). Single-language sites take
        // exactly one pass with an empty prefix — behavior unchanged.
        $warnings = [];
        $default = LocalePaths::defaultLanguage($site);
        foreach (LocalePaths::languages($site) as $locale) {
            $prefix = LocalePaths::prefix($site, $locale);
            $posts = $all->filter(fn ($p) => LocalePaths::contentLocale($p, $site) === $locale)->values();
            if ($posts->isEmpty() && $locale !== $default) {
                continue;
            }
            $this->buildBlogIndex($site, $posts, $stagingPath, $prefix, $locale);
            $warnings = array_merge($warnings, $this->buildCategoryArchives($site, $stagingPath, $prefix, $locale));
        }
        $this->buildTagArchives($site, $stagingPath);
        $this->buildAuthorArchives($site, $stagingPath);

        return $warnings;
    }


    /**
     * Write an archive file, first rewriting /api/v1 asset serve URLs to
     * hashed static paths (and copying the files) — archives render outside
     * BuildPageService::build(), so they'd otherwise ship API URLs that 404
     * on static tenant domains (e.g. post-loop featured images).
     */
    private function write(string $stagingPath, string $path, string $html, ?Site $site = null): void
    {
        $html = AssetPublisher::rewriteHtml($html);
        if ($site !== null) {
            // Slug-hosted sites serve under /{slug}/ — root-absolute links in
            // archive chrome/head (e.g. /site-files/...) need the base prefix,
            // exactly as BuildPageService applies it to pages.
            $html = SiteFilesPublisher::rewriteHtml($html, $site);
            $html = BuildPageService::rewriteBaseForSlugHosting($html, $site);
        }
        File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
        File::put("{$stagingPath}/{$path}", $html);
    }

    public function getArchiveVars(Site $site, ?string $locale = null): array
    {
        $themeConfig = $site->theme?->config ?? [];
        $menuRenderer = app(MenuRenderer::class);
        $tokenGenerator = app(DesignTokenGenerator::class);
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";

        // Exact-copy design sites publish bare (see BuildPageService): no
        // theme CSS fights the design package's @layer sheet. Instead the
        // archive loads the site's global head assets (the design stylesheet)
        // and its stored chrome (settings.chrome_header_html/footer — the
        // design's own header/footer markup), so archives read as pages of
        // the same site rather than a generic listing.
        $bareDesign = ($site->settings['design_fidelity'] ?? null) === 'exact';

        return [
            'site' => $site,
            'baseUrl' => $baseUrl,
            // F3 chain: site language beats theme default (was theme ?? 'en')
            'lang' => $site->settings['default_language'] ?? $themeConfig['lang'] ?? 'en',
            'criticalCss' => $bareDesign ? '' : ($themeConfig['critical_css'] ?? ''),
            'customCss' => $site->settings['custom_css'] ?? '',
            'designTokensCss' => $bareDesign ? '' : $tokenGenerator->generate($site),
            'bareWrapper' => $bareDesign,
            'headScripts' => $bareDesign ? ($site->settings['head_scripts'] ?? '') : '',
            'navigation' => $bareDesign
                ? ($site->settings['chrome_header_html'] ?? '')
                : $menuRenderer->renderByLocation($site, 'header', $locale),
            'footerNavigation' => $bareDesign
                ? ($site->settings['chrome_footer_html'] ?? '')
                : $menuRenderer->renderByLocation($site, 'footer', $locale),
            'rssUrl' => "{$baseUrl}/feed.xml",
        ];
    }

    public function buildBlogIndex(Site $site, $posts, string $stagingPath, string $prefix = '', ?string $locale = null): void
    {
        $vars = $this->getArchiveVars($site, $locale);
        $perPage = 10;
        $totalPages = max(1, (int) ceil($posts->count() / $perPage));

        for ($page = 1; $page <= $totalPages; $page++) {
            $pagePosts = $posts->forPage($page, $perPage);
            $html = View::make('publishing.blog-index', array_merge($vars, [
                'posts' => $pagePosts,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'archiveJsonLd' => app(StructuredDataService::class)->generateArchiveGraph($site, 'Blog', $page === 1 ? "/{$prefix}blog/" : "/{$prefix}blog/page/{$page}/", $pagePosts),
            ]))->render();

            $path = $page === 1 ? "{$prefix}blog/index.html" : "{$prefix}blog/page/{$page}/index.html";
            $this->write($stagingPath, $path, $html, $site);
        }
    }

    /** @return string[] lint warnings (archive templates rendering zero posts) */
    public function buildCategoryArchives(Site $site, string $stagingPath, string $prefix = '', ?string $locale = null): array
    {
        $warnings = [];
        $locale ??= LocalePaths::defaultLanguage($site);
        $vars = $this->getArchiveVars($site, $locale);
        $categories = $site->categories()->withCount('posts')->get();
        $buildService = app(BuildPageService::class);
        $isDefaultLocale = $prefix === '';
        $catBase = LocalePaths::categoryBase($site);
        $localizeName = fn ($cat) => ($cat->settings['name_translations'][$locale] ?? null) ?: $cat->name;

        foreach ($categories as $category) {
            // A real page owns its slug: with a rootless category base the
            // archive writes to /{slug}/ AFTER pages build, so a same-slug
            // category (common after a WP import, e.g. a "Статии" category next
            // to the Статии page) would silently overwrite the page. The page
            // wins; skip the archive. With a category base (e.g.
            // /category/{slug}/) there is no collision, so the skip is moot.
            $pageOwnsSlug = $catBase === '' && \App\Models\Page::where('site_id', $site->id)
                ->where('slug', $category->slug)
                ->where('status', 'published')
                ->exists();
            if ($pageOwnsSlug) {
                $warnings[] = "Category '{$category->slug}': archive skipped — a published page owns this slug.";
                continue;
            }

            $posts = $category->posts()->with(['category', 'author'])->where('status', 'published')->orderByDesc('published_at')->get()
                ->filter(fn ($p) => LocalePaths::contentLocale($p, $site) === $locale)->values();
            if ($posts->isEmpty() && !$isDefaultLocale) {
                continue; // no /{locale}/{cat}/ page for languages this category has no posts in
            }
            $displayName = $localizeName($category);

            // Check for archive template
            $archiveTemplate = ThemeTemplate::resolveForArchive($site->id, $category->id);

            if ($archiveTemplate) {
                $html = $this->renderArchiveWithTemplate($archiveTemplate, $category, $posts, $site, $vars, $buildService,
                    app(StructuredDataService::class)->generateArchiveGraph($site, $displayName, "/{$prefix}{$catBase}{$category->slug}/", $posts),
                    $prefix, $locale, $displayName);

                // An empty/misconfigured archive template fails completely
                // silently — the archive publishes with no post listing at all.
                // Check the BODY only: the head's CollectionPage JSON-LD always
                // lists the posts and would mask an empty template.
                $body = substr($html, strpos($html, '</head>') ?: 0);
                if ($posts->isNotEmpty() && !str_contains($body, rtrim(LocalePaths::urlPath($site, $posts->first()), '/'))) {
                    $warnings[] = "Category '{$category->slug}': archive template '{$archiveTemplate->name}' renders none of the category's {$posts->count()} published posts (empty template or missing post-loop block?)";
                }
                $path = "{$prefix}{$catBase}{$category->slug}/index.html";
                $this->write($stagingPath, $path, $html, $site);
            } else {
                // Collect child categories with their posts (only shown page 1)
                $children = $categories->where('parent_id', $category->id);
                $childData = [];
                foreach ($children as $child) {
                    $childPosts = $child->posts()->with('category')->where('status', 'published')->orderByDesc('published_at')->get()
                        ->filter(fn ($p) => LocalePaths::contentLocale($p, $site) === $locale)->values();
                    if ($childPosts->isNotEmpty()) {
                        $childData[] = ['category' => $child, 'posts' => $childPosts, 'displayName' => $localizeName($child)];
                    }
                }

                // Paginate so a large category (thousands of posts) never renders
                // one enormous archive page. page 1 at /{base}{slug}/, the rest at
                // /{base}{slug}/page/{n}/.
                $paginationBase = "/{$prefix}{$catBase}{$category->slug}";
                $totalPages = max(1, (int) ceil($posts->count() / self::ARCHIVE_PER_PAGE));
                for ($pg = 1; $pg <= $totalPages; $pg++) {
                    $pagePosts = $posts->forPage($pg, self::ARCHIVE_PER_PAGE);
                    $canonical = $pg === 1 ? "{$paginationBase}/" : "{$paginationBase}/page/{$pg}/";
                    $html = View::make('publishing.category-archive', array_merge($vars, [
                        'category' => $category,
                        'displayName' => $displayName,
                        'urlPrefix' => $prefix,
                        'posts' => $pagePosts,
                        'childCategories' => $pg === 1 ? $childData : [],
                        'currentPage' => $pg,
                        'totalPages' => $totalPages,
                        'paginationBase' => $paginationBase,
                        'archiveJsonLd' => app(StructuredDataService::class)->generateArchiveGraph($site, $displayName, $canonical, $pagePosts),
                    ]))->render();
                    $path = $pg === 1
                        ? "{$prefix}{$catBase}{$category->slug}/index.html"
                        : "{$prefix}{$catBase}{$category->slug}/page/{$pg}/index.html";
                    $this->write($stagingPath, $path, $html, $site);
                }
            }
        }

        return $warnings;
    }

    /**
     * Render a single category archive page to HTML (no file write, no asset
     * rewrite) — used by the dynamic preview so category URLs resolve the same
     * way they do in a static build. Mirrors the non-template branch of
     * buildCategoryArchives() for one category + page.
     */
    public function renderCategoryArchiveHtml(Site $site, $category, int $page = 1, string $prefix = '', ?string $locale = null): string
    {
        $locale ??= LocalePaths::defaultLanguage($site);
        $vars = $this->getArchiveVars($site, $locale);
        $catBase = LocalePaths::categoryBase($site);
        $localizeName = fn ($cat) => ($cat->settings['name_translations'][$locale] ?? null) ?: $cat->name;
        $categories = $site->categories()->get();

        $posts = $category->posts()->with(['category', 'author'])->where('status', 'published')->orderByDesc('published_at')->get()
            ->filter(fn ($p) => LocalePaths::contentLocale($p, $site) === $locale)->values();
        $displayName = $localizeName($category);

        $childData = [];
        foreach ($categories->where('parent_id', $category->id) as $child) {
            $childPosts = $child->posts()->with('category')->where('status', 'published')->orderByDesc('published_at')->get()
                ->filter(fn ($p) => LocalePaths::contentLocale($p, $site) === $locale)->values();
            if ($childPosts->isNotEmpty()) {
                $childData[] = ['category' => $child, 'posts' => $childPosts, 'displayName' => $localizeName($child)];
            }
        }

        $paginationBase = "/{$prefix}{$catBase}{$category->slug}";
        $totalPages = max(1, (int) ceil($posts->count() / self::ARCHIVE_PER_PAGE));
        $page = max(1, min($page, $totalPages));
        $pagePosts = $posts->forPage($page, self::ARCHIVE_PER_PAGE);
        $canonical = $page === 1 ? "{$paginationBase}/" : "{$paginationBase}/page/{$page}/";

        return View::make('publishing.category-archive', array_merge($vars, [
            'category' => $category,
            'displayName' => $displayName,
            'urlPrefix' => $prefix,
            'posts' => $pagePosts,
            'childCategories' => $page === 1 ? $childData : [],
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'paginationBase' => $paginationBase,
            'archiveJsonLd' => app(StructuredDataService::class)->generateArchiveGraph($site, $displayName, $canonical, $pagePosts),
        ]))->render();
    }

    private function renderArchiveWithTemplate(
        ThemeTemplate $template,
        $category,
        $posts,
        Site $site,
        array $vars,
        BuildPageService $buildService,
        string $extraHead = '',
        string $prefix = '',
        ?string $locale = null,
        ?string $displayName = null,
    ): string {
        $displayName ??= $category->name;
        // Set archive context for dynamic blocks
        $archiveContext = [
            '__category' => $category,
            '__locale' => $locale ?? LocalePaths::defaultLanguage($site),
            '__archivePosts' => $posts,
            '__archivePostCount' => $posts->count(),
            '__archiveCurrentPage' => 1,
            '__archiveTotalPages' => 1,
            '__archiveBaseUrl' => '/' . $prefix . LocalePaths::categoryBase($site) . $category->slug,
        ];

        // Render template blocks with archive context (safe try/finally inside)
        $templateBlocks = $template->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        $renderedBlocks = $buildService->renderBlocksWithContext($templateBlocks, $site, $archiveContext);

        $themeConfig = $site->theme?->config ?? [];
        $description = trim((string) ($category->description ?: "Posts in {$displayName} — {$site->name}"));
        $headContent = '<title>' . e($displayName) . ' | ' . e($site->name) . '</title>'
            . '<meta name="description" content="' . e(mb_substr($description, 0, 160)) . '">'
            . app(FaviconGenerator::class)->headLink()
            . $extraHead;

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
            'content' => (object) ['title' => $displayName, 'seo_meta' => []],
            'themeConfig' => $themeConfig,
        ]))->render();
    }

    public function buildTagArchives(Site $site, string $stagingPath): void
    {
        $vars = $this->getArchiveVars($site);
        $tags = $site->tags()->get();

        foreach ($tags as $tag) {
            $base = $tag->posts()->where('status', 'published');
            $total = (clone $base)->count();
            if ($total === 0) {
                continue;
            }
            $paginationBase = "/tag/{$tag->slug}";
            $totalPages = max(1, (int) ceil($total / self::ARCHIVE_PER_PAGE));

            for ($pg = 1; $pg <= $totalPages; $pg++) {
                $pagePosts = (clone $base)->orderByDesc('published_at')->forPage($pg, self::ARCHIVE_PER_PAGE)->get();
                $canonical = $pg === 1 ? "{$paginationBase}/" : "{$paginationBase}/page/{$pg}/";
                $html = View::make('publishing.tag-archive', array_merge($vars, [
                    'tag' => $tag,
                    'posts' => $pagePosts,
                    'currentPage' => $pg,
                    'totalPages' => $totalPages,
                    'paginationBase' => $paginationBase,
                    'archiveJsonLd' => app(StructuredDataService::class)->generateArchiveGraph($site, $tag->name, $canonical, $pagePosts),
                ]))->render();
                $path = $pg === 1 ? "tag/{$tag->slug}/index.html" : "tag/{$tag->slug}/page/{$pg}/index.html";
                $this->write($stagingPath, $path, $html, $site);
            }
        }
    }

    public function buildAuthorArchives(Site $site, string $stagingPath): void
    {
        $vars = $this->getArchiveVars($site);

        $authorIds = $site->posts()->where('status', 'published')->whereNotNull('author_id')
            ->distinct()->pluck('author_id');

        foreach ($authorIds as $authorId) {
            $author = User::find($authorId);
            if (!$author) {
                continue;
            }

            $slug = Str::slug($author->name);
            $paginationBase = "/author/{$slug}";
            // Per-page queries (not one big ->get()) so a prolific author — e.g.
            // an imported site where every post shares one owner — never loads
            // thousands of posts into memory at once.
            $base = $site->posts()->where('status', 'published')->where('author_id', $authorId);
            $total = (clone $base)->count();
            $totalPages = max(1, (int) ceil($total / self::ARCHIVE_PER_PAGE));

            for ($pg = 1; $pg <= $totalPages; $pg++) {
                $pagePosts = (clone $base)->orderByDesc('published_at')->forPage($pg, self::ARCHIVE_PER_PAGE)->get();
                $canonical = $pg === 1 ? "{$paginationBase}/" : "{$paginationBase}/page/{$pg}/";
                $html = View::make('publishing.author-archive', array_merge($vars, [
                    'author' => $author,
                    'posts' => $pagePosts,
                    'postTotal' => $total,
                    'currentPage' => $pg,
                    'totalPages' => $totalPages,
                    'paginationBase' => $paginationBase,
                    'archiveJsonLd' => app(StructuredDataService::class)->generateArchiveGraph($site, $author->name, $canonical, $pagePosts),
                ]))->render();
                $path = $pg === 1 ? "author/{$slug}/index.html" : "author/{$slug}/page/{$pg}/index.html";
                $this->write($stagingPath, $path, $html, $site);
            }
        }
    }
}
