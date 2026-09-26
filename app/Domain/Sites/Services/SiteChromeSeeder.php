<?php

namespace App\Domain\Sites\Services;

use App\Domain\Blocks\Services\BlockService;
use App\Models\GlobalSection;
use App\Models\Site;

/**
 * Grid areas as blocks — stage 3: a brand-new site gets a working header
 * and footer made of blocks instead of empty grid areas.
 *
 *  - "Site header": logo/name + the header-location menu
 *  - "Site footer": logo/name + tagline, footer-location menu, social links,
 *    then copyright + back to top
 *
 * Both are published Global Sections; every preset grid points its header
 * and footer areas at them, and the separate "nav" row is dropped (the menu
 * lives in the header section). Menus are referenced by LOCATION, so the
 * starter template's menus (or ones made later) show up without ids.
 */
class SiteChromeSeeder
{
    public function __construct(private BlockService $blocks) {}

    /** @return array{header: string, footer: string} */
    public function createSections(Site $site): array
    {
        $header = $this->section($site, 'Site header', [
            $this->sectionNode(['padding_top' => '1rem', 'padding_bottom' => '1rem'], [
                $this->row('1/3+2/3', [
                    [$this->module('site-identity', ['show' => 'auto', 'size' => 'md', 'linkHome' => true, 'align' => 'left'])],
                    [$this->module('menu', ['source' => 'system', 'location' => 'header', 'style' => 'horizontal', 'mobileBreakpoint' => 768])],
                ]),
            ]),
        ]);

        $footer = $this->section($site, 'Site footer', [
            $this->sectionNode(['padding_top' => '3rem', 'padding_bottom' => '2rem'], [
                $this->row('1/3+1/3+1/3', [
                    [$this->module('site-identity', ['show' => 'auto', 'size' => 'sm', 'showTagline' => true, 'linkHome' => true, 'align' => 'left'])],
                    [$this->module('menu', ['source' => 'system', 'location' => 'footer', 'style' => 'vertical'])],
                    [$this->module('social-links', ['links' => [], 'style' => 'circle', 'size' => 'md', 'align' => 'left'])],
                ]),
                $this->row('1/2+1/2', [
                    [$this->module('copyright', ['text' => '© {year} {site}. All rights reserved.', 'align' => 'left'])],
                    [$this->module('back-to-top', ['label' => 'Back to top', 'style' => 'link', 'align' => 'right'])],
                ]),
            ]),
        ]);

        return ['header' => $header->id, 'footer' => $footer->id];
    }

    /**
     * Rewrite preset grid definitions for a blocks site: header/footer areas
     * become section areas, the "nav" row disappears (desktop + breakpoints).
     *
     * @param array<int, array> $presets GridPresetSeeder::getPresets() shape
     * @param array{header: string, footer: string} $sections
     */
    public static function applyToPresets(array $presets, array $sections): array
    {
        return array_map(function (array $def) use ($sections) {
            $rows = self::rows($def['areas']);
            $navIdx = array_keys(array_filter($rows, fn ($r) => self::isOnly($r, 'nav')));
            if ($navIdx) {
                $tracks = preg_split('/\s+/', trim($def['row_tracks']));
                foreach ($navIdx as $i) {
                    unset($rows[$i], $tracks[$i]);
                }
                $def['areas'] = self::join($rows);
                $def['row_tracks'] = implode(' ', $tracks);
                foreach (($def['breakpoints'] ?? []) as $bp => $cfg) {
                    if (!empty($cfg['areas'])) {
                        $def['breakpoints'][$bp]['areas'] = self::join(array_filter(self::rows($cfg['areas']), fn ($r) => !self::isOnly($r, 'nav')));
                    }
                }
            }

            $def['positions'] = array_values(array_filter(array_map(function (array $pos) use ($sections) {
                return match ($pos['area_name']) {
                    'nav' => null,
                    'header', 'footer' => array_merge($pos, ['type' => 'section', 'config' => ['section_id' => $sections[$pos['area_name']]]]),
                    default => $pos,
                };
            }, $def['positions'])));

            return $def;
        }, $presets);
    }

    private function section(Site $site, string $name, array $tree): GlobalSection
    {
        $section = GlobalSection::create([
            'site_id' => $site->id,
            'name' => $name,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->blocks->syncBlocks($section, $tree);

        return $section;
    }

    private function sectionNode(array $data, array $rows): array
    {
        return ['type' => 'section', 'level' => 'section', 'order' => 0, 'data' => $data, 'children' => $rows];
    }

    /** @param array<int, array<int, array>> $columns modules per column */
    private function row(string $layout, array $columns): array
    {
        return [
            'type' => 'row', 'level' => 'row', 'order' => 0, 'data' => ['layout' => $layout],
            'children' => array_map(fn ($modules, $i) => [
                'type' => 'column', 'level' => 'column', 'order' => $i, 'data' => [],
                'children' => array_values(array_map(fn ($m, $j) => $m + ['order' => $j], $modules, array_keys($modules))),
            ], $columns, array_keys($columns)),
        ];
    }

    private function module(string $type, array $data): array
    {
        return ['type' => $type, 'level' => 'module', 'data' => $data];
    }

    /** @return array<int, string> */
    private static function rows(string $areas): array
    {
        preg_match_all('/"([^"]*)"/', $areas, $m);

        return $m[1];
    }

    private static function isOnly(string $row, string $name): bool
    {
        $cells = preg_split('/\s+/', trim($row));

        return $cells !== [] && count(array_unique($cells)) === 1 && $cells[0] === $name;
    }

    private static function join(array $rows): string
    {
        return implode(' ', array_map(fn ($r) => '"' . $r . '"', array_values($rows)));
    }
}
