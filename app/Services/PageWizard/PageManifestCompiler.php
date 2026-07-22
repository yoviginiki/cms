<?php

namespace App\Services\PageWizard;

use App\Domain\Blocks\Services\BlockRegistry;
use Illuminate\Support\Str;

/**
 * Compiles a validated Page Wizard manifest into a real block tree
 * (section → row → column → module) that BlockService::syncBlocks accepts.
 * Every manifest block becomes one section, so the page is cleanly structured
 * and immediately editable with the normal builder. Each emitted module is
 * checked against the BlockRegistry; anything that somehow doesn't fit is
 * dropped rather than corrupting the tree.
 */
class PageManifestCompiler
{
    private int $order = 0;

    public function __construct(private BlockRegistry $registry)
    {
    }

    /** @return array<int, array> block tree for syncBlocks */
    public function compile(array $manifest): array
    {
        $this->order = 0;
        $sections = [];

        // Design fidelity: manifest blocks may carry the ORIGINAL page's
        // effective background (_bg) and text color (_fg), read off the real
        // DOM by the deterministic importer. Consecutive blocks sharing a
        // background merge into ONE section painted that color — rebuilding
        // the source's light/dark section rhythm. Blocks without _bg keep the
        // legacy one-section-per-block behavior (AI manifests, older runs).
        $runRows = [];
        $runBg = null;

        $flush = function () use (&$sections, &$runRows, &$runBg) {
            if ($runRows !== []) {
                $sections[] = $this->section($runRows, $runBg);
            }
            $runRows = [];
            $runBg = null;
        };

        foreach ($manifest['blocks'] ?? [] as $block) {
            $rows = $this->rowsFor($block);
            if ($rows === []) {
                continue;
            }
            $bg = $this->safeHex($block['_bg'] ?? '');

            if ($bg === null) {
                $flush();
                $sections[] = $this->section($rows, null);
                continue;
            }
            if ($runRows !== [] && $bg !== $runBg) {
                $flush();
            }
            $runBg = $bg;
            $runRows = array_merge($runRows, $rows);
        }
        $flush();

        return $sections;
    }

    /** @return array<int, array> the row nodes one manifest block contributes */
    private function rowsFor(array $block): array
    {
        $kind = $block['kind'] ?? '';
        $align = in_array($block['align'] ?? '', ['left', 'center', 'right'], true) ? $block['align'] : null;
        $fg = $this->safeHex($block['_fg'] ?? '');

        // kind=columns → one N-col row, N columns.
        if ($kind === 'columns') {
            $cells = array_slice($block['columns'] ?? [], 0, 3);
            $layout = count($cells) >= 3 ? '1/3+1/3+1/3' : '1/2+1/2';
            $columns = [];
            foreach ($cells as $cell) {
                $modules = [];
                if (($cell['image'] ?? '') !== '') {
                    $modules[] = $this->module('image', ['url' => $cell['image'], 'size' => 'large', 'alt' => $cell['heading'] ?? '']);
                }
                if (($cell['heading'] ?? '') !== '') {
                    $modules[] = $this->module('heading', ['text' => $this->clean($cell['heading'], 255), 'level' => 'h3']
                        + ($align ? ['textAlign' => $align] : []) + ($fg ? ['color' => $fg] : []));
                }
                if (($cell['body'] ?? '') !== '') {
                    $modules[] = $this->module('text', ['content' => $this->html($cell['body'])]
                        + ($align ? ['textAlign' => $align] : []) + ($fg ? ['color' => $fg] : []));
                }
                $columns[] = $this->column(array_filter($modules));
            }

            return [$this->row($layout, $columns)];
        }

        $modules = $this->modulesFor($kind, $block, $align, $fg);
        if ($modules === []) {
            return [];
        }

        return [$this->row('1', [$this->column($modules)])];
    }

