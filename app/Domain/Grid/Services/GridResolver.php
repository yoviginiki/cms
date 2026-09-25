<?php

namespace App\Domain\Grid\Services;

use App\Models\Category;
use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Collection;

class GridResolver
{
    /**
     * Resolve which grid to use for a given page or post.
     * Resolution order:
     * 1. Direct grid_id on the page/post
     * 2. The post's category grid (Categories screen)
     * 3. Assignments by priority: exact page/post, category, post type, URL rule, site default
     */
    public function resolve(Page|Post $content, Site $site): ?Grid
    {
        return $this->resolveDetailed($content, $site)['grid'];
    }

    /**
     * Like resolve(), but also reports where the grid came from,
     * so the admin can show "inherited from X".
     *
     * @return array{grid: ?Grid, source: string, assignment: ?GridAssignment}
     *         source: override | category | page | post | post_type | rule | default | none
     */
    public function resolveDetailed(Page|Post $content, Site $site): array
    {
        $assignments = GridAssignment::where('site_id', $site->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        $match = $this->match($content, $assignments, fn (string $categoryId) => Category::whereKey($categoryId)->value('grid_id'));
        $grid = $match['grid_id'] ? Grid::with('positions')->find($match['grid_id']) : null;

        // A dangling override/category grid falls through to the assignments.
        if (!$grid && in_array($match['source'], ['override', 'category'], true)) {
            $match = $this->match($content, $assignments, fn () => null, assignmentsOnly: true);
            $grid = $match['grid_id'] ? Grid::with('positions')->find($match['grid_id']) : null;
        }

        return $grid
            ? ['grid' => $grid, 'source' => $match['source'], 'assignment' => $match['assignment']]
            : ['grid' => null, 'source' => 'none', 'assignment' => null];
    }

    /**
     * The resolution order, over preloaded assignments (active, priority-sorted).
     * Shared by resolveDetailed() and EffectiveGridResolver's batch labelling.
     *
     * @param  callable(string): ?string  $categoryGridId
     * @return array{grid_id: ?string, source: string, assignment: ?GridAssignment}
     */
    public function match(Page|Post $content, Collection $assignments, callable $categoryGridId, bool $assignmentsOnly = false): array
    {
        // 1. Direct override on content
        if (!$assignmentsOnly && $content->grid_id) {
            return ['grid_id' => $content->grid_id, 'source' => 'override', 'assignment' => null];
        }

        $isPost = $content instanceof Post;

        // 2. The category's own grid
        if (!$assignmentsOnly && $isPost && $content->category_id && ($catGrid = $categoryGridId($content->category_id))) {
            return ['grid_id' => $catGrid, 'source' => 'category', 'assignment' => null];
        }

        foreach ($assignments as $assignment) {
            $isMatch = match ($assignment->assignable_type) {
                'page' => !$isPost && $assignment->assignable_id === $content->id,
                'post' => $isPost && $assignment->assignable_id === $content->id,
                'category' => $isPost && $content->category_id === $assignment->assignable_id,
                'post_type' => match ($assignment->assignable_id) {
                    'post' => $isPost,
                    'page' => !$isPost,
                    default => false,
                },
                'rule' => $this->matchesRule($content, $assignment->assignable_id),
                'default' => true,
                default => false,
            };

            if ($isMatch) {
                return ['grid_id' => $assignment->grid_id, 'source' => $assignment->assignable_type, 'assignment' => $assignment];
            }
        }

        return ['grid_id' => null, 'source' => 'none', 'assignment' => null];
    }

    private function matchesRule(Page|Post $content, ?string $pattern): bool
    {
        if (!$pattern) return false;

        $slug = $content instanceof Post
            ? $content->url_path
            : '/' . ($content->slug === 'home' ? '' : $content->slug);

        return fnmatch($pattern, $slug);
    }
}
