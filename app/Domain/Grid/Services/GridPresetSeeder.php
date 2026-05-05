<?php

namespace App\Domain\Grid\Services;

use App\Models\Grid;
use App\Models\GridAssignment;
use App\Models\GridPosition;
use App\Models\Site;

class GridPresetSeeder
{
    /**
     * Seed all built-in grid presets for a site.
     */
    public function seed(Site $site): void
    {
        $presets = $this->getPresets();

        foreach ($presets as $preset) {
            $this->createPreset($site, $preset);
        }

        // Set classic-blog as default
        $defaultGrid = Grid::where('site_id', $site->id)->where('slug', 'full-width')->first();
        if ($defaultGrid) {
            GridAssignment::create([
                'site_id' => $site->id,
                'grid_id' => $defaultGrid->id,
                'assignable_type' => 'default',
                'assignable_id' => null,
                'priority' => 9999,
                'is_active' => true,
            ]);
        }
    }

    private function createPreset(Site $site, array $def): Grid
    {
        $grid = Grid::create([
            'site_id' => $site->id,
            'name' => $def['name'],
            'slug' => $def['slug'],
            'description' => $def['description'] ?? null,
            'col_tracks' => $def['col_tracks'],
            'row_tracks' => $def['row_tracks'],
            'areas' => $def['areas'],
            'gap_x' => $def['gap_x'] ?? '0px',
            'gap_y' => $def['gap_y'] ?? '0px',
            'container_width' => $def['container_width'] ?? '1200px',
            'is_preset' => true,
            'breakpoints_json' => $def['breakpoints'] ?? null,
        ]);

        foreach ($def['positions'] as $pos) {
            GridPosition::create([
                'grid_id' => $grid->id,
                'area_name' => $pos['area_name'],
                'label' => $pos['label'],
                'type' => $pos['type'],
                'config_json' => $pos['config'] ?? [],
                'scope' => $pos['scope'] ?? 'site',
                'is_overridable' => $pos['is_overridable'] ?? false,
                'mobile_order' => $pos['mobile_order'] ?? 0,
            ]);
        }

        return $grid;
    }

