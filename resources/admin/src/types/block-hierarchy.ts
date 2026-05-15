/**
 * Block hierarchy types for the 4-level page composition model.
 *
 * Section → Row → Column → Module
 */

export type BlockLevel = 'section' | 'row' | 'column' | 'module';

export interface HierarchicalBlock {
  id: string;
  type: string;
  level: BlockLevel; // defaults to 'module' for legacy blocks without level field
  order: number;
  data: Record<string, unknown>;
  children: HierarchicalBlock[];
  presetId?: string | null;
  // Existing fields preserved
  style?: Record<string, unknown>;
  animation?: Record<string, unknown>;
  responsive?: Record<string, unknown>;
  advanced?: Record<string, unknown>;
}

/** What child levels can a given level contain? */
export const ALLOWED_CHILDREN: Record<BlockLevel, BlockLevel[]> = {
  section: ['row'],
  row: ['column'],
  column: ['module'],
  module: [], // leaf node
};

/** Can this level exist at root (no parent)? */
export function canBeRoot(level: BlockLevel): boolean {
  // Section is the only structural root. Module at root is allowed for backward compat.
  return level === 'section' || level === 'module';
}

export interface HierarchyError {
  path: string;
  message: string;
}

export interface HierarchyValidationResult {
  valid: boolean;
  errors: HierarchyError[];
}
