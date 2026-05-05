<?php

namespace App\Domain\Menus\Services;

use App\Models\Menu;
use App\Models\Site;

class MenuRenderer
{
    public function render(?Menu $menu, Site $site, string $ariaLabel = 'Main navigation'): string
    {
        if (!$menu) return '';

        $menu->load(['rootItems.children.children', 'rootItems.page', 'rootItems.post', 'rootItems.category']);
        $baseUrl = '';

        $items = $menu->rootItems;
        if ($items->isEmpty()) return '';

        $siteName = e($site->name);

        $html = '<nav class="site-nav" aria-label="' . e($ariaLabel) . '">' . "\n";
        $html .= '  <div class="nav-inner">' . "\n";

        // Logo / site name
        $html .= '    <a href="/" class="nav-logo">' . $siteName . '</a>' . "\n";

        // Hamburger button (mobile)
        $html .= '    <button class="nav-toggle" aria-label="Toggle menu" aria-expanded="false" onclick="this.setAttribute(\'aria-expanded\', this.getAttribute(\'aria-expanded\')===\'true\'?\'false\':\'true\');this.closest(\'.site-nav\').classList.toggle(\'nav-open\')">' . "\n";
        $html .= '      <span class="nav-toggle-line"></span>' . "\n";
        $html .= '      <span class="nav-toggle-line"></span>' . "\n";
        $html .= '      <span class="nav-toggle-line"></span>' . "\n";
        $html .= '    </button>' . "\n";

        // Menu items
        $html .= '    <ul class="nav-menu">' . "\n";
        foreach ($items as $item) {
            $html .= $this->renderItem($item, $baseUrl, 3);
        }
        $html .= '    </ul>' . "\n";

        $html .= '  </div>' . "\n";
        $html .= '</nav>' . "\n";

        return $html;
    }

    public function renderByLocation(Site $site, string $location): string
    {
        $menu = $site->menus()->where('location', $location)->first();
        $label = match ($location) {
            'header' => 'Main navigation',
            'footer' => 'Footer navigation',
            'sidebar' => 'Sidebar navigation',
            default => ucfirst($location) . ' navigation',
        };

        return $this->render($menu, $site, $label);
    }

    private function renderItem($item, string $baseUrl, int $indent): string
    {
        $pad = str_repeat('  ', $indent);
        $url = e($item->resolveUrl($baseUrl));
        $label = e($item->label);
        $target = $item->target !== '_self' ? ' target="' . e($item->target) . '" rel="noopener"' : '';
        $children = $item->children ?? collect();
        $hasChildren = $children->isNotEmpty();

        $liClass = $hasChildren ? ' class="has-children"' : '';
        $html = "{$pad}<li{$liClass}>\n";
        $html .= "{$pad}  <a href=\"{$url}\"{$target}>{$label}</a>\n";

        if ($hasChildren) {
            $html .= "{$pad}  <ul class=\"nav-submenu\">\n";
            foreach ($children as $child) {
                $html .= $this->renderItem($child, $baseUrl, $indent + 2);
            }
            $html .= "{$pad}  </ul>\n";
        }

        $html .= "{$pad}</li>\n";
        return $html;
    }
}
