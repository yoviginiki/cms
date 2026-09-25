<?php

namespace App\Domain\Grid\Services;

use App\Models\Category;
use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\Layout;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Models\ThemeTemplate;
use Illuminate\Support\Collection;

/**
 * Which grid a page/post is ACTUALLY published with — the grid resolver's
 * answer, minus the cases where the publisher bypasses grids entirely
 * (magazine pages, explicit layouts, raw HTML, exact-copy sites, posts that
 * render their builder output verbatim). BuildPageService asks skipReason()
 * so the admin labels and the published output can't drift apart.
 *
 * Memoises per-site lookups, so labelling a whole list costs a few queries.
 */
class EffectiveGridResolver
{
    /** Human-readable explanation per skip reason. */
    public const SKIP_LABELS = [
        'magazine' => 'Magazine page — renders its own layout',
        'exact_design' => 'Exact-copy design site — pages carry their own chrome',
        'layout' => 'A page layout replaces the grid',
        'raw_html' => 'Custom HTML page',
        'post_builder' => 'Post without a template on a legacy site — standard layout (header/footer from Templates or Menus)',
    ];

    /** @var array<string, Collection<int, GridAssignment>> */
    private array $assignments = [];
    /** @var array<string, ?Grid> */
    private array $grids = [];
    /** @var array<string, ?string> */
    private array $categoryGridIds = [];
    /** @var array<string, bool> */
    private array $standardLayout = [];
    /** @var array<string, bool> */
    private array $postTemplateExists = [];

    public function __construct(private GridResolver $resolver) {}

    /**
     * @return array{grid: ?array{id: string, name: string, slug: string}, source: string, reason: ?string, label: string}
     *   source: override | category | page | post | post_type | rule | default | none | skipped
     */
    public function forContent(Page|Post $content, Site $site): array
    {
        if ($reason = $this->skipReason($content, $site)) {
            return ['grid' => null, 'source' => 'skipped', 'reason' => $reason, 'label' => self::SKIP_LABELS[$reason]];
        }

        $match = $this->resolver->match($content, $this->assignmentsFor($site), fn (string $categoryId) => $this->categoryGridId($categoryId));
        $grid = $match['grid_id'] ? $this->grid($match['grid_id']) : null;

        if (!$grid) {
            return ['grid' => null, 'source' => 'none', 'reason' => null, 'label' => 'No grid — standard layout'];
        }

        return [
            'grid' => ['id' => $grid->id, 'name' => $grid->name, 'slug' => $grid->slug],
            'source' => $match['source'],
            'reason' => null,
            'label' => $grid->name,
        ];
    }

    /**
     * Unified sites (every site created since 2026-09-25) render every post
     * inside the grid, like pages. Older sites keep the legacy rule: posts
     * without a template skip the grid and the grid canvas applies the
     * default post template regardless of the post's own choice.
     */
    public static function postsUseGrid(Site $site): bool
    {
        return ($site->settings['post_grid'] ?? null) === 'unified';
    }

    /** Null when the grid applies; otherwise a key of SKIP_LABELS. */
    public function skipReason(Page|Post $content, Site $site): ?string
    {
        if ($content instanceof Page && $content->editor_mode === 'magazine') {
            return 'magazine';
        }
        if (($site->settings['design_fidelity'] ?? null) === 'exact') {
            return 'exact_design';
        }
        if ($content->layout_id && !$this->isStandardLayout($content->layout_id)) {
            return 'layout';
        }
        if ($content instanceof Page && $content->raw_html) {
            return 'raw_html';
        }
        if ($content instanceof Post && !self::postsUseGrid($site) && $this->rendersBuilderVerbatim($content)) {
            return 'post_builder';
        }
        return null;
    }

    /**
     * What each grid of the site is used by: pages/posts that publish with it,
     * categories that point at it, and the assignments that select it.
     *
     * @return array<string, array{pages: list<array{id: string, title: string}>, posts: int, categories: list<array{id: string, name: string}>, is_default: bool}>
     */
    public function usage(Site $site): array
    {
        $usage = [];
        $slot = function (string $gridId) use (&$usage) {
            return $usage[$gridId] ??= ['pages' => [], 'posts' => 0, 'categories' => [], 'is_default' => false];
        };

        foreach ($this->assignmentsFor($site) as $a) {
            if ($a->assignable_type === 'default') {
                $slot($a->grid_id);
                $usage[$a->grid_id]['is_default'] = true;
            }
        }

        foreach (Category::where('site_id', $site->id)->whereNotNull('grid_id')->get(['id', 'name', 'grid_id']) as $cat) {
            $slot($cat->grid_id);
            $usage[$cat->grid_id]['categories'][] = ['id' => $cat->id, 'name' => $cat->name];
        }

        Page::where('site_id', $site->id)
            ->orderBy('title')
            ->get(['id', 'site_id', 'title', 'slug', 'grid_id', 'layout_id', 'editor_mode', 'raw_html'])
            ->each(function (Page $page) use ($site, $slot, &$usage) {
                $r = $this->forContent($page, $site);
                if ($id = $r['grid']['id'] ?? null) {
                    $slot($id);
                    $usage[$id]['pages'][] = ['id' => $page->id, 'title' => $page->title];
                }
            });

        Post::where('site_id', $site->id)
            ->select(['id', 'site_id', 'category_id', 'grid_id', 'layout_id', 'editor_mode', 'post_format', 'seo_meta'])
            ->chunkById(500, function ($posts) use ($site, $slot, &$usage) {
                foreach ($posts as $post) {
                    $r = $this->forContent($post, $site);
                    if ($id = $r['grid']['id'] ?? null) {
                        $slot($id);
                        $usage[$id]['posts']++;
                    }
                }
            });

        return $usage;
    }

    /** Mirrors BuildPageService: no post template + builder/none choice → verbatim builder output. */
    private function rendersBuilderVerbatim(Post $post): bool
    {
        $choice = $post->seo_meta['template_id'] ?? null;
        if ($this->postHasTemplate($post)) {
            return false;
        }
        return $choice === 'none' || in_array($post->editor_mode, ['block', 'canvas', 'magazine'], true);
    }

    private function postHasTemplate(Post $post): bool
    {
        $key = implode('|', [$post->category_id, $post->post_format, $post->editor_mode, json_encode($post->seo_meta['template_id'] ?? null)]);
        return $this->postTemplateExists[$key] ??= ThemeTemplate::chosenForPost($post) !== null;
    }

    private function assignmentsFor(Site $site): Collection
    {
        return $this->assignments[$site->id] ??= GridAssignment::where('site_id', $site->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();
    }

    private function grid(string $id): ?Grid
    {
        return array_key_exists($id, $this->grids)
            ? $this->grids[$id]
            : $this->grids[$id] = Grid::find($id, ['id', 'name', 'slug']);
    }

    private function categoryGridId(string $categoryId): ?string
    {
        return array_key_exists($categoryId, $this->categoryGridIds)
            ? $this->categoryGridIds[$categoryId]
            : $this->categoryGridIds[$categoryId] = Category::whereKey($categoryId)->value('grid_id');
    }

    private function isStandardLayout(string $layoutId): bool
    {
        return $this->standardLayout[$layoutId] ??= (Layout::whereKey($layoutId)->value('slug') ?? 'standard') === 'standard';
    }
}
