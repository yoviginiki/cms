<?php

namespace App\Domain\Projection;

/**
 * The full internal projection plus the filtered views over it. The views are
 * pure subsets — they never compute anything the full projection does not
 * already contain (Prime Directive 7).
 */
final class Projection
{
    public function __construct(
        private readonly array $data,
    ) {
    }

    /** The complete internal projection. */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Deterministic JSON. Fixed key order (as built), unescaped unicode/slashes.
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode(
            $this->data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | $flags
        );
    }

    /**
     * A filtered view. Every returned value is a subset of the full projection.
     */
    public function view(ProjectionView $view): array
    {
        return match ($view) {
            ProjectionView::Internal => $this->data,

            ProjectionView::Public => [
                'projection_version' => $this->data['projection_version'],
                'page' => $this->data['page'],
                'schema_org' => $this->data['schema_org'],
                'structure' => $this->minimalStructure($this->data['structure']),
            ],

            ProjectionView::Rag => [
                'projection_version' => $this->data['projection_version'],
                'source' => $this->data['source'],
                'page' => $this->data['page'],
                'segments' => $this->data['segments'],
            ],

            ProjectionView::Inventory => [
                'projection_version' => $this->data['projection_version'],
                'source' => $this->data['source'],
                'inventory' => $this->data['inventory'],
            ],
        };
    }

    /**
     * The public structure: only address / type / path / depth per entry, with
     * children reduced the same way. A strict key-subset of each full entry.
     *
     * @param list<array> $entries
     * @return list<array>
     */
    private function minimalStructure(array $entries): array
    {
        $out = [];
        foreach ($entries as $entry) {
            $out[] = [
                'address' => $entry['address'],
                'type' => $entry['type'],
                'path' => $entry['path'],
                'depth' => $entry['depth'],
                'children' => $this->minimalStructure($entry['children']),
            ];
        }

        return $out;
    }
}
