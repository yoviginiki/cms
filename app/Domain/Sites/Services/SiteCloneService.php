<?php

namespace App\Domain\Sites\Services;

use App\Domain\Assets\Services\AssetService;
use App\Domain\Blocks\Services\BlockService;
use App\Domain\Categories\Services\CategoryService;
use App\Domain\Import\Data\ImportResult;
use App\Domain\Pages\Services\PageService;
use App\Domain\Posts\Services\PostService;
use App\Models\Asset;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SiteCloneService
{
    public function __construct(
        private SiteService $siteService,
        private CategoryService $categoryService,
        private PageService $pageService,
        private PostService $postService,
        private BlockService $blockService,
        private AssetService $assetService,
    ) {
    }

    /**
     * Export site as a portable JSON-serializable array.
     */
    public function export(Site $site): array
    {
        $site->load(['categories', 'pages.blocks', 'posts.blocks', 'assets', 'theme']);

        $data = [
            'meta' => [
                'name' => $site->name,
                'seo_defaults' => $site->seo_defaults,
                'settings' => $site->settings,
                'theme_config' => $site->theme?->config ?? [],
            ],
            'categories' => $site->categories->map(fn($c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'parent_slug' => $c->parent?->slug,
                'description' => $c->description,
                'sort_order' => $c->sort_order,
            ])->toArray(),
            'pages' => $site->pages->map(fn($p) => [
                'title' => $p->title,
                'slug' => $p->slug,
                'parent_slug' => $p->parent?->slug,
                'sort_order' => $p->sort_order,
                'status' => $p->status,
                'seo_meta' => $p->seo_meta,
                'blocks' => $this->blockService->getBlockTree($p),
            ])->toArray(),
            'posts' => $site->posts->map(fn($p) => [
                'title' => $p->title,
                'slug' => $p->slug,
                'category_slug' => $p->category?->slug,
                'excerpt' => $p->excerpt,
                'status' => $p->status,
                'seo_meta' => $p->seo_meta,
                'published_at' => $p->published_at?->toISOString(),
                'blocks' => $this->blockService->getBlockTree($p),
            ])->toArray(),
            'assets' => $site->assets->map(fn($a) => [
                'original_name' => $a->original_name,
                'storage_path' => $a->storage_path,
                'mime_type' => $a->mime_type,
                'alt_text' => $a->alt_text,
                'checksum' => $a->checksum,
            ])->toArray(),
        ];

        return $data;
    }

    /**
     * Clone a site within the same tenant.
     */
    public function clone(Site $source, string $newName, User $owner): Site
    {
        return DB::transaction(function () use ($source, $newName, $owner) {
            // Create new site
            $newSite = $this->siteService->createSite([
                'name' => $newName,
                'seo_defaults' => $source->seo_defaults,
                'settings' => $source->settings,
            ], $owner);

            // Export and import
            $exported = $this->export($source);
            $this->importFromArray($exported, $newSite);

            return $newSite->fresh();
        });
    }

    /**
     * Import from a template/export array into an existing site.
     */
    public function importFromTemplate(string $templateJson, Site $targetSite): ImportResult
    {
        $data = json_decode($templateJson, true);
        if (!$data) {
            throw new \RuntimeException('Invalid template JSON');
        }

        return $this->importFromArray($data, $targetSite);
    }

    /**
     * Import from array data into a site.
     */
    private function importFromArray(array $data, Site $targetSite): ImportResult
    {
        $result = new ImportResult();

        // Import categories
        $categoryMap = []; // slug => id
        foreach ($data['categories'] ?? [] as $cat) {
            $category = $this->categoryService->createCategory([
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'description' => $cat['description'] ?? '',
                'sort_order' => $cat['sort_order'] ?? 0,
            ], $targetSite);
            $categoryMap[$cat['slug']] = $category->id;
            $result->categories++;
        }

        // Set parent relationships
        foreach ($data['categories'] ?? [] as $cat) {
            if (!empty($cat['parent_slug']) && isset($categoryMap[$cat['slug']]) && isset($categoryMap[$cat['parent_slug']])) {
                \App\Models\Category::where('id', $categoryMap[$cat['slug']])->update([
                    'parent_id' => $categoryMap[$cat['parent_slug']],
                ]);
            }
        }

        // Import pages
        $pageMap = []; // slug => id
        // Sort by parent to ensure parents first
        $pages = collect($data['pages'] ?? [])->sortBy(fn($p) => empty($p['parent_slug']) ? 0 : 1);
        foreach ($pages as $pageData) {
            $parentId = null;
            if (!empty($pageData['parent_slug']) && isset($pageMap[$pageData['parent_slug']])) {
                $parentId = $pageMap[$pageData['parent_slug']];
            }

            $page = $this->pageService->createPage([
                'title' => $pageData['title'],
                'slug' => $pageData['slug'],
                'parent_id' => $parentId,
                'sort_order' => $pageData['sort_order'] ?? 0,
                'status' => $pageData['status'] ?? 'draft',
                'seo_meta' => $pageData['seo_meta'] ?? [],
            ], $targetSite);

            $pageMap[$pageData['slug']] = $page->id;

            if (!empty($pageData['blocks'])) {
                $this->blockService->syncBlocks($page, $this->stripBlockIds($pageData['blocks']));
            }

            $result->pages++;
        }

        // Import posts
        foreach ($data['posts'] ?? [] as $postData) {
            $categoryId = null;
            if (!empty($postData['category_slug']) && isset($categoryMap[$postData['category_slug']])) {
                $categoryId = $categoryMap[$postData['category_slug']];
            }

            $post = $this->postService->createPost([
                'title' => $postData['title'],
                'slug' => $postData['slug'],
                'excerpt' => $postData['excerpt'] ?? null,
                'category_id' => $categoryId,
                'status' => $postData['status'] ?? 'draft',
                'seo_meta' => $postData['seo_meta'] ?? [],
                'published_at' => $postData['published_at'] ?? null,
            ], $targetSite);

            if (!empty($postData['blocks'])) {
                $this->blockService->syncBlocks($post, $this->stripBlockIds($postData['blocks']));
            }

            $result->posts++;
        }

        return $result;
    }

    /**
     * Strip existing block IDs so BlockService generates new ones.
     */
    private function stripBlockIds(array $blocks): array
    {
        return array_map(function ($block) {
            unset($block['id']);
            if (!empty($block['children'])) {
                $block['children'] = $this->stripBlockIds($block['children']);
            }
            return $block;
        }, $blocks);
    }
}
