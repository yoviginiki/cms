<?php

namespace App\Domain\Library\Services;

use App\Domain\Blocks\Services\BlockRegistry;
use App\Domain\Publishing\Services\SanitizationService;
use App\Models\Block;
use RuntimeException;

/**
 * Validates + sanitizes a Library item's block tree on IMPORT (arbitrary,
 * untrusted JSON). The publish pipeline already sanitizes every block at build
 * time, but library items are also previewed in the admin (and system items are
 * shared across tenants), so imports are cleaned defensively here too:
 *
 *  - shape: an array of block nodes, each with a known `type`
 *  - bounds: capped node count + nesting depth (zip-bomb / DoS guard)
 *  - per-node `data` run through SanitizationService (strips scripts, event
 *    handlers, javascript: URLs; keeps the block's allowed rich HTML)
 *  - unknown block types are rejected (fail loud — a partial import is worse)
 *  - `style` is left as-is: BlockStyle's own strict sanitizers clean it at render
 */
class LibraryItemSanitizer
{
    private const MAX_NODES = 400;
    private const MAX_DEPTH = 12;

    private int $count = 0;

    public function __construct(
        private BlockRegistry $registry,
        private SanitizationService $sanitizer,
    ) {}

    /**
     * @param array<int,mixed> $blocks
     * @return array<int,array> the cleaned tree
     */
    public function sanitizeTree(array $blocks): array
    {
        $this->count = 0;
        return $this->walk(array_values($blocks), 1);
    }

    /** @param array<int,mixed> $nodes */
    private function walk(array $nodes, int $depth): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('This library item is nested too deeply to import.');
        }

        $out = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || !isset($node['type']) || !is_string($node['type'])) {
                throw new RuntimeException('This does not look like a valid library item (a block is malformed).');
            }
            if (++$this->count > self::MAX_NODES) {
                throw new RuntimeException('This library item is too large to import (over ' . self::MAX_NODES . ' blocks).');
            }
            if (!$this->registry->get($node['type'])) {
                throw new RuntimeException("This library item uses an unknown block type: {$node['type']}.");
            }

            $clean = [
                'type' => $node['type'],
                'data' => $this->sanitizeData($node['type'], is_array($node['data'] ?? null) ? $node['data'] : []),
            ];
            if (isset($node['style']) && is_array($node['style'])) {
                $clean['style'] = $node['style']; // strict-sanitized at render by BlockStyle
            }

            $children = $node['children'] ?? null;
            if (is_array($children) && $children !== []) {
                $clean['children'] = $this->walk(array_values($children), $depth + 1);
            }

            $out[] = $clean;
        }

        return $out;
    }

    private function sanitizeData(string $type, array $data): array
    {
        // Reuse the publish-time per-block sanitizer by handing it a transient
        // Block (never saved) — same allowlist the published page would get.
        $block = new Block(['type' => $type, 'data' => $data]);

        return $this->sanitizer->sanitizeBlock($block);
    }
}
