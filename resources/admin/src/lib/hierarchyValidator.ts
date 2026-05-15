/**
 * Client-side hierarchy validator — mirrors PHP HierarchyValidator.
 *
 * Validates the 4-level page composition hierarchy:
 * Section → Row → Column → Module
 */

import type { BlockLevel, HierarchicalBlock, HierarchyError, HierarchyValidationResult } from '@/types/block-hierarchy';
import { ALLOWED_CHILDREN, canBeRoot } from '@/types/block-hierarchy';

const LEVEL_LABELS: Record<BlockLevel, string> = {
  section: 'Section',
  row: 'Row',
  column: 'Column',
  module: 'Module',
};

const VALID_LEVELS: BlockLevel[] = ['section', 'row', 'column', 'module'];

/**
 * Validate a block tree hierarchy.
 */
export function validateHierarchy(blocks: HierarchicalBlock[]): HierarchyValidationResult {
  const errors: HierarchyError[] = [];
  validateLevel(blocks, null, errors, '');
  return { valid: errors.length === 0, errors };
}

function validateLevel(
  blocks: HierarchicalBlock[],
  parentLevel: BlockLevel | null,
  errors: HierarchyError[],
  path: string,
): void {
  for (let i = 0; i < blocks.length; i++) {
    const block = blocks[i];
    const blockPath = path ? `${path}.children[${i}]` : `blocks[${i}]`;
    const level = block.level || 'module'; // default for legacy blocks without level

    // Validate level is a known value
    if (!VALID_LEVELS.includes(level)) {
      errors.push({
        path: blockPath,
        message: `Invalid block level '${level}'. Must be section, row, column, or module.`,
      });
      continue;
    }

    // Check root-level placement
    if (parentLevel === null && !canBeRoot(level)) {
      errors.push({
        path: blockPath,
        message: `${LEVEL_LABELS[level]} cannot be at root level. Only Sections can be root blocks.`,
      });
    }

    // Check parent-child containment
    if (parentLevel !== null) {
      const allowed = ALLOWED_CHILDREN[parentLevel];
      if (!allowed.includes(level)) {
        const allowedLabels = allowed.map((l) => LEVEL_LABELS[l]).join(', ');
        errors.push({
          path: blockPath,
          message: `${LEVEL_LABELS[level]} cannot be inside ${LEVEL_LABELS[parentLevel]}. ${LEVEL_LABELS[parentLevel]} can only contain: ${allowedLabels}.`,
        });
      }
    }

    // Validate children
    const children = block.children || [];
    if (children.length > 0) {
      if (level === 'module') {
        errors.push({
          path: blockPath,
          message: `Module '${block.type}' cannot have children. Modules are leaf nodes.`,
        });
      } else {
        validateLevel(children, level, errors, blockPath);
      }
    }
  }
}
