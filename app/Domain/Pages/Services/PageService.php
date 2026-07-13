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
        $data['slug'] = $this->generateUniqueSlug(
            $data['slug'] ?? $data['title'], $site
        );

        return Page::create($data);
    }

    public function updatePage(Page $page, array $data): Page
    {
        if (isset($data['slug']) && $data['slug'] !== $page->slug) {
            $data['slug'] = $this->generateUniqueSlug($data['slug'], $page->site, $page->id);

            // Remember the OLD published path so a delta promote can remove
            // the stale file (§7 — full publishes prune it; deltas can't
            // know it without this). Server-managed key, stripped from input.
            if ($page->status === 'published') {
                $seoMeta = $data['seo_meta'] ?? [];
                $oldPath = \App\Domain\Publishing\Services\LocalePaths::pagePath($page->site, $page);
                $prev = ($page->seo_meta['_previous_paths'] ?? []);
                $seoMeta['_previous_paths'] = array_slice(array_values(array_unique(array_merge($prev, [$oldPath]))), -5);
                $data['seo_meta'] = $seoMeta;
            }
        }

        // Merge seo_meta instead of replacing — prevents losing fields
        if (isset($data['seo_meta']) && is_array($data['seo_meta'])) {
            $data['seo_meta'] = array_merge($page->seo_meta ?? [], $data['seo_meta']);
        }

        // Real content edits stamp content_modified_at (F4 — accurate dateModified)
        if (array_intersect(['title', 'raw_html'], array_keys($data)) !== []) {
            $data['content_modified_at'] = now();
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
        $slug = \App\Support\Slugify::slug($text);
        $original = $slug;
        $count = 1;

        $query = Page::withTrashed()->where('site_id', $site->id)->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $original . '-' . $count++;
            $query = Page::withTrashed()->where('site_id', $site->id)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}
