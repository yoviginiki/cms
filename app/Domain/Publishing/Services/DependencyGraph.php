<?php

namespace App\Domain\Publishing\Services;

use App\Models\Grid;
use App\Models\GridPosition;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

class DependencyGraph
{
    /**
     * Determine what needs rebuilding when a specific change occurs.
     * Returns an array of rebuild targets.
     */
    public function getAffectedTargets(Site $site, string $changeType, ?string $changeId = null): array
    {
        return match ($changeType) {
            // Global changes — rebuild everything
            'menu', 'theme', 'settings', 'grid' => $this->allTargets($site),

            // Post changes — rebuild post + archives that include it
            'post_created', 'post_updated', 'post_deleted' => $this->postTargets($site, $changeId),

            // Page blocks changed — rebuild just that page
            'page_blocks' => $this->pageBlockTargets($site, $changeId),

            // Page metadata changed (title, slug, status) — rebuild page + maybe nav
            'page_updated' => $this->pageUpdateTargets($site, $changeId),

            // Category changed — rebuild category archive + posts in it
            'category' => $this->categoryTargets($site, $changeId),

            // Tag changed — rebuild tag archive
            'tag' => $this->tagTargets($site, $changeId),

            default => $this->allTargets($site),
        };
    }

    /**
     * Build ALL targets (full rebuild).
     */
    private function allTargets(Site $site): array
    {
        return [
            'type' => 'full',
            'pages' => $site->pages()->where('status', 'published')->pluck('id')->toArray(),
            'posts' => $site->posts()->where('status', 'published')->pluck('id')->toArray(),
            'archives' => ['blog_index', 'categories', 'tags', 'authors'],
            'feeds' => ['rss', 'sitemap'],
            'reason' => 'Global change affects all pages',
        ];
    }

    /**
     * What to rebuild when a post changes.
     */
    private function postTargets(Site $site, ?string $postId): array
    {
        $targets = [
            'type' => 'partial',
            'pages' => [],
            'posts' => [],
            'archives' => ['blog_index'],
            'feeds' => ['rss', 'sitemap'],
            'reason' => 'Post change',
        ];

        if ($postId) {
            $post = Post::find($postId);
            if ($post) {
                $targets['posts'][] = $postId;

                // Category archive needs rebuild
                if ($post->category_id) {
                    $targets['archives'][] = "category:{$post->category->slug}";
                }

                // Tag archives need rebuild
                if (method_exists($post, 'tags')) {
                    foreach ($post->tags as $tag) {
                        $targets['archives'][] = "tag:{$tag->slug}";
                    }
                }

                // Author archive
                if ($post->author_id) {
                    $targets['archives'][] = "author:{$post->author_id}";
                }

                // Find pages with query positions that might show this post
                $queryPages = $this->findPagesWithQueryPositions($site);
                $targets['pages'] = array_merge($targets['pages'], $queryPages);
            }
        }

        return $targets;
    }

    /**
     * When page blocks change — only rebuild that page.
     */
    private function pageBlockTargets(Site $site, ?string $pageId): array
    {
        return [
            'type' => 'partial',
            'pages' => $pageId ? [$pageId] : [],
            'posts' => [],
            'archives' => [],
            'feeds' => [],
            'reason' => 'Page blocks changed',
        ];
    }

    /**
     * When page metadata changes (title, slug, status).
     */
    private function pageUpdateTargets(Site $site, ?string $pageId): array
    {
        $targets = [
            'type' => 'partial',
            'pages' => $pageId ? [$pageId] : [],
            'posts' => [],
            'archives' => [],
            'feeds' => ['sitemap'],
            'reason' => 'Page updated',
        ];

        // If page is in the menu, ALL pages need rebuild (menu changed)
        if ($pageId && $this->isPageInMenu($site, $pageId)) {
            return $this->allTargets($site);
        }

        return $targets;
    }

    /**
     * When a category changes — rebuild its archive + posts in it.
     */
    private function categoryTargets(Site $site, ?string $categoryId): array
    {
        $targets = [
            'type' => 'partial',
            'pages' => [],
            'posts' => [],
            'archives' => [],
            'feeds' => ['sitemap'],
            'reason' => 'Category changed',
        ];

        if ($categoryId) {
            $category = \App\Models\Category::find($categoryId);
            if ($category) {
                $targets['archives'][] = "category:{$category->slug}";
                // Posts in this category need their breadcrumb/category label updated
                $targets['posts'] = $category->posts()
                    ->where('status', 'published')
                    ->pluck('id')
                    ->toArray();
            }
        }

        return $targets;
    }

    /**
     * When a tag changes — rebuild its archive.
     */
    private function tagTargets(Site $site, ?string $tagId): array
    {
        $targets = [
            'type' => 'partial',
            'pages' => [],
            'posts' => [],
            'archives' => [],
            'feeds' => [],
            'reason' => 'Tag changed',
        ];

        if ($tagId) {
            $tag = \App\Models\Tag::find($tagId);
            if ($tag) {
                $targets['archives'][] = "tag:{$tag->slug}";
            }
        }

        return $targets;
    }

