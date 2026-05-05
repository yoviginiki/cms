<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SiteResetController extends Controller
{
    /**
     * Get current content counts (preview what will be deleted).
     */
    public function preview(Request $request, Site $site): JsonResponse
    {
        $this->authorize('delete', $site);

        return response()->json(['data' => $this->getCounts($site)]);
    }

    /**
     * Wipe all content from a site. Requires confirmation code.
     * Does NOT delete the site itself, theme, grids, or menus.
     */
    public function resetContent(Request $request, Site $site): JsonResponse
    {
        $this->authorize('delete', $site);

        $request->validate([
            'confirm' => ['required', 'string'],
            'options' => ['required', 'array'],
            'options.pages' => ['sometimes', 'boolean'],
            'options.posts' => ['sometimes', 'boolean'],
            'options.categories' => ['sometimes', 'boolean'],
            'options.tags' => ['sometimes', 'boolean'],
            'options.assets' => ['sometimes', 'boolean'],
            'options.menus' => ['sometimes', 'boolean'],
            'options.deployments' => ['sometimes', 'boolean'],
        ]);

        // Safety: require typing the site name to confirm
        if ($request->input('confirm') !== $site->name) {
            return response()->json([
                'message' => 'Confirmation failed. Type the exact site name to confirm.',
            ], 422);
        }

        $options = $request->input('options');
        $deleted = [];

        DB::transaction(function () use ($site, $options, &$deleted) {
            // Delete page versions FIRST (check constraint requires page_id or post_id)
            // Use withTrashed() so forceDelete doesn't leave orphaned versions
            if (!empty($options['pages']) || !empty($options['posts'])) {
                $pageIds = $site->pages()->withTrashed()->pluck('id');
                $postIds = $site->posts()->withTrashed()->pluck('id');

                if (!empty($options['pages']) && !empty($options['posts'])) {
                    \App\Models\PageVersion::whereIn('page_id', $pageIds)->orWhereIn('post_id', $postIds)->delete();
                } elseif (!empty($options['pages'])) {
                    \App\Models\PageVersion::whereIn('page_id', $pageIds)->delete();
                } elseif (!empty($options['posts'])) {
                    \App\Models\PageVersion::whereIn('post_id', $postIds)->delete();
                }
            }

            // Delete position overrides that reference pages/posts
            if (!empty($options['pages'])) {
                \App\Models\PositionOverride::whereIn('page_id', $site->pages()->withTrashed()->pluck('id'))->delete();
            }

            // Delete pages (and their blocks via cascade)
            if (!empty($options['pages'])) {
                $count = $site->pages()->count();
                $site->pages()->forceDelete();
                $deleted['pages'] = $count;
            }

            // Delete posts (and their blocks via cascade)
            if (!empty($options['posts'])) {
                $count = $site->posts()->count();
                $site->posts()->forceDelete();
                $deleted['posts'] = $count;
            }

            // Delete categories
            if (!empty($options['categories'])) {
                $count = $site->categories()->count();
                $site->categories()->delete();
                $deleted['categories'] = $count;
            }

            // Delete tags
            if (!empty($options['tags'])) {
                $count = $site->tags()->count();
                // Remove taggable pivots first
                foreach ($site->tags as $tag) {
                    $tag->posts()->detach();
                    $tag->delete();
                }
                $deleted['tags'] = $count;
            }

            // Delete assets
            if (!empty($options['assets'])) {
                $count = $site->assets()->count();
                foreach ($site->assets as $asset) {
                    try {
                        app(\App\Domain\Assets\Services\AssetService::class)->delete($asset);
                    } catch (\Throwable) {
                        // File deletion may fail due to permissions — delete DB record anyway
                        $asset->delete();
                    }
                }
                $deleted['assets'] = $count;
            }

            // Delete menus (and their items via cascade)
            if (!empty($options['menus'])) {
                $count = $site->menus()->count();
                $site->menus()->delete();
                $deleted['menus'] = $count;
            }

            // Clear deployments
            if (!empty($options['deployments'])) {
                $count = \App\Models\Deployment::where('site_id', $site->id)->count();
                \App\Models\Deployment::where('site_id', $site->id)->delete();
                $deleted['deployments'] = $count;

                // Try to clean published files (may fail due to open_basedir)
                try {
                    $publishPath = config('publishing.public_path');
                    if ($publishPath && is_dir($publishPath)) {
                        foreach (File::files($publishPath) as $f) File::delete($f->getPathname());
                        foreach (File::directories($publishPath) as $d) {
                            if (basename($d) !== 'sites') File::deleteDirectory($d);
                        }
                    }
                } catch (\Throwable) {
                    // File cleanup skipped — publish path not accessible from this PHP process
                }
            }

        });

        return response()->json([
            'data' => [
                'message' => 'Content reset complete.',
                'deleted' => $deleted,
                'remaining' => $this->getCounts($site),
            ],
        ]);
    }

    /**
     * Full factory reset — wipe EVERYTHING and start fresh.
     * Only the site record, tenant, user, and theme survive.
     */
    public function factoryReset(Request $request, Site $site): JsonResponse
    {
        $this->authorize('delete', $site);

        $request->validate([
            'confirm' => ['required', 'string'],
        ]);

        if ($request->input('confirm') !== 'FACTORY RESET ' . $site->name) {
            return response()->json([
                'message' => 'Type "FACTORY RESET ' . $site->name . '" to confirm.',
            ], 422);
        }

        DB::transaction(function () use ($site) {
            // Delete page versions FIRST (check constraint)
            // Use withTrashed() so forceDelete doesn't leave orphaned versions
            $pageIds = $site->pages()->withTrashed()->pluck('id');
            $postIds = $site->posts()->withTrashed()->pluck('id');
            \App\Models\PageVersion::whereIn('page_id', $pageIds)->orWhereIn('post_id', $postIds)->delete();

            // Delete position overrides (pageIds already includes trashed)
            \App\Models\PositionOverride::whereIn('page_id', $pageIds)->delete();

            // Delete everything
            $site->pages()->forceDelete();
            $site->posts()->forceDelete();
            $site->categories()->delete();
            foreach ($site->tags as $tag) {
                $tag->posts()->detach();
                $tag->delete();
            }
            foreach ($site->assets as $asset) {
                try {
                    app(\App\Domain\Assets\Services\AssetService::class)->delete($asset);
                } catch (\Throwable) {
                    $asset->delete();
                }
            }
            $site->menus()->delete();
            \App\Models\Deployment::where('site_id', $site->id)->delete();

            // Re-seed grid presets
            $seeder = app(\App\Domain\Grid\Services\GridPresetSeeder::class);
            // Delete existing grids first
            $site->grids()->where('is_preset', false)->delete();

            // Reset settings but keep auto_publish and homepage_id
            $settings = $site->settings ?? [];
            unset($settings['homepage_id'], $settings['blog_page_id']);
            $site->update(['settings' => $settings]);
        });

        return response()->json([
            'data' => [
                'message' => 'Factory reset complete. Site is now empty.',
                'remaining' => $this->getCounts($site),
            ],
        ]);
    }

    private function getCounts(Site $site): array
    {
        return [
            'pages' => $site->pages()->count(),
            'posts' => $site->posts()->count(),
            'categories' => $site->categories()->count(),
            'tags' => $site->tags()->count(),
            'assets' => $site->assets()->count(),
            'blocks' => DB::table('blocks')
                ->where(function ($q) use ($site) {
                    $q->whereIn('blockable_id', $site->pages()->pluck('id'))
                      ->orWhereIn('blockable_id', $site->posts()->pluck('id'));
                })->count(),
            'menus' => $site->menus()->count(),
            'deployments' => \App\Models\Deployment::where('site_id', $site->id)->count(),
        ];
    }
}
