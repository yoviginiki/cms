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
    /** Rebuild the blog index + all archives for a site into $stagingPath. */
    public function buildAll(Site $site, string $stagingPath): void
    {
        $posts = $site->posts()->with('category')->where('status', 'published')->orderByDesc('published_at')->get();
        if ($posts->isEmpty()) {
            return;
        }

        $this->buildBlogIndex($site, $posts, $stagingPath);
        $this->buildCategoryArchives($site, $stagingPath);
        $this->buildTagArchives($site, $stagingPath);
        $this->buildAuthorArchives($site, $stagingPath);
    }

    public function getArchiveVars(Site $site): array
    {
        $themeConfig = $site->theme?->config ?? [];
        $menuRenderer = app(MenuRenderer::class);
        $tokenGenerator = app(DesignTokenGenerator::class);
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";

        return [
            'site' => $site,
            'baseUrl' => $baseUrl,
            // F3 chain: site language beats theme default (was theme ?? 'en')
            'lang' => $site->settings['default_language'] ?? $themeConfig['lang'] ?? 'en',
            'criticalCss' => $themeConfig['critical_css'] ?? '',
            'customCss' => $site->settings['custom_css'] ?? '',
            'designTokensCss' => $tokenGenerator->generate($site),
            'navigation' => $menuRenderer->renderByLocation($site, 'header'),
            'footerNavigation' => $menuRenderer->renderByLocation($site, 'footer'),
            'rssUrl' => "{$baseUrl}/feed.xml",
        ];
    }

    public function buildBlogIndex(Site $site, $posts, string $stagingPath): void
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

    public function buildCategoryArchives(Site $site, string $stagingPath): void
    {
        $vars = $this->getArchiveVars($site);
        $categories = $site->categories()->withCount('posts')->get();
        $buildService = app(BuildPageService::class);

        foreach ($categories as $category) {
            $posts = $category->posts()->with(['category', 'author'])->where('status', 'published')->orderByDesc('published_at')->get();

            // Check for archive template
            $archiveTemplate = ThemeTemplate::resolveForArchive($site->id, $category->id);

            if ($archiveTemplate) {
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

            $path = "{$category->slug}/index.html";
            File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
            File::put("{$stagingPath}/{$path}", $html);
        }
    }

    private function renderArchiveWithTemplate(
        ThemeTemplate $template,
        $category,
        $posts,
        Site $site,
        array $vars,
        BuildPageService $buildService,
    ): string {
        // Set archive context for dynamic blocks
        $archiveContext = [
            '__category' => $category,
            '__archivePosts' => $posts,
            '__archivePostCount' => $posts->count(),
            '__archiveCurrentPage' => 1,
            '__archiveTotalPages' => 1,
            '__archiveBaseUrl' => "/{$category->slug}",
        ];

        // Render template blocks with archive context (safe try/finally inside)
        $templateBlocks = $template->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        $renderedBlocks = $buildService->renderBlocksWithContext($templateBlocks, $site, $archiveContext);

        $themeConfig = $site->theme?->config ?? [];
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

    public function buildTagArchives(Site $site, string $stagingPath): void
    {
        $vars = $this->getArchiveVars($site);
        $tags = $site->tags()->get();

        foreach ($tags as $tag) {
            $posts = $tag->posts()->where('status', 'published')->orderByDesc('published_at')->get();
            if ($posts->isEmpty()) {
                continue;
            }

            $html = View::make('publishing.tag-archive', array_merge($vars, [
                'tag' => $tag,
                'posts' => $posts,
            ]))->render();

            $path = "tag/{$tag->slug}/index.html";
            File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
            File::put("{$stagingPath}/{$path}", $html);
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

            $posts = $site->posts()->where('status', 'published')->where('author_id', $authorId)
                ->orderByDesc('published_at')->get();

            $html = View::make('publishing.author-archive', array_merge($vars, [
                'author' => $author,
                'posts' => $posts,
            ]))->render();

            $slug = Str::slug($author->name);
            $path = "author/{$slug}/index.html";
            File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
            File::put("{$stagingPath}/{$path}", $html);
        }
    }
}
