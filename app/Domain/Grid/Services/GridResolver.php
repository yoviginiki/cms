<?php

namespace App\Domain\Grid\Services;

use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

class GridResolver
{
    /**
     * Resolve which grid to use for a given page or post.
     * Resolution order:
     * 1. Direct grid_id on the page/post
     * 2. Exact page/post assignment
     * 3. Category assignment (for posts)
     * 4. Post type assignment
     * 5. Site default
     */
    public function resolve(Page|Post $content, Site $site): ?Grid
    {
        // 1. Direct override on content
        if ($content->grid_id) {
            $grid = Grid::with('positions')->find($content->grid_id);
            if ($grid) return $grid;
        }

        $isPost = $content instanceof Post;
        $assignments = GridAssignment::where('site_id', $site->id)
            ->where('is_active', true)
            ->orderBy('priority')
            ->get();

        foreach ($assignments as $assignment) {
            $match = match ($assignment->assignable_type) {
                // 2. Exact page/post assignment
                'page' => !$isPost && $assignment->assignable_id === $content->id,
                'post' => $isPost && $assignment->assignable_id === $content->id,

                // 3. Category assignment (for posts)
                'category' => $isPost && $content->category_id === $assignment->assignable_id,

                // 4. Post type assignment
                'post_type' => match ($assignment->assignable_id) {
                    'post' => $isPost,
                    'page' => !$isPost,
                    default => false,
                },

                // 5. Rule-based (URL pattern)
                'rule' => $this->matchesRule($content, $assignment->assignable_id),

                // 6. Site default
                'default' => true,

                default => false,
            };

            if ($match) {
                return Grid::with('positions')->find($assignment->grid_id);
            }
        }

        return null;
    }

    private function matchesRule(Page|Post $content, ?string $pattern): bool
    {
        if (!$pattern) return false;

        $slug = $content instanceof Post
            ? '/' . ($content->category ? $content->category->slug . '/' : '') . $content->slug
            : '/' . ($content->slug === 'home' ? '' : $content->slug);

        return fnmatch($pattern, $slug);
    }
}
