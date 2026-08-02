<?php

namespace App\Domain\Projection\Descriptors;

/**
 * A single declared field within a block's projection.
 *
 * `$path` is a dot-path RELATIVE to the block's `data` (canonical addressing
 * decision: no `data.` prefix — identical to the editor's `data-sp-field`).
 * The canonical field address is therefore `{block_uuid}#{path}`.
 *
 * The four participation flags decide which projection views the field feeds:
 *   - schema:    contributes to the schema.org / JSON-LD view
 *   - rag:       contributes to the RAG text segments
 *   - inventory: contributes to the asset / link inventory
 *   - segment:   the field is itself a RAG segment boundary
 */
final class FieldProjection
{
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly FieldType $type,
        public readonly bool $schema = false,
        public readonly bool $rag = false,
        public readonly bool $inventory = false,
        public readonly bool $segment = false,
    ) {
    }

    /**
     * @param array{schema?:bool,rag?:bool,inventory?:bool,segment?:bool} $flags
     */
    public static function fromFlags(string $path, string $name, FieldType $type, array $flags): self
    {
        return new self(
            path: $path,
            name: $name,
            type: $type,
            schema: (bool) ($flags['schema'] ?? false),
            rag: (bool) ($flags['rag'] ?? false),
            inventory: (bool) ($flags['inventory'] ?? false),
            segment: (bool) ($flags['segment'] ?? false),
        );
    }
}
