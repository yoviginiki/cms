<?php

namespace App\Domain\Categories\Services;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Support\Str;

class CategoryService
{
    public function createCategory(array $data, Site $site): Category
    {
        $data['site_id'] = $site->id;
        $data['slug'] = $data['slug'] ?? $this->generateUniqueSlug($data['name'], $site);

        $category = new Category();
        $category->site_id = $site->id;
        $this->applyFeaturedImage($category, $data);
        $category->fill($data)->save();

        return $category;
    }

    public function updateCategory(Category $category, array $data): Category
    {
        if (isset($data['parent_id']) && $this->wouldCreateCircular($category, $data['parent_id'])) {
            throw new \InvalidArgumentException('Circular parent reference detected.');
        }

        if (isset($data['slug']) && $data['slug'] !== $category->slug) {
            $data['slug'] = $this->generateUniqueSlug($data['slug'], $category->site, $category->id);
        }

        $this->applyFeaturedImage($category, $data);
        $category->fill($data)->save();

        return $category->fresh();
    }

    /**
     * Fold the flat featured_image_asset_id API field into the category's
     * settings JSON (serve URL + asset id) so it needs no dedicated column.
     */
    private function applyFeaturedImage(Category $category, array &$data): void
    {
        if (array_key_exists('featured_image_asset_id', $data)) {
            $category->setFeaturedImageAsset($data['featured_image_asset_id']);
            unset($data['featured_image_asset_id']);
            // keep the merged settings; don't let a stale settings key overwrite it
            unset($data['settings']);
        }
    }

    public function getCategoryTree(Site $site): array
    {
        $categories = $site->categories()
            ->withCount('posts')
            ->orderBy('sort_order')
            ->get();

        return $this->buildTree($categories);
    }

    public function reorderCategories(Site $site, array $items): void
    {
        foreach ($items as $item) {
            Category::where('id', $item['id'])
                ->where('site_id', $site->id)
                ->update([
                    'parent_id' => $item['parent_id'] ?? null,
                    'sort_order' => $item['sort_order'],
                ]);
        }
    }

    private function wouldCreateCircular(Category $category, ?string $newParentId): bool
    {
        if (!$newParentId || $newParentId === $category->id) {
            return $newParentId === $category->id;
        }

        $current = Category::find($newParentId);
        while ($current) {
            if ($current->id === $category->id) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    private function buildTree($categories, ?string $parentId = null): array
    {
        $tree = [];

        foreach ($categories->where('parent_id', $parentId) as $cat) {
            $node = $cat->toArray();
            $node['children'] = $this->buildTree($categories, $cat->id);
            $tree[] = $node;
        }

        return $tree;
    }

    private function generateUniqueSlug(string $text, Site $site, ?string $excludeId = null): string
    {
        $slug = \App\Support\Slugify::slug($text);
        $original = $slug;
        $count = 1;

        $query = Category::where('site_id', $site->id)->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $original . '-' . $count++;
            $query = Category::where('site_id', $site->id)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