    /**
     * Find pages that have query-type grid positions (they show dynamic post lists).
     */
    private function findPagesWithQueryPositions(Site $site): array
    {
        // Get grids that have query positions
        $gridIds = GridPosition::where('type', 'query')
            ->whereHas('grid', fn($q) => $q->where('site_id', $site->id))
            ->pluck('grid_id')
            ->unique();

        if ($gridIds->isEmpty()) {
            // Check if the homepage has query content (common pattern)
            $homepageId = $site->settings['homepage_id'] ?? null;
            return $homepageId ? [$homepageId] : [];
        }

        // Find pages assigned to these grids
        $pageIds = [];

        // Direct grid assignments
        $pageIds = array_merge($pageIds,
            Page::where('site_id', $site->id)
                ->whereIn('grid_id', $gridIds)
                ->where('status', 'published')
                ->pluck('id')
                ->toArray()
        );

        // Grid assignment rules
        $assignments = \App\Models\GridAssignment::where('site_id', $site->id)
            ->whereIn('grid_id', $gridIds)
            ->where('is_active', true)
            ->get();

        foreach ($assignments as $assignment) {
            if ($assignment->assignable_type === 'default') {
                // Default grid has query — all pages without explicit grid are affected
                $pageIds = array_merge($pageIds,
                    Page::where('site_id', $site->id)
                        ->where('status', 'published')
                        ->whereNull('grid_id')
                        ->pluck('id')
                        ->toArray()
                );
            }
        }

        return array_unique($pageIds);
    }

    /**
     * Check if a page is referenced in any menu.
     */
    private function isPageInMenu(Site $site, string $pageId): bool
    {
        return \App\Models\MenuItem::whereHas('menu', fn($q) => $q->where('site_id', $site->id))
            ->where('page_id', $pageId)
            ->exists();
    }

    /**
     * Get a visual representation of the dependency graph for the admin UI.
     */
    public function getGraph(Site $site): array
    {
        $nodes = [];
        $edges = [];

        // Pages
        foreach ($site->pages()->where('status', 'published')->get(['id', 'title', 'slug']) as $page) {
            $nodes[] = ['id' => "page:{$page->id}", 'type' => 'page', 'label' => $page->title, 'slug' => $page->slug];
        }

        // Posts
        foreach ($site->posts()->where('status', 'published')->with('category', 'tags')->get(['id', 'title', 'slug', 'category_id']) as $post) {
            $nodes[] = ['id' => "post:{$post->id}", 'type' => 'post', 'label' => $post->title, 'slug' => $post->slug];

            // Post → category edge
            if ($post->category) {
                $catNodeId = "category:{$post->category->id}";
                $edges[] = ['from' => "post:{$post->id}", 'to' => $catNodeId, 'relation' => 'belongs_to'];
            }

            // Post → tag edges
            foreach ($post->tags as $tag) {
                $edges[] = ['from' => "post:{$post->id}", 'to' => "tag:{$tag->id}", 'relation' => 'tagged'];
            }
        }

        // Categories
        foreach ($site->categories()->get(['id', 'name', 'slug']) as $cat) {
            $nodes[] = ['id' => "category:{$cat->id}", 'type' => 'category', 'label' => $cat->name, 'slug' => $cat->slug];
        }

        // Tags
        foreach ($site->tags()->get(['id', 'name', 'slug']) as $tag) {
            $nodes[] = ['id' => "tag:{$tag->id}", 'type' => 'tag', 'label' => $tag->name, 'slug' => $tag->slug];
        }

        // Menus
        foreach ($site->menus()->with('items')->get() as $menu) {
            $menuNodeId = "menu:{$menu->id}";
            $nodes[] = ['id' => $menuNodeId, 'type' => 'menu', 'label' => $menu->name, 'location' => $menu->location];

            foreach ($menu->items as $item) {
                if ($item->page_id) {
                    $edges[] = ['from' => $menuNodeId, 'to' => "page:{$item->page_id}", 'relation' => 'links_to'];
                }
                if ($item->category_id) {
                    $edges[] = ['from' => $menuNodeId, 'to' => "category:{$item->category_id}", 'relation' => 'links_to'];
                }
            }

            // Menu → ALL pages (because menu appears on every page)
            foreach ($site->pages()->where('status', 'published')->pluck('id') as $pid) {
                $edges[] = ['from' => $menuNodeId, 'to' => "page:{$pid}", 'relation' => 'renders_on'];
            }
        }

        // Archives
        $nodes[] = ['id' => 'archive:blog', 'type' => 'archive', 'label' => 'Blog Index', 'slug' => '/blog'];
        $nodes[] = ['id' => 'archive:rss', 'type' => 'feed', 'label' => 'RSS Feed', 'slug' => '/feed.xml'];
        $nodes[] = ['id' => 'archive:sitemap', 'type' => 'feed', 'label' => 'Sitemap', 'slug' => '/sitemap.xml'];

        // All posts connect to blog index and RSS
        foreach ($site->posts()->where('status', 'published')->pluck('id') as $pid) {
            $edges[] = ['from' => "post:{$pid}", 'to' => 'archive:blog', 'relation' => 'listed_in'];
            $edges[] = ['from' => "post:{$pid}", 'to' => 'archive:rss', 'relation' => 'listed_in'];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'stats' => [
                'pages' => count(array_filter($nodes, fn($n) => $n['type'] === 'page')),
                'posts' => count(array_filter($nodes, fn($n) => $n['type'] === 'post')),
                'categories' => count(array_filter($nodes, fn($n) => $n['type'] === 'category')),
                'tags' => count(array_filter($nodes, fn($n) => $n['type'] === 'tag')),
                'menus' => count(array_filter($nodes, fn($n) => $n['type'] === 'menu')),
                'edges' => count($edges),
            ],
        ];
    }
}