    /** @return array<int, array> */
    private function modulesFor(string $kind, array $block, ?string $align, ?string $fg = null): array
    {
        switch ($kind) {
            case 'hero':
                $data = ['title' => $this->clean($block['title'] ?? '', 255), 'bg_type' => 'none', 'headlineTag' => 'h1'];
                if (($block['subtitle'] ?? '') !== '') $data['subtitle'] = $this->clean($block['subtitle'], 500);
                if (($block['cta_text'] ?? '') !== '') $data['ctaText'] = $this->clean($block['cta_text'], 60);
                if ($this->safe($block['cta_url'] ?? '')) $data['ctaUrl'] = $block['cta_url'];
                return [$this->module('hero', $data)];

            case 'heading':
                $level = in_array($block['level'] ?? '', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $block['level'] : 'h2';
                return [$this->module('heading', ['text' => $this->clean($block['text'] ?? '', 255), 'level' => $level]
                    + ($align ? ['textAlign' => $align] : []) + ($fg ? ['color' => $fg] : []))];

            case 'text':
                return [$this->module('text', ['content' => $this->html($block['body'] ?? '')]
                    + ($align ? ['textAlign' => $align] : []) + ($fg ? ['color' => $fg] : []))];

            case 'image':
                return $this->safe($block['url'] ?? '')
                    ? [$this->module('image', ['url' => $block['url'], 'alt' => $this->clean($block['alt'] ?? '', 255), 'size' => 'large'])]
                    : [];

            case 'gallery':
                $images = array_values(array_filter((array) ($block['images'] ?? []), fn ($u) => $this->safe((string) $u)));
                return $images === [] ? [] : [$this->module('gallery', ['images' => $images, 'columns' => min(3, max(1, count($images))), 'layout' => 'grid'])];

            case 'button':
                $data = ['text' => $this->clean($block['text'] ?? '', 60), 'style' => 'primary'];
                if ($this->safe($block['url'] ?? '')) $data['url'] = $block['url'];
                return [$this->module('button', $data)];

            case 'cta':
                $modules = [$this->module('heading', ['text' => $this->clean($block['title'] ?? '', 255), 'level' => 'h2', 'textAlign' => 'center'])];
                if (($block['body'] ?? '') !== '') {
                    $modules[] = $this->module('text', ['content' => $this->html($block['body']), 'textAlign' => 'center']);
                }
                if (($block['cta_text'] ?? '') !== '') {
                    $btn = ['text' => $this->clean($block['cta_text'], 60), 'style' => 'primary'];
                    if ($this->safe($block['cta_url'] ?? '')) $btn['url'] = $block['cta_url'];
                    $modules[] = $this->module('button', $btn);
                }
                return $modules;

            case 'divider':
                return [$this->module('divider', [])];

            case 'spacer':
                return [$this->module('spacer', ['height' => '48px'])];

            default:
                return [];
        }
    }

    // ── node builders ──

    /** @param array<int, array> $rows  @param ?string $bg validated #rrggbb section background */
    private function section(array $rows, ?string $bg = null): array
    {
        $data = ['padding_top' => '48px', 'padding_bottom' => '48px', 'max_width' => '1200px'];
        if ($bg !== null) {
            $data['bg_type'] = 'color';
            $data['bg_color'] = $bg;
        }

        return [
            'id' => (string) Str::uuid(), 'type' => 'section', 'level' => 'section', 'order' => $this->order++,
            'data' => $data,
            'children' => $this->reorder($rows),
        ];
    }

    private function row(string $layout, array $columns): array
    {
        return [
            'id' => (string) Str::uuid(), 'type' => 'row', 'level' => 'row', 'order' => 0,
            'data' => ['layout' => $layout, 'gap' => '24px'],
            'children' => $this->reorder(array_values($columns)),
        ];
    }

    private function column(array $modules): array
    {
        return [
            'id' => (string) Str::uuid(), 'type' => 'column', 'level' => 'column', 'order' => 0,
            'data' => [],
            'children' => $this->reorder(array_values(array_filter($modules))),
        ];
    }

    private function module(string $type, array $data): ?array
    {
        if (!$this->registry->has($type)) {
            return null;
        }

        return [
            'id' => (string) Str::uuid(), 'type' => $type, 'level' => 'module', 'order' => 0,
            'data' => $data,
            'children' => [],
        ];
    }

    /** Assign sibling order by array position (deterministic). */
    private function reorder(array $nodes): array
    {
        return array_values(array_map(function ($node, $i) {
            $node['order'] = $i;
            return $node;
        }, $nodes, array_keys($nodes)));
    }

    private function clean(string $s, int $max): string
    {
        return mb_substr(trim(strip_tags($s)), 0, $max);
    }

    /** Wrap AI plain text as a safe paragraph (render-time HTMLPurifier is the backstop). */
    private function html(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        // Preserve paragraph breaks, escape the rest.
        $paras = preg_split('/\n{2,}/', $s) ?: [$s];

        return implode('', array_map(fn ($p) => '<p>' . e(trim(preg_replace('/\s+/', ' ', $p))) . '</p>', array_filter($paras)));
    }

    /** Validate an extractor-supplied color; anything but #rrggbb is discarded. */
    private function safeHex(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1
            ? strtolower($value)
            : null;
    }

    private function safe(mixed $url): bool
    {
        if (!is_string($url) || $url === '') {
            return false;
        }
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
