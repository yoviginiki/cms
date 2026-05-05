<?php

namespace App\Domain\Menus\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MenuService
{
    public function createMenu(array $data, Site $site): Menu
    {
        $data['site_id'] = $site->id;
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        return Menu::create($data);
    }

    public function updateMenu(Menu $menu, array $data): Menu
    {
        $menu->update($data);
        return $menu->fresh();
    }

    public function deleteMenu(Menu $menu): void
    {
        $menu->delete();
    }

    /**
     * Sync the full menu item tree from a nested JSON structure.
     */
    public function syncItems(Menu $menu, array $items): Menu
    {
        return DB::transaction(function () use ($menu, $items) {
            // Delete all existing items
            MenuItem::where('menu_id', $menu->id)->delete();

            // Insert the new tree
            $this->insertItems($menu, $items);

            return $menu->load('items.children');
        });
    }

    /**
     * Get menu tree as nested array.
     */
    public function getTree(Menu $menu): array
    {
        $items = $menu->items()
            ->with(['page:id,title,slug', 'post:id,title,slug', 'category:id,name,slug'])
            ->orderBy('sort_order')
            ->get();

        return $this->buildTree($items);
    }

    private function insertItems(Menu $menu, array $items, ?string $parentId = null): void
    {
        foreach ($items as $item) {
            $children = $item['children'] ?? [];
            unset($item['children'], $item['id']);

            $menuItem = MenuItem::create([
                'menu_id' => $menu->id,
                'parent_id' => $parentId,
                'label' => $item['label'] ?? 'Untitled',
                'url' => $item['url'] ?? null,
                'page_id' => $item['page_id'] ?? null,
                'post_id' => $item['post_id'] ?? null,
                'category_id' => $item['category_id'] ?? null,
                'target' => $item['target'] ?? '_self',
                'css_class' => $item['css_class'] ?? null,
                'icon' => $item['icon'] ?? null,
                'sort_order' => $item['sort_order'] ?? 0,
            ]);

            if (!empty($children)) {
                $this->insertItems($menu, $children, $menuItem->id);
            }
        }
    }

    private function buildTree($items, ?string $parentId = null): array
    {
        $tree = [];

        foreach ($items->where('parent_id', $parentId) as $item) {
            $node = $item->toArray();
            $node['children'] = $this->buildTree($items, $item->id);
            $tree[] = $node;
        }

        return $tree;
    }
}
