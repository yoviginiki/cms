<?php

namespace App\Domain\Projection\Descriptors;

/**
 * Declarative descriptor for how a block projects into the machine-readable
 * views. Built fluently inside a block definition's projection() method.
 *
 * This object is pure data + a heading-level resolver closure; it performs no
 * I/O and never inspects live block data except through the explicit
 * resolveHeadingLevel() call. Only paths declared here participate in any
 * projection (Prime Directive 3).
 */
final class BlockProjection
{
    private ?string $schemaType = null;

    /** @var list<FieldProjection> */
    private array $fields = [];

    private bool $segmentBoundary = false;

    /** @var null|callable(array):(int|string|null) */
    private $headingLevelResolver = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * The schema.org @type this block emits, or null when the block has no
     * standalone semantic type (e.g. a heading only structures the outline).
     */
    public function schemaType(?string $type): self
    {
        $this->schemaType = $type;

        return $this;
    }

    /**
     * Declare a semantic field. $path is relative to the block's data
     * (canonical form {uuid}#{path}, no `data.` prefix).
     *
     * @param array{schema?:bool,rag?:bool,inventory?:bool,segment?:bool} $flags
     */
    public function field(string $path, string $name, FieldType $type, array $flags = []): self
    {
        $this->fields[] = FieldProjection::fromFlags($path, $name, $type, $flags);

        return $this;
    }

    /**
     * Whether this block is itself a segment boundary for RAG chunking.
     */
    public function segmentBoundary(bool $isBoundary = true): self
    {
        $this->segmentBoundary = $isBoundary;

        return $this;
    }

    /**
     * Declare the block's contribution to the heading outline. The resolver
     * receives the block's `data` array and returns the heading level (1-6)
     * or null when the block is not a heading in this instance.
     *
     * @param callable(array):(int|string|null) $resolver
     */
    public function headingLevel(callable $resolver): self
    {
        $this->headingLevelResolver = $resolver;

        return $this;
    }

    // --- read side (consumed by the Phase 3 builder) -----------------------

    public function getSchemaType(): ?string
    {
        return $this->schemaType;
    }

    /** @return list<FieldProjection> */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function isSegmentBoundary(): bool
    {
        return $this->segmentBoundary;
    }

    public function hasHeadingLevel(): bool
    {
        return $this->headingLevelResolver !== null;
    }

    /**
     * Resolve the heading level for a concrete block's data. Returns null when
     * no resolver is declared or the resolver yields null.
     */
    public function resolveHeadingLevel(array $data): ?int
    {
        if ($this->headingLevelResolver === null) {
            return null;
        }

        $level = ($this->headingLevelResolver)($data);

        return $level === null ? null : (int) $level;
    }
}
