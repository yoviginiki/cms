<?php

namespace App\Services\PageWizard;

/**
 * Semantic validation of a Page Wizard manifest — the rules the enforced JSON
 * schema can't express (per-kind required fields, ranges, URL safety). Returns
 * human-readable errors ([] = valid) for the SchemaRepairLoop; also used to
 * clean input before compiling.
 */
class PageManifestValidator
{
    /** @return array<int, string> */
    public function validate(mixed $manifest): array
    {
        $errors = [];

        if (!is_array($manifest)) {
            return ['Output was not an object.'];
        }
        if (!is_string($manifest['page_title'] ?? null) || trim($manifest['page_title']) === '') {
            $errors[] = 'page_title is required.';
        }

        $blocks = $manifest['blocks'] ?? null;
        if (!is_array($blocks) || $blocks === []) {
            return array_merge($errors, ['blocks must contain at least one block.']);
        }

        $max = (int) config('cms.page_wizard.max_blocks', 40);
        if (count($blocks) > $max) {
            $errors[] = "Too many blocks (max {$max}).";
        }

        foreach (array_values($blocks) as $i => $block) {
            $errors = array_merge($errors, $this->validateBlock($block, $i));
        }

        return $errors;
    }

    /**
     * Coerce a manifest into a valid one by dropping any block that fails
     * validation (used by the deterministic DOM importer, whose output is
     * mostly valid but can contain the odd malformed block). Guarantees a
     * page_title; returns blocks that individually pass.
     */
    public function sanitize(mixed $manifest): array
    {
        $manifest = is_array($manifest) ? $manifest : [];
        $title = (is_string($manifest['page_title'] ?? null) && trim($manifest['page_title']) !== '')
            ? mb_substr(trim($manifest['page_title']), 0, 120) : 'Imported page';

        $blocks = [];
        foreach ($manifest['blocks'] ?? [] as $block) {
            if (is_array($block) && $this->validateBlock($block, count($blocks)) === []) {
                $blocks[] = $block;
            }
        }

        return [
            'page_title' => $title,
            'design_read' => is_string($manifest['design_read'] ?? null) ? $manifest['design_read'] : '',
            'blocks' => $blocks,
        ];
    }

    /** @return array<int, string> */
    private function validateBlock(mixed $block, int $i): array
    {
        $at = "blocks[{$i}]";
        if (!is_array($block)) {
            return ["{$at} is not an object."];
        }
        $kind = $block['kind'] ?? null;
        if (!in_array($kind, PageManifestSchema::KINDS, true)) {
            return ["{$at}.kind '{$kind}' is not a valid block kind."];
        }

        $e = [];
        $need = fn (string $field, string $label) => (!is_string($block[$field] ?? null) || trim($block[$field]) === '')
            ? ["{$at}: {$kind} needs a {$label}."] : [];

        $e = match ($kind) {
            'hero', 'cta' => $need('title', 'title'),
            'heading', 'button' => $need('text', $kind === 'button' ? 'label' : 'text'),
            'text' => $need('body', 'body'),
            'image' => $this->needUrl($block, $at),
            'gallery' => (!is_array($block['images'] ?? null) || $block['images'] === [])
                ? ["{$at}: gallery needs at least one image."] : $this->checkUrls($block['images'], $at),
            'columns' => $this->validateColumns($block['columns'] ?? null, $at),
            default => [],
        };

        // URL-bearing optional fields must be safe when present.
        foreach (['url', 'cta_url'] as $f) {
            if (isset($block[$f]) && is_string($block[$f]) && $block[$f] !== '' && !$this->safeUrl($block[$f])) {
                $e[] = "{$at}.{$f} must be an http(s) URL.";
            }
        }

        return $e;
    }

    private function validateColumns(mixed $columns, string $at): array
    {
        if (!is_array($columns) || count($columns) < 2 || count($columns) > 3) {
            return ["{$at}: columns needs 2 or 3 cells."];
        }
        $e = [];
        foreach ($columns as $j => $cell) {
            if (!is_array($cell) || (($cell['heading'] ?? '') === '' && ($cell['body'] ?? '') === '' && ($cell['image'] ?? '') === '')) {
                $e[] = "{$at}.columns[{$j}] is empty.";
            }
            if (isset($cell['image']) && is_string($cell['image']) && $cell['image'] !== '' && !$this->safeUrl($cell['image'])) {
                $e[] = "{$at}.columns[{$j}].image must be an http(s) URL.";
            }
        }

        return $e;
    }

    private function needUrl(array $block, string $at): array
    {
        return $this->safeUrl($block['url'] ?? '') ? [] : ["{$at}: image needs a valid http(s) url."];
    }

    private function checkUrls(array $urls, string $at): array
    {
        foreach ($urls as $u) {
            if (!$this->safeUrl((string) $u)) {
                return ["{$at}: every gallery image must be an http(s) URL."];
            }
        }

        return [];
    }

    public function safeUrl(string $url): bool
    {
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
