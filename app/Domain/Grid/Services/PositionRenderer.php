<?php

namespace App\Domain\Grid\Services;

use App\Domain\Menus\Services\MenuRenderer;
use App\Domain\Publishing\Services\SanitizationService;
use App\Models\Block;
use App\Models\GridPosition;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Illuminate\Support\Facades\View;

class PositionRenderer
{
    public function __construct(
        private MenuRenderer $menuRenderer,
        private SanitizationService $sanitizer,
    ) {}

    /**
     * Render a single block (used for canvas/fixed positions).
     * Avoids circular dependency with BuildPageService by doing lightweight rendering.
     */
    private function renderBlockHtml(Block $block, Site $site): string
    {
        // Resolve lazily to avoid circular dependency (BuildPageService → GridRenderer → PositionRenderer)
        $builder = app(\App\Domain\Publishing\Services\BuildPageService::class);
        return $builder->renderBlock($block, $site);
    }

    /**
     * Render a single grid position's content.
     */
    public function render(GridPosition $position, Page|Post $content, Site $site): string
    {
        // Check for page-level override
        $override = $content instanceof Post
            ? $position->getOverrideForPost($content->id)
            : $position->getOverrideForPage($content->id);

        if ($override) {
            return $this->renderOverride($override, $position, $content, $site);
        }

        return match ($position->type) {
            'canvas' => $this->renderCanvas($position, $content, $site),
            'menu' => $this->renderMenu($position, $site),
            'query' => $this->renderQuery($position, $content, $site),
            'fixed' => $this->renderFixed($position, $site),
            'widget' => $this->renderWidget($position, $site),
            'static' => $this->renderStatic($position, $content, $site),
            default => "<!-- Unknown position type: {$position->type} -->",
        };
    }

    /**
     * Canvas: renders the page's own blocks.
     * Only the PRIMARY canvas position gets page blocks.
     * Primary = area named 'main', 'content', or the first canvas in the grid.
     */
    private function renderCanvas(GridPosition $position, Page|Post $content, Site $site): string
    {
        // Determine if this is the primary canvas position
        $isPrimary = in_array($position->area_name, ['main', 'content']);

        if (!$isPrimary) {
            // Check if this is the first canvas position in the grid
            $firstCanvas = $position->grid?->positions()
                ->where('type', 'canvas')
                ->orderBy('mobile_order')
                ->first();
            $isPrimary = $firstCanvas && $firstCanvas->id === $position->id;
        }

        if (!$isPrimary) {
            // Non-primary canvas — render empty (or override content if any)
            return '';
        }

        // Check for ThemeTemplate — if a template exists for this post,
        // render template blocks with post context (Grid layout + Template content)
        if ($content instanceof Post) {
            $template = ThemeTemplate::resolveForPost($content);
            if ($template) {
                return $this->renderTemplatedCanvas($template, $content, $site);
            }
        }

        // Standard: render content's own blocks
        $blocks = $content->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderBlockHtml($block, $site);
        }

