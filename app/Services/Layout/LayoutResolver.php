<?php

namespace App\Services\Layout;

use App\Models\Layout;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LayoutResolver
{
    public function resolveForPost(Post $post): Layout
    {
        return $this->resolveForPostFresh($post);
    }

    public function resolveForPage(Page $page): Layout
    {
        return $this->resolveForPageFresh($page);
        return Layout::find($layoutId) ?? $this->systemStandard();
    }

    private function resolveForPostFresh(Post $post): Layout
    {
        // 1. Explicit on post
        if ($post->layout_id) {
            $layout = Layout::find($post->layout_id);
            if ($layout) return $layout;
        }

        // 2. Category default (nearest ancestor)
        if ($post->category_id) {
            $catLayout = $this->nearestCategoryLayout($post->category_id);
            if ($catLayout) return $catLayout;
        }

        // 3. Site/tenant default for posts
        $site = $post->site ?? Site::find($post->site_id);
        if ($site) {
            $settings = $site->settings ?? [];
            $defaultId = $settings['default_post_layout_id'] ?? null;
            if ($defaultId) {
                $layout = Layout::find($defaultId);
                if ($layout) return $layout;
            }
        }

        // 4. System standard fallback
        return $this->systemStandard();
    }

    private function resolveForPageFresh(Page $page): Layout
    {
        // 1. Explicit on page
        if ($page->layout_id) {
            $layout = Layout::find($page->layout_id);
            if ($layout) return $layout;
        }

        // 2. Site/tenant default for pages
        $site = $page->site ?? Site::find($page->site_id);
        if ($site) {
            $settings = $site->settings ?? [];
            $defaultId = $settings['default_page_layout_id'] ?? null;
            if ($defaultId) {
                $layout = Layout::find($defaultId);
                if ($layout) return $layout;
            }
        }

        // 3. System standard fallback
        return $this->systemStandard();
    }

    private function nearestCategoryLayout(string $categoryId): ?Layout
    {
        $cat = \App\Models\Category::find($categoryId);
        while ($cat) {
            if ($cat->default_layout_id) {
                return Layout::find($cat->default_layout_id);
            }
            $cat = $cat->parent_id ? \App\Models\Category::find($cat->parent_id) : null;
        }
        return null;
    }

    private function systemStandard(): Layout
    {
        // Don't cache the model — cache the ID and re-fetch
        $id = Cache::remember('layout:system:standard:id', 86400, function () {
            $row = DB::table('layouts')
                ->where('slug', 'standard')
                ->whereNull('tenant_id')
                ->where('is_system', true)
                ->first();
            return $row?->id;
        });

        if ($id) {
            $layout = Layout::find($id);
            if ($layout) return $layout;
        }

        // Absolute fallback
        $row = DB::table('layouts')
            ->where('slug', 'standard')
            ->whereNull('tenant_id')
            ->where('is_system', true)
            ->first();

        if (!$row) {
            $layout = new Layout();
            $layout->forceFill([
                'slug' => 'standard',
                'name' => 'Standard',
                'wrapper_blade_view' => 'layouts.wrappers.standard',
                'supports' => ['header' => true, 'footer' => true, 'maxWidthContent' => true, 'maxWidthValue' => '48rem'],
            ]);
            return $layout;
        }

        $layout = new Layout();
            $layout->forceFill((array) $row);
            $layout->exists = true;
            return $layout;
        });
    }
}
