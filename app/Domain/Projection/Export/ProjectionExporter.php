<?php

namespace App\Domain\Projection\Export;

use App\Domain\Projection\Projection;

/**
 * Export consumer (first slice): loss-free JSON and human-readable Markdown
 * derived from a projection. Pure: operates only on the projection array, no
 * I/O — the same determinism guarantees as the builder carry through.
 *
 * The projection is the single source; both formats are filtered/rendered
 * views of it, never independent walks of the block tree.
 */
class ProjectionExporter
{
    /** Full, loss-free JSON export (the internal projection). */
    public function toJson(Projection $projection, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return (string) json_encode($projection->toArray(), $flags);
    }

    /**
     * Human-readable Markdown, rendered in document order from the projection's
     * structure. Deterministic.
     */
    public function toMarkdown(Projection $projection): string
    {
        $data = $projection->toArray();

        // address (block uuid) → heading level, from the outline.
        $levelByBlock = [];
        foreach ($data['inventory']['heading_outline'] as $h) {
            $levelByBlock[$h['address']] = $h['level'];
        }
        // block uuid → segment text, from the RAG segments.
        $segmentByBlock = [];
        foreach ($data['segments'] as $seg) {
            $blockId = explode('#', $seg['address'])[0];
            $segmentByBlock[$blockId] = $seg['text'];
        }

        $lines = [];
        $title = $data['page']['title'] ?? '';
        if ($title !== '') {
            $lines[] = '# ' . $title;
            $lines[] = '';
        }

        foreach ($data['structure'] as $entry) {
            $this->renderEntry($entry, $levelByBlock, $segmentByBlock, $lines);
        }

        // Collapse trailing blank lines to a single terminating newline.
        while (! empty($lines) && end($lines) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array               $entry
     * @param array<string,int>    $levelByBlock
     * @param array<string,string> $segmentByBlock
     * @param list<string>         $lines
     */
    private function renderEntry(array $entry, array $levelByBlock, array $segmentByBlock, array &$lines): void
    {
        $address = $entry['address'];
        $fields = $entry['fields'] ?? [];

        if (isset($levelByBlock[$address])) {
            // Outline level maps directly to Markdown depth: page title is #,
            // an h2 content heading is ##.
            $level = max(1, min(6, $levelByBlock[$address]));
            $lines[] = str_repeat('#', $level) . ' ' . $this->fieldValue($fields, 'headline');
            $lines[] = '';
        } elseif ($entry['type'] === 'image') {
            $alt = $this->fieldValue($fields, 'name');
            $src = $this->fieldValue($fields, 'contentUrl') ?: $this->fieldValue($fields, 'image');
            if ($src !== '') {
                $lines[] = '![' . $alt . '](' . $src . ')';
                $lines[] = '';
            }
        } elseif (isset($segmentByBlock[$address])) {
            $lines[] = $segmentByBlock[$address];
            $lines[] = '';
        }

        foreach ($entry['children'] ?? [] as $child) {
            $this->renderEntry($child, $levelByBlock, $segmentByBlock, $lines);
        }
    }

    /** First field value matching a semantic name, or ''. */
    private function fieldValue(array $fields, string $name): string
    {
        foreach ($fields as $field) {
            if (($field['name'] ?? null) === $name) {
                return (string) ($field['value'] ?? '');
            }
        }

        return '';
    }
}
