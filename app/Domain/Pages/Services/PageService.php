<?php

namespace App\Domain\Pages\Services;

use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Str;

class PageService
{
    public function createPage(array $data, Site $site): Page
    {
        $data['site_id'] = $site->id;
        $data['slug'] = $data['slug'] ?? $this->generateUniqueSlug($data['title'], $site);

        return Page::create($data);
    }

    public function updatePage(Page $page, array $data): Page
    {
        if (isset($data['slug']) && $data['slug'] !== $page->slug) {
            $data['slug'] = $this->generateUniqueSlug($data['slug'], $page->site, $page->id);
        }

        $page->update($data);

        return $page->fresh();
    }

    public function reorderPages(Site $site, array $items): void
    {
        foreach ($items as $item) {
            Page::where('id', $item['id'])
                ->where('site_id', $site->id)
                ->update([
                    'parent_id' => $item['parent_id'] ?? null,
                    'sort_order' => $item['sort_order'],
                ]);
        }
    }

    public function getPageTree(Site $site): array
    {
        $pages = $site->pages()
            ->orderBy('sort_order')
            ->get();

        return $this->buildTree($pages);
    }

    private function buildTree($pages, ?string $parentId = null): array
    {
        $tree = [];

        foreach ($pages->where('parent_id', $parentId) as $page) {
            $node = $page->toArray();
            $node['children'] = $this->buildTree($pages, $page->id);
            $tree[] = $node;
        }

        return $tree;
    }

    private function generateUniqueSlug(string $text, Site $site, ?string $excludeId = null): string
    {
        $slug = Str::slug($text);
        $original = $slug;
        $count = 1;

        $query = Page::where('site_id', $site->id)->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $original . '-' . $count++;
            $query = Page::where('site_id', $site->id)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
