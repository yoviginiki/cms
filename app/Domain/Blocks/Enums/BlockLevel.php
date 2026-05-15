<?php

namespace App\Domain\Blocks\Enums;

/**
 * Block levels in the 4-level page composition hierarchy.
 *
 * Section → Row → Column → Module
 *
 * Containment rules:
 * - Section: root-level only, contains Rows
 * - Row: inside Section only, contains Columns
 * - Column: inside Row only, contains Modules
 * - Module: inside Column only, no children (leaf node)
 */
enum BlockLevel: string
{
    case Section = 'section';
    case Row = 'row';
    case Column = 'column';
    case Module = 'module';

    /**
     * What child levels can this level contain?
     */
    public function allowedChildLevels(): array
    {
        return match ($this) {
            self::Section => [self::Row],
            self::Row => [self::Column],
            self::Column => [self::Module],
            self::Module => [], // leaf node
        };
    }

    /**
     * Can this level exist at root (no parent)?
     */
    public function canBeRoot(): bool
    {
        return $this === self::Section;
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Section => 'Section',
            self::Row => 'Row',
            self::Column => 'Column',
            self::Module => 'Module',
        };
    }
}
