<?php

namespace App\Services\SiteWizard;

use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;

/**
 * Builds the site's header navigation from the nav anchors read off the entry
 * page, bound to the pages the wizard created (pattern:
 * WordPressImporter::importMenus). Nav links that match a built page become
 * page items; external links stay URL items; unmatched internal links are
 * dropped. Design exports often ship no <nav> at all — then the menu falls
 * back to the created pages themselves (home first).
 */
class SiteMenuBuilder
{
    public function build(SiteWizardSession $session, Site $site): ?Menu
    {
        $items = $this->itemsFromNav($session, $site);
        if ($items === []) {
            $items = $this->itemsFromPages($session);
        }
        if ($items === []) {
            return null;
        }

        $menu = Menu::create([
            'site_id' => $site->id,
            'name' => 'Main menu',
            'slug' => 'main',
            // A wizard-built site is brand new, but keep the guard anyway.
            'location' => Menu::where('site_id', $site->id)->where('location', 'header')->exists()
                ? null : 'header',
        ]);

        foreach ($items as $order => $item) {
            MenuItem::create($item + ['menu_id' => $menu->id, 'sort_order' => $order]);
        }

        return $menu;
    }

    /**
     * 'into' mode: hang the imported pages as a SUBMENU under one new parent
     * item in the target site's existing header menu (created if the site has
     * none). The parent links to the imported home page so the label itself
     * is clickable.
     *
     * @return array{menu: Menu, parent: MenuItem}|null
     */
    public function buildInto(SiteWizardSession $session, Site $site): ?array
    {
        $items = $this->itemsFromNav($session, $site);
        if ($items === []) {
            $items = $this->itemsFromPages($session);
        }
        if ($items === []) {
            return null;
        }

        $menu = Menu::where('site_id', $site->id)->where('location', 'header')->first()
            ?? Menu::firstOrCreate(
                ['site_id' => $site->id, 'slug' => 'main'],
                ['name' => 'Main menu', 'location' => 'header'],
            );

        $label = trim((string) ($session->options['menu_label'] ?? '')) ?: ($session->title ?: 'Imported');
        $home = collect($session->sources ?? [])->first(fn ($s) => ($s['is_home'] ?? false) && !empty($s['page_id']));
        $nextOrder = (int) MenuItem::where('menu_id', $menu->id)->whereNull('parent_id')->max('sort_order') + 1;

        $parent = MenuItem::create([
            'menu_id' => $menu->id,
            'label' => mb_substr($label, 0, 60),
            'page_id' => $home['page_id'] ?? null,
            'target' => '_self',
            'sort_order' => $nextOrder,
        ]);

        foreach ($items as $order => $item) {
            // The parent already IS the home link — don't repeat it as a child.
            if (($item['page_id'] ?? null) !== null && $item['page_id'] === ($home['page_id'] ?? null)) {
                continue;
            }
            MenuItem::create($item + ['menu_id' => $menu->id, 'parent_id' => $parent->id, 'sort_order' => $order]);
        }

        return ['menu' => $menu, 'parent' => $parent];
    }

    /** @return array<int, array> menu item attribute sets, in nav order */
    private function itemsFromNav(SiteWizardSession $session, Site $site): array
    {
        $pagesByRef = $this->pagesByRef($session);
        $items = [];
        $seen = [];

        foreach ($session->nav ?? [] as $nav) {
            $label = trim((string) ($nav['label'] ?? ''));
            $href = (string) ($nav['href'] ?? '');
            if ($label === '' || $href === '' || count($items) >= 10) {
                continue;
            }

            $pageId = $this->matchPage($session, $pagesByRef, $href);
            if ($pageId !== null) {
                if (in_array($pageId, $seen, true)) {
                    continue;
                }
                $seen[] = $pageId;
                $items[] = ['label' => mb_substr($label, 0, 60), 'page_id' => $pageId, 'target' => '_self'];
                continue;
            }

            // External links survive as URL items; unmatched internal ones are dropped
            // (their target page wasn't built, so the link would 404).
            if ($this->isExternal($session, $href)) {
                $items[] = ['label' => mb_substr($label, 0, 60), 'url' => $href, 'target' => '_blank'];
            }
        }

        return $items;
    }

    /** @return array<int, array> */
    private function itemsFromPages(SiteWizardSession $session): array
    {
        $sources = collect($session->sources ?? [])
            ->filter(fn ($s) => ($s['status'] ?? '') === 'done' && !empty($s['page_id']))
            ->sortBy(fn ($s) => [($s['is_home'] ?? false) ? 0 : 1, (string) ($s['title'] ?? '')])
            ->take(7);

        return $sources->map(fn ($s) => [
            'label' => mb_substr((string) ($s['title'] ?? ucwords(str_replace('-', ' ', $s['slug'] ?? 'Page'))), 0, 60),
            'page_id' => $s['page_id'],
            'target' => '_self',
        ])->values()->all();
    }

    /** @return array<string, string> normalized ref → page_id for every built source */
    private function pagesByRef(SiteWizardSession $session): array
    {
        $map = [];
        foreach ($session->sources ?? [] as $source) {
            if (($source['status'] ?? '') === 'done' && !empty($source['page_id'])) {
                $map[$this->normalizeRef($session, (string) $source['ref'])] = $source['page_id'];
            }
        }

        return $map;
    }

    private function matchPage(SiteWizardSession $session, array $pagesByRef, string $href): ?string
    {
        $key = $this->normalizeRef($session, $href);
        $pageId = $pagesByRef[$key] ?? null;

        return $pageId !== null && Page::whereKey($pageId)->exists() ? $pageId : null;
    }

    /**
     * Comparable key for a nav href vs a source ref. URL mode: the URL path.
     * ZIP mode: sources are relative file paths and nav hrefs are loopback
     * URLs — both reduce to the file path.
     */
    private function normalizeRef(SiteWizardSession $session, string $ref): string
    {
        if ($session->source === 'zip') {
            $path = str_contains($ref, '://') ? (string) parse_url($ref, PHP_URL_PATH) : $ref;
            $path = ltrim(rawurldecode($path), '/');
            if ($path === '' || str_ends_with($path, '/')) {
                $path .= 'index.html';
            }

            return strtolower($path);
        }

        $path = str_contains($ref, '://') ? (string) (parse_url($ref, PHP_URL_PATH) ?: '/') : $ref;
        $path = '/' . ltrim($path, '/');

        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    private function isExternal(SiteWizardSession $session, string $href): bool
    {
        $host = (string) parse_url($href, PHP_URL_HOST);
        if ($host === '' || in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return false;
        }
        if ($session->source === 'url') {
            $entryHost = (string) parse_url((string) $session->reference_url, PHP_URL_HOST);

            return strcasecmp($host, $entryHost) !== 0;
        }

        return true; // zip mode: any real host is external
    }
}
