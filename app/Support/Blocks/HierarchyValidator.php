<?php

namespace App\Support\Blocks;

use App\Domain\Blocks\Enums\BlockLevel;

/**
 * Validates the 4-level page composition hierarchy.
 *
 * Rules enforced:
 * - Section can only contain Rows
 * - Row can only contain Columns
 * - Column can only contain Modules
 * - Module has no children
 * - Only Sections can be at root level (no parent)
 * - Module blocks at root are allowed for backward compatibility (legacy flat blocks)
 */
class HierarchyValidator
{
    /**
     * Validate a block tree.
     *
     * @param array $blocks Nested block tree (each block has 'children' array)
     * @return ValidationResult
     */
    public static function validate(array $blocks): ValidationResult
    {
        $errors = [];
        self::validateLevel($blocks, null, $errors, '');
        return new ValidationResult(empty($errors), $errors);
    }

    private static function validateLevel(array $blocks, ?BlockLevel $parentLevel, array &$errors, string $path): void
    {
        foreach ($blocks as $i => $block) {
            $blockPath = $path ? "{$path}.children[{$i}]" : "blocks[{$i}]";
            $type = $block['type'] ?? 'unknown';
            $levelStr = $block['level'] ?? 'module';
            $level = BlockLevel::tryFrom($levelStr);

            if (!$level) {
                $errors[] = [
                    'path' => $blockPath,
                    'message' => "Invalid block level '{$levelStr}'. Must be section, row, column, or module.",
                ];
                continue;
            }

            // Check root-level placement
            if ($parentLevel === null && !$level->canBeRoot() && $level !== BlockLevel::Module) {
                // Module at root is allowed for backward compatibility (legacy flat blocks)
                $errors[] = [
                    'path' => $blockPath,
                    'message' => "{$level->label()} cannot be at root level. Only Sections can be root blocks.",
                ];
            }

            // Check parent-child containment
            if ($parentLevel !== null) {
                $allowedChildren = $parentLevel->allowedChildLevels();
                if (!in_array($level, $allowedChildren)) {
                    $allowedLabels = implode(', ', array_map(fn(BlockLevel $l) => $l->label(), $allowedChildren));
                    $errors[] = [
                        'path' => $blockPath,
                        'message' => "{$level->label()} cannot be inside {$parentLevel->label()}. {$parentLevel->label()} can only contain: {$allowedLabels}.",
                    ];
                }
            }

            // Validate children recursively
            $children = $block['children'] ?? [];
            if (!empty($children)) {
                if ($level === BlockLevel::Module) {
                    $errors[] = [
                        'path' => $blockPath,
                        'message' => "Module '{$type}' cannot have children. Modules are leaf nodes.",
                    ];
                } else {
                    self::validateLevel($children, $level, $errors, $blockPath);
                }
            }
        }
    }
}

/**
 * Result of hierarchy validation.
 */
class ValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
    ) {}

    public function errorMessages(): array
    {
        return array_map(fn($e) => $e['message'], $this->errors);
    }
}