        return $html;
    }

    /**
     * Render a post's canvas using a ThemeTemplate.
     * Combines Grid layout (wrapper) with Template content (dynamic blocks).
     */
    private function renderTemplatedCanvas(ThemeTemplate $template, Post $post, Site $site): string
    {
        $builder = app(\App\Domain\Publishing\Services\BuildPageService::class);

        // Render post's own blocks as content HTML
        $postBlocks = $post->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        $postContentHtml = '';
        foreach ($postBlocks as $block) {
            $postContentHtml .= $builder->renderBlock($block, $site);
        }

        // Resolve prev/next posts
        $prevPost = null;
        $nextPost = null;
        if ($post->category_id && $post->published_at) {
            $prevPost = Post::where('site_id', $post->site_id)
                ->where('category_id', $post->category_id)
                ->where('status', 'published')
                ->where('published_at', '<', $post->published_at)
                ->orderByDesc('published_at')
                ->first();
            $nextPost = Post::where('site_id', $post->site_id)
                ->where('category_id', $post->category_id)
                ->where('status', 'published')
                ->where('published_at', '>', $post->published_at)
                ->orderBy('published_at')
                ->first();
        }

        $post->loadMissing(['category', 'author']);

        // Render template blocks with post context
        $templateBlocks = $template->blocks()
            ->whereNull('parent_block_id')
            ->orderBy('order')
            ->with('children')
            ->get();

        return $builder->renderBlocksWithContext($templateBlocks, $site, [
            '__post' => $post,
            '__postContentHtml' => $postContentHtml,
            '__prevPost' => $prevPost,
            '__nextPost' => $nextPost,
        ]);
    }

    /**
     * Menu: renders a named menu location.
     */
    private function renderMenu(GridPosition $position, Site $site): string
    {
        $config = $position->config_json ?? [];
        $location = $config['location'] ?? 'header';

        return $this->menuRenderer->renderByLocation($site, $location);
    }

    /**
     * Query: auto-populated from post query.
     */
    private function renderQuery(GridPosition $position, Page|Post $content, Site $site): string
    {
        $config = $position->config_json ?? [];
        $count = $config['count'] ?? 6;
        $orderBy = $config['order_by'] ?? 'date';
        $order = $config['order'] ?? 'desc';
        $layout = $config['layout'] ?? 'grid';
        $cardStyle = $config['card_style'] ?? 'default';
        $excludeCurrent = $config['exclude_current'] ?? true;

        $query = \App\Models\Post::withoutGlobalScopes()->with('category')
            ->with('category')
            ->where('site_id', $site->id)->where('status', 'published');

        if (!empty($config['category_ids'])) {
            $query->whereIn('category_id', $config['category_ids']);
        }

        if (!empty($config['context_aware']) && $content instanceof Post && $content->category_id) {
            $query->where('category_id', $content->category_id);
        }

        if ($excludeCurrent && $content instanceof Post) {
            $query->where('id', '!=', $content->id);
        }

        $orderColumn = match ($orderBy) {
            'title' => 'title',
            'random' => \Illuminate\Support\Facades\DB::raw('RANDOM()'),
            default => 'published_at',
        };

        $posts = $query->orderBy($orderColumn, $order)->limit($count)->get();

        if ($posts->isEmpty()) {
            return '';
        }

        return View::make('positions.query', [
            'posts' => $posts,
            'layout' => $layout,
            'cardStyle' => $cardStyle,
            'site' => $site,
        ])->render();
    }

    /**
     * Fixed: renders site-level blocks from grid_position_blocks.
     */
    private function renderFixed(GridPosition $position, Site $site): string
    {
        $config = $position->config_json ?? [];

        // If a blade partial is specified, render it
        if (!empty($config['blade_partial'])) {
            $viewName = 'positions.' . $config['blade_partial'];
            if (View::exists($viewName)) {
                return View::make($viewName, ['site' => $site, 'position' => $position])->render();
            }
        }

        // Otherwise render associated blocks
        $posBlocks = $position->positionBlocks()->with('block.children')->orderBy('order')->get();
        $html = '';
        foreach ($posBlocks as $pb) {
            if ($pb->block) {
                $html .= $this->buildPageService->renderBlock($pb->block, $site);
            }
        }

        return $html;
    }

    /**
     * Widget: renders a stack of mini-widgets.
     */
    private function renderWidget(GridPosition $position, Site $site): string
    {
        $config = $position->config_json ?? [];
        $widgets = $config['widgets'] ?? [];

        if (empty($widgets)) return '';

        $stickyStyle = '';
        if (!empty($config['sticky'])) {
            $offset = $config['sticky_offset'] ?? 80;
            $stickyStyle = " style=\"position: sticky; top: {$offset}px;\"";
        }

        $html = "<aside class=\"widget-stack\"{$stickyStyle}>\n";

        foreach ($widgets as $widget) {
            $type = $widget['type'] ?? '';
            $viewName = "widgets.{$type}";

            if (View::exists($viewName)) {
                $html .= View::make($viewName, ['widget' => $widget, 'site' => $site])->render();
            } else {
                $html .= $this->renderBuiltinWidget($widget, $site);
            }
        }

        $html .= "</aside>\n";

        return $html;
    }

    /**
     * Static: renders a named Blade partial.
     */
    private function renderStatic(GridPosition $position, Page|Post $content, Site $site): string
    {
        $config = $position->config_json ?? [];
        $partial = $config['partial'] ?? '';

        if (!$partial) return '';

        $viewName = "positions.{$partial}";
        if (!View::exists($viewName)) {
            return "<!-- Missing partial: {$partial} -->";
        }

        return View::make($viewName, [
            'page' => $content,
            'site' => $site,
            'position' => $position,
        ])->render();
    }

    private function renderOverride($override, GridPosition $position, Page|Post $content, Site $site): string
    {
        $overrideData = $override->content_json ?? [];

        // For canvas overrides, the content_json contains block data
        if ($position->type === 'canvas' && !empty($overrideData['blocks'])) {
            // Render blocks from override data
            $html = '';
            foreach ($overrideData['blocks'] as $blockData) {
                $block = new Block($blockData);
                $html .= $this->renderBlockHtml($block, $site);
            }
            return $html;
        }

        // For widget overrides
        if ($position->type === 'widget' && !empty($overrideData['widgets'])) {
            $tempPos = clone $position;
            $tempPos->config_json = $overrideData;
            return $this->renderWidget($tempPos, $site);
        }

        // Default: render as fixed with override content
        return $overrideData['html'] ?? '';
    }

    /** Render widget heading — returns empty string if title is blank. */
    private function widgetTitle(string $title, string $style = ''): string
    {
        $title = trim($title);
        if ($title === '') return '';
        if (!$style) $style = 'font-weight:600;margin-bottom:0.75rem;';
        return '<h3 style="' . $style . '">' . e($title) . '</h3>';
    }

    private function renderBuiltinWidget(array $widget, Site $site): string
    {
        $type = $widget['type'] ?? '';
        $html = "<div class=\"widget widget-{$type}\">\n";

        switch ($type) {
            case 'search':
                $html .= '<form class="widget-search" action="/search" method="GET"><input type="search" name="q" placeholder="Search..." style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;"><button type="submit" style="margin-top:0.5rem;padding:0.5rem 1rem;background:var(--color-primary,#3b82f6);color:#fff;border:none;border-radius:0.375rem;cursor:pointer;">Search</button></form>';
                break;

            case 'recent_posts':
                $count = $widget['count'] ?? 5;
                $title = $widget['title'] ?? 'Recent Posts';
                $showDate = $widget['show_date'] ?? true;
                $showCategory = $widget['show_category'] ?? false;
                $categoryId = $widget['category_id'] ?? null;
                $includeChildren = $widget['include_children'] ?? false;

                $query = \App\Models\Post::withoutGlobalScopes()->with('category')
                    ->where('site_id', $site->id)
                    ->where('status', 'published')
                    ->orderByDesc('published_at')
                    ->limit($count);

                // Filter by category
                if ($categoryId) {
                    if ($includeChildren) {
                        // Get category + all child category IDs
                        $catIds = [$categoryId];
                        $children = \App\Models\Category::withoutGlobalScopes()
                            ->where('site_id', $site->id)
                            ->where('parent_id', $categoryId)
                            ->pluck('id')->toArray();
                        $catIds = array_merge($catIds, $children);
                        // Go one more level deep
                        if (!empty($children)) {
                            $grandchildren = \App\Models\Category::withoutGlobalScopes()
                                ->where('site_id', $site->id)
                                ->whereIn('parent_id', $children)
                                ->pluck('id')->toArray();
                            $catIds = array_merge($catIds, $grandchildren);
                        }
                        $query->whereIn('category_id', $catIds);
                    } else {
                        $query->where('category_id', $categoryId);
                    }
                }

                $posts = $query->get();

                $html .= $this->widgetTitle($title);
                if ($posts->isEmpty()) {
                    $html .= '<p style="font-size:0.875rem;color:var(--color-text-muted,#9ca3af);">No posts yet</p>';
                } else {
                    $html .= '<ul style="list-style:none;padding:0;">';
                    foreach ($posts as $post) {
                        $html .= '<li style="margin-bottom:0.75rem;padding-bottom:0.75rem;border-bottom:1px solid var(--color-border-light,#f1f5f9);">';
                        $html .= '<a href="' . e($post->url_path) . '" style="color:var(--color-text,#374151);text-decoration:none;font-size:0.875rem;font-weight:500;">' . e($post->title) . '</a>';
                        if ($showDate && $post->published_at) {
                            $html .= '<span style="display:block;font-size:0.75rem;color:var(--color-text-muted,#9ca3af);margin-top:0.125rem;">' . $post->published_at->format('M j, Y') . '</span>';
                        }
                        if ($showCategory && $post->category_id) {
                            $cat = \App\Models\Category::withoutGlobalScopes()->find($post->category_id);
                            if ($cat) {
                                $html .= '<a href="/' . e($cat->slug) . '" style="font-size:0.75rem;color:var(--color-primary,#3b82f6);">' . e($cat->name) . '</a>';
                            }
                        }
                        $html .= '</li>';
                    }
                    $html .= '</ul>';
                }
                break;

            case 'category_tree':
                $cats = \App\Models\Category::withoutGlobalScopes()
                    ->where('site_id', $site->id)
                    ->withCount(['posts' => fn($q) => $q->where('status', 'published')])
                    ->orderBy('name')->get();
                $html .= $this->widgetTitle($widget['title'] ?? 'Categories') . '<ul style="list-style:none;padding:0;">';
                foreach ($cats as $cat) {
                    $html .= '<li style="margin-bottom:0.25rem;"><a href="/' . e($cat->slug) . '" style="color:var(--color-text,#374151);text-decoration:none;">' . e($cat->name) . '</a>';
                    if (!empty($widget['show_count'])) $html .= ' <span style="color:var(--color-text-muted,#9ca3af);">(' . $cat->posts_count . ')</span>';
                    $html .= '</li>';
                }
                $html .= '</ul>';
                break;

            case 'tag_cloud':
                $tags = \App\Models\Tag::withoutGlobalScopes()
                    ->where('site_id', $site->id)->withCount('posts')->orderBy('name')->get();
                $html .= $this->widgetTitle($widget['title'] ?? 'Tags') . '<div style="display:flex;flex-wrap:wrap;gap:0.5rem;">';
                foreach ($tags as $tag) {
                    $html .= '<a href="/tag/' . e($tag->slug) . '" style="display:inline-block;padding:0.25rem 0.75rem;background:#f3f4f6;border-radius:1rem;font-size:0.875rem;color:#374151;text-decoration:none;">' . e($tag->name) . '</a>';
                }
                $html .= '</div>';
                break;

            case 'custom_html':
                $html .= $widget['html'] ?? '';
                break;

            case 'newsletter':
                $title = $widget['title'] ?? 'Newsletter';
                $html .= $this->widgetTitle($title);
                $desc = $widget['description'] ?? 'Get the latest posts in your inbox.';
                $html .= '<p style="font-size:0.875rem;color:var(--color-text-muted,#64748b);margin-bottom:0.75rem;">' . e($desc) . '</p>';
                $html .= '<form style="display:flex;flex-direction:column;gap:0.5rem;"><input type="email" placeholder="Your email" required style="padding:0.625rem;border:1px solid var(--color-border,#d1d5db);border-radius:var(--border-radius-md,0.375rem);font-size:0.875rem;"><button type="submit" style="padding:0.625rem 1rem;background:var(--color-primary,#3b82f6);color:#fff;border:none;border-radius:var(--border-radius-md,0.375rem);font-weight:600;cursor:pointer;">Subscribe</button></form>';
                break;

            case 'author_bio':
                $html .= $this->widgetTitle($widget['title'] ?? 'About the Author');
                $user = \App\Models\User::where('tenant_id', $site->tenant_id)->where('role', 'owner')->first();
                if ($user) {
                    $html .= '<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">';
                    $html .= '<div style="width:48px;height:48px;border-radius:50%;background:var(--color-primary,#3b82f6);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:1.25rem;">' . strtoupper(substr($user->name, 0, 1)) . '</div>';
                    $html .= '<div><p style="font-weight:600;">' . e($user->name) . '</p><p style="font-size:0.75rem;color:var(--color-text-muted,#64748b);">' . e($user->email) . '</p></div>';
                    $html .= '</div>';
                }
                break;

            case 'popular_posts':
                $count = $widget['count'] ?? 5;
                $title = $widget['title'] ?? 'Popular Posts';
                $categoryId = $widget['category_id'] ?? null;
                $includeChildren = $widget['include_children'] ?? false;

                $query = \App\Models\Post::withoutGlobalScopes()->with('category')
                    ->where('site_id', $site->id)->where('status', 'published')
                    ->withCount('blocks')
                    ->orderByDesc('blocks_count');

                if ($categoryId) {
                    if ($includeChildren) {
                        $catIds = [$categoryId];
                        $children = \App\Models\Category::withoutGlobalScopes()
                            ->where('site_id', $site->id)
                            ->where('parent_id', $categoryId)
                            ->pluck('id')->toArray();
                        $catIds = array_merge($catIds, $children);
                        if (!empty($children)) {
                            $grandchildren = \App\Models\Category::withoutGlobalScopes()
                                ->where('site_id', $site->id)
                                ->whereIn('parent_id', $children)
                                ->pluck('id')->toArray();
                            $catIds = array_merge($catIds, $grandchildren);
                        }
                        $query->whereIn('category_id', $catIds);
                    } else {
                        $query->where('category_id', $categoryId);
                    }
                }

                $posts = $query->limit($count)->get();
                $html .= $this->widgetTitle($title) . '<ol style="padding-left:1.25rem;margin:0;">';
                foreach ($posts as $idx => $post) {
                    $html .= '<li style="margin-bottom:0.5rem;"><a href="' . e($post->url_path) . '" style="color:var(--color-text,#374151);text-decoration:none;font-size:0.875rem;">' . e($post->title) . '</a></li>';
                }
                $html .= '</ol>';
                break;

            case 'latest_from_category':
                $categoryId = $widget['category_id'] ?? null;
                $title = $widget['title'] ?? '';
                $showImage = $widget['show_image'] ?? true;
                $showDate = $widget['show_date'] ?? true;
                $showCategory = $widget['show_category'] ?? true;
                $count = $widget['count'] ?? 1;
                $includeChildren = $widget['include_children'] ?? false;
                // content_mode: 'none', 'excerpt', 'full'
                $contentMode = $widget['content_mode'] ?? 'excerpt';
                $excerptLength = (int) ($widget['excerpt_length'] ?? 200);

                $query = \App\Models\Post::withoutGlobalScopes()->with('category')
                    ->where('site_id', $site->id)
                    ->where('status', 'published')
                    ->orderByDesc('published_at');

                if ($categoryId) {
                    if ($includeChildren) {
                        $catIds = [$categoryId];
                        $children = \App\Models\Category::withoutGlobalScopes()
                            ->where('site_id', $site->id)
                            ->where('parent_id', $categoryId)
                            ->pluck('id')->toArray();
                        $catIds = array_merge($catIds, $children);
                        if (!empty($children)) {
                            $grandchildren = \App\Models\Category::withoutGlobalScopes()
                                ->where('site_id', $site->id)
                                ->whereIn('parent_id', $children)
                                ->pluck('id')->toArray();
                            $catIds = array_merge($catIds, $grandchildren);
                        }
                        $query->whereIn('category_id', $catIds);
                    } else {
                        $query->where('category_id', $categoryId);
                    }
                }

                $latestPosts = $query->limit($count)->get();

                $html .= $this->widgetTitle($title);

                foreach ($latestPosts as $post) {
                    $html .= '<div class="widget-latest-post" style="margin-bottom:1.5rem;">';

                    // Featured image
                    if ($showImage && $post->featured_image) {
                        $html .= '<a href="' . e($post->url_path) . '" style="display:block;margin-bottom:0.75rem;"><img src="' . e($post->featured_image) . '" alt="' . e($post->title) . '" style="width:100%;border-radius:var(--border-radius-md,0.375rem);aspect-ratio:16/9;object-fit:cover;"></a>';
                    }

                    // Category badge
                    if ($showCategory && $post->category_id) {
                        $postCat = \App\Models\Category::withoutGlobalScopes()->find($post->category_id);
                        if ($postCat) {
                            $html .= '<a href="/' . e($postCat->slug) . '" style="display:inline-block;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-primary,#3b82f6);text-decoration:none;margin-bottom:0.375rem;">' . e($postCat->name) . '</a>';
                        }
                    }

                    // Title
                    $html .= '<a href="' . e($post->url_path) . '" style="font-weight:600;font-size:1.125rem;color:var(--color-text,#1e293b);text-decoration:none;display:block;margin-bottom:0.375rem;line-height:1.3;">' . e($post->title) . '</a>';

                    // Date
                    if ($showDate && $post->published_at) {
                        $html .= '<time style="font-size:0.75rem;color:var(--color-text-muted,#9ca3af);display:block;margin-bottom:0.5rem;">' . $post->published_at->format('d.m.Y') . '</time>';
                    }

                    // Content: excerpt or full
                    if ($contentMode === 'full') {
                        // Render ALL blocks as full content
                        $blocks = $post->blocks()
                            ->whereNull('parent_block_id')
                            ->orderBy('order')
                            ->with('children')
                            ->get();
                        if ($blocks->isNotEmpty()) {
                            $html .= '<div class="widget-post-content" style="font-size:0.9rem;line-height:1.7;color:var(--color-text,#374151);margin-top:0.5rem;">';
                            foreach ($blocks as $block) {
                                $html .= $this->renderBlockHtml($block, $site);
                            }
                            $html .= '</div>';
                        }
                    } elseif ($contentMode === 'excerpt') {
                        // Show excerpt (manual or auto-extracted), trimmed to excerpt_length
                        $excerptText = $post->excerpt;
                        if (!$excerptText) {
                            $firstBlock = $post->blocks()
                                ->whereNull('parent_block_id')
                                ->whereIn('type', ['text', 'paragraph'])
                                ->orderBy('order')
                                ->first();
                            if ($firstBlock) {
                                $blockData = $firstBlock->data ?? [];
                                $rawContent = $blockData['content'] ?? $blockData['text'] ?? '';
                                $plain = strip_tags($rawContent);
                                $excerptText = mb_substr($plain, 0, $excerptLength);
                                if (mb_strlen($plain) > $excerptLength) {
                                    $excerptText .= '...';
                                }
                            }
                        } else {
                            // Trim manual excerpt too if length set
                            if ($excerptLength > 0 && mb_strlen($excerptText) > $excerptLength) {
                                $excerptText = mb_substr($excerptText, 0, $excerptLength) . '...';
                            }
                        }
                        if ($excerptText) {
                            $html .= '<p style="font-size:0.875rem;color:var(--color-text-muted,#64748b);margin:0;line-height:1.6;">' . e($excerptText) . '</p>';
                        }
                    }
                    // contentMode === 'none' → show nothing

                    // Read more link (not needed for full content)
                    if ($contentMode !== 'full') {
                        $html .= '<a href="' . e($post->url_path) . '" style="display:inline-block;margin-top:0.75rem;font-size:0.8rem;font-weight:600;color:var(--color-primary,#3b82f6);text-decoration:none;">Прочети повече →</a>';
                    }

                    $html .= '</div>';
                }

                if ($latestPosts->isEmpty()) {
                    $html .= '<p style="font-size:0.875rem;color:var(--color-text-muted,#9ca3af);">Няма постове в тази категория</p>';
                }
                break;

            case 'social_links':
                $links = $widget['links'] ?? [];
                $html .= $this->widgetTitle($widget['title'] ?? 'Follow Us') . '<div style="display:flex;gap:0.75rem;">';
                foreach ($links as $link) {
                    $name = $link['name'] ?? '';
                    $url = $link['url'] ?? '#';
                    $html .= '<a href="' . e($url) . '" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:var(--color-bg-alt,#f1f5f9);color:var(--color-text,#374151);text-decoration:none;font-size:0.75rem;font-weight:bold;" title="' . e($name) . '">' . strtoupper(substr($name, 0, 2)) . '</a>';
                }
                $html .= '</div>';
                break;

            case 'post_navigation':
                $html .= $this->widgetTitle($widget['title'] ?? 'More Posts');
                $latest = \App\Models\Post::withoutGlobalScopes()->with('category')
                    ->where('site_id', $site->id)->where('status', 'published')
                    ->orderByDesc('published_at')->limit(3)->get();
                foreach ($latest as $p) {
                    $html .= '<a href="' . e($p->url_path) . '" style="display:block;padding:0.5rem 0;border-bottom:1px solid var(--color-border-light,#f1f5f9);color:var(--color-text,#374151);text-decoration:none;font-size:0.875rem;">' . e($p->title) . '<span style="display:block;font-size:0.75rem;color:var(--color-text-muted,#9ca3af);">' . ($p->published_at?->format('M j, Y') ?? '') . '</span></a>';
                }
                break;

            case 'site_info':
                $html .= '<div style="text-align:center;">';
                $html .= $this->widgetTitle($site->name, 'font-weight:700;font-size:1.125rem;margin-bottom:0.25rem;');
                $desc = $site->seo_defaults['description'] ?? '';
                if ($desc) $html .= '<p style="font-size:0.875rem;color:var(--color-text-muted,#64748b);">' . e($desc) . '</p>';
                $html .= '</div>';
                break;

            case 'logo':
                $logoUrl = $widget['url'] ?? '';
                $siteName = $site->name;
                if ($logoUrl) {
                    $html .= '<a href="/" style="display:block;"><img src="' . e($logoUrl) . '" alt="' . e($siteName) . '" style="max-height:48px;width:auto;"></a>';
                } else {
                    $html .= '<a href="/" style="font-size:1.5rem;font-weight:800;color:var(--color-text,#0f172a);text-decoration:none;font-family:var(--font-heading);">' . e($siteName) . '</a>';
                }
                break;

            case 'copyright':
                $year = date('Y');
                $text = $widget['text'] ?? '© ' . $year . ' ' . $site->name . '. All rights reserved.';
                $text = str_replace('{{year}}', $year, $text);
                $text = str_replace('{{site_name}}', $site->name, $text);
                $html .= '<p style="font-size:0.75rem;color:var(--color-text-muted,#9ca3af);text-align:center;">' . e($text) . '</p>';
                break;

            case 'back_to_top':
                $html .= '<div style="text-align:center;"><a href="#" onclick="window.scrollTo({top:0,behavior:\'smooth\'});return false;" style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.5rem 1rem;border:1px solid var(--color-border,#e5e7eb);border-radius:var(--border-radius-md,0.375rem);color:var(--color-text-muted,#64748b);text-decoration:none;font-size:0.875rem;">↑ Back to top</a></div>';
                break;

            case 'cta_banner':
                $title = $widget['title'] ?? 'Ready to get started?';
                $btnText = $widget['button_text'] ?? 'Get Started';
                $btnUrl = $widget['button_url'] ?? '#';
                $bgColor = $widget['bg_color'] ?? 'var(--color-primary,#3b82f6)';
                $html .= '<div style="background:' . $bgColor . ';color:#fff;padding:1.5rem;border-radius:var(--border-radius-lg,0.75rem);text-align:center;">';
                if (trim($title)) $html .= '<h3 style="font-weight:700;margin-bottom:0.75rem;color:#fff;">' . e($title) . '</h3>';
                $html .= '<a href="' . e($btnUrl) . '" style="display:inline-block;padding:0.625rem 1.5rem;background:#fff;color:' . $bgColor . ';border-radius:var(--border-radius-md,0.375rem);font-weight:600;text-decoration:none;">' . e($btnText) . '</a>';
                $html .= '</div>';
                break;

            case 'related_posts':
                // Context-aware: show posts from same category
                $count = $widget['count'] ?? 3;
                $posts = $site->posts()->where('status', 'published')
                    ->orderByDesc('published_at')->limit($count)->get();
                $html .= $this->widgetTitle($widget['title'] ?? 'Related Posts');
                foreach ($posts as $post) {
                    $html .= '<a href="' . e($post->url_path) . '" style="display:block;padding:0.5rem 0;border-bottom:1px solid var(--color-border-light,#f1f5f9);text-decoration:none;color:var(--color-text,#374151);font-size:0.875rem;">' . e($post->title) . '</a>';
                }
                break;

            case 'image':
                $src = $widget['src'] ?? '';
                $alt = $widget['alt'] ?? '';
                $link = $widget['link'] ?? '';
                if ($src) {
                    $img = '<img src="' . e($src) . '" alt="' . e($alt) . '" style="width:100%;border-radius:var(--border-radius-md,0.375rem);">';
                    $html .= $link ? '<a href="' . e($link) . '">' . $img . '</a>' : $img;
                }
                break;

            case 'rich_text':
                $html .= $widget['content'] ?? '';
                break;

            default:
                $html .= "<!-- Unknown widget: {$type} -->";
        }

        $html .= "</div>\n";
        return $html;
    }
}