    private function getPresets(): array
    {
        return [
            [
                'name' => 'Full Width',
                'slug' => 'full-width',
                'description' => 'Header, navigation, full-width content, footer',
                'col_tracks' => '1fr',
                'row_tracks' => 'auto auto 1fr auto',
                'areas' => '"header" "nav" "main" "footer"',
                'gap_y' => '0px',
                'container_width' => '100%',
                'breakpoints' => null,
                'positions' => [
                    ['area_name' => 'header', 'label' => 'Header', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 1, 'config' => []],
                    ['area_name' => 'nav', 'label' => 'Navigation', 'type' => 'menu', 'scope' => 'site', 'mobile_order' => 2, 'config' => ['location' => 'header']],
                    ['area_name' => 'main', 'label' => 'Main Content', 'type' => 'canvas', 'scope' => 'page', 'is_overridable' => true, 'mobile_order' => 3, 'config' => ['placeholder' => 'Drag blocks here']],
                    ['area_name' => 'footer', 'label' => 'Footer', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 4, 'config' => []],
                ],
            ],
            [
                'name' => 'Classic Blog',
                'slug' => 'classic-blog',
                'description' => 'Header, nav, sidebar + main content, footer',
                'col_tracks' => '260px 1fr',
                'row_tracks' => 'auto auto 1fr auto',
                'areas' => '"header header" "nav nav" "sidebar main" "footer footer"',
                'gap_x' => '24px',
                'gap_y' => '0px',
                'container_width' => '1200px',
                'breakpoints' => [
                    'tablet' => ['col_tracks' => '1fr', 'areas' => '"header" "nav" "main" "sidebar" "footer"'],
                    'mobile' => ['col_tracks' => '1fr', 'areas' => '"header" "nav" "main" "sidebar" "footer"', 'gap_x' => '0px'],
                ],
                'positions' => [
                    ['area_name' => 'header', 'label' => 'Header', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 1],
                    ['area_name' => 'nav', 'label' => 'Navigation', 'type' => 'menu', 'scope' => 'site', 'mobile_order' => 2, 'config' => ['location' => 'header']],
                    ['area_name' => 'sidebar', 'label' => 'Sidebar', 'type' => 'widget', 'scope' => 'site', 'is_overridable' => true, 'mobile_order' => 5, 'config' => ['widgets' => [['type' => 'search'], ['type' => 'recent_posts', 'count' => 5], ['type' => 'category_tree', 'show_count' => true], ['type' => 'tag_cloud']], 'sticky' => true, 'sticky_offset' => 80]],
                    ['area_name' => 'main', 'label' => 'Main Content', 'type' => 'canvas', 'scope' => 'page', 'mobile_order' => 3, 'config' => ['placeholder' => 'Drag blocks here']],
                    ['area_name' => 'footer', 'label' => 'Footer', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 6],
                ],
            ],
            [
                'name' => 'Both Sidebars',
                'slug' => 'both-sidebars',
                'description' => 'Header, nav, left sidebar + main + right sidebar, footer',
                'col_tracks' => '220px 1fr 220px',
                'row_tracks' => 'auto auto 1fr auto',
                'areas' => '"header header header" "nav nav nav" "left main right" "footer footer footer"',
                'gap_x' => '24px',
                'container_width' => '1400px',
                'breakpoints' => [
                    'tablet' => ['col_tracks' => '1fr', 'areas' => '"header" "nav" "main" "left" "right" "footer"'],
                ],
                'positions' => [
                    ['area_name' => 'header', 'label' => 'Header', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 1],
                    ['area_name' => 'nav', 'label' => 'Navigation', 'type' => 'menu', 'scope' => 'site', 'mobile_order' => 2, 'config' => ['location' => 'header']],
                    ['area_name' => 'left', 'label' => 'Left Sidebar', 'type' => 'widget', 'scope' => 'site', 'is_overridable' => true, 'mobile_order' => 5],
                    ['area_name' => 'main', 'label' => 'Main Content', 'type' => 'canvas', 'scope' => 'page', 'mobile_order' => 3],
                    ['area_name' => 'right', 'label' => 'Right Sidebar', 'type' => 'widget', 'scope' => 'site', 'is_overridable' => true, 'mobile_order' => 6],
                    ['area_name' => 'footer', 'label' => 'Footer', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 7],
                ],
            ],
            [
                'name' => 'Magazine',
                'slug' => 'magazine',
                'description' => 'Header, nav, featured + trending + sidebar, archive + sidebar, footer',
                'col_tracks' => '1fr 300px',
                'row_tracks' => 'auto auto auto 1fr auto',
                'areas' => '"header header" "nav nav" "featured sidebar" "archive sidebar" "footer footer"',
                'gap_x' => '24px',
                'gap_y' => '16px',
                'container_width' => '1200px',
                'breakpoints' => [
                    'tablet' => ['col_tracks' => '1fr', 'areas' => '"header" "nav" "featured" "archive" "sidebar" "footer"'],
                ],
                'positions' => [
                    ['area_name' => 'header', 'label' => 'Header', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 1],
                    ['area_name' => 'nav', 'label' => 'Navigation', 'type' => 'menu', 'scope' => 'site', 'mobile_order' => 2, 'config' => ['location' => 'header']],
                    ['area_name' => 'featured', 'label' => 'Featured Posts', 'type' => 'query', 'scope' => 'grid', 'mobile_order' => 3, 'config' => ['count' => 3, 'order_by' => 'date', 'layout' => 'featured', 'card_style' => 'overlay']],
                    ['area_name' => 'sidebar', 'label' => 'Sidebar', 'type' => 'widget', 'scope' => 'site', 'is_overridable' => true, 'mobile_order' => 5, 'config' => ['widgets' => [['type' => 'search'], ['type' => 'recent_posts', 'count' => 5], ['type' => 'category_tree', 'show_count' => true]], 'sticky' => true]],
                    ['area_name' => 'archive', 'label' => 'Archive', 'type' => 'canvas', 'scope' => 'page', 'mobile_order' => 4],
                    ['area_name' => 'footer', 'label' => 'Footer', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 6],
                ],
            ],
            [
                'name' => 'Landing Page',
                'slug' => 'landing',
                'description' => 'Header, hero section, main content, footer',
                'col_tracks' => '1fr',
                'row_tracks' => 'auto auto 1fr auto',
                'areas' => '"header" "hero" "main" "footer"',
                'container_width' => '100%',
                'positions' => [
                    ['area_name' => 'header', 'label' => 'Header', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 1],
                    ['area_name' => 'hero', 'label' => 'Hero', 'type' => 'canvas', 'scope' => 'page', 'is_overridable' => true, 'mobile_order' => 2],
                    ['area_name' => 'main', 'label' => 'Main Content', 'type' => 'canvas', 'scope' => 'page', 'mobile_order' => 3],
                    ['area_name' => 'footer', 'label' => 'Footer', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 4],
                ],
            ],
            [
                'name' => 'Single Post',
                'slug' => 'single-post',
                'description' => 'Header, nav, breadcrumb, post header, main + sidebar, related posts, footer',
                'col_tracks' => '1fr 300px',
                'row_tracks' => 'auto auto auto auto 1fr auto auto',
                'areas' => '"header header" "nav nav" "breadcrumb breadcrumb" "post-header post-header" "main sidebar" "related related" "footer footer"',
                'gap_x' => '24px',
                'container_width' => '1200px',
                'breakpoints' => [
                    'tablet' => ['col_tracks' => '1fr', 'areas' => '"header" "nav" "breadcrumb" "post-header" "main" "sidebar" "related" "footer"'],
                ],
                'positions' => [
                    ['area_name' => 'header', 'label' => 'Header', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 1],
                    ['area_name' => 'nav', 'label' => 'Navigation', 'type' => 'menu', 'scope' => 'site', 'mobile_order' => 2, 'config' => ['location' => 'header']],
                    ['area_name' => 'breadcrumb', 'label' => 'Breadcrumb', 'type' => 'static', 'scope' => 'site', 'mobile_order' => 3, 'config' => ['partial' => 'breadcrumb']],
                    ['area_name' => 'post-header', 'label' => 'Post Header', 'type' => 'canvas', 'scope' => 'page', 'mobile_order' => 4],
                    ['area_name' => 'main', 'label' => 'Post Content', 'type' => 'canvas', 'scope' => 'page', 'mobile_order' => 5],
                    ['area_name' => 'sidebar', 'label' => 'Sidebar', 'type' => 'widget', 'scope' => 'site', 'is_overridable' => true, 'mobile_order' => 7, 'config' => ['widgets' => [['type' => 'search'], ['type' => 'recent_posts', 'count' => 5], ['type' => 'tag_cloud']], 'sticky' => true]],
                    ['area_name' => 'related', 'label' => 'Related Posts', 'type' => 'query', 'scope' => 'grid', 'mobile_order' => 6, 'config' => ['count' => 3, 'order_by' => 'date', 'layout' => 'grid', 'context_aware' => true, 'exclude_current' => true]],
                    ['area_name' => 'footer', 'label' => 'Footer', 'type' => 'fixed', 'scope' => 'site', 'mobile_order' => 8],
                ],
            ],
        ];
    }
}
