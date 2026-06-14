/**
 * Sprint 5 — Extractable builder helper functions.
 * Pure functions for block tree manipulation, testable without store.
 */

import type { BlockData } from '@/types/blocks';

/** Generate a new UUID */
export function generateId(): string {
  return crypto.randomUUID();
}

/** Deep clone an object via JSON serialization */
export function deepClone<T>(obj: T): T {
  return JSON.parse(JSON.stringify(obj));
}

/** Deep clone a block tree with new IDs */
export function deepCloneWithNewIds(block: BlockData): BlockData {
  return {
    ...block,
    id: generateId(),
    data: deepClone(block.data),
    children: (block.children ?? []).map(deepCloneWithNewIds),
  };
}

/** Find a block in a tree by ID */
export function findInTree(
  blocks: BlockData[],
  id: string,
): { block: BlockData; parent: BlockData[]; index: number } | null {
  for (let i = 0; i < blocks.length; i++) {
    if (blocks[i].id === id) {
      return { block: blocks[i], parent: blocks, index: i };
    }
    const found = findInTree(blocks[i].children, id);
    if (found) return found;
  }
  return null;
}

/** Remove a block from a tree by ID */
export function removeFromTree(blocks: BlockData[], id: string): BlockData[] {
  return blocks
    .filter((b) => b.id !== id)
    .map((b) => ({
      ...b,
      children: removeFromTree(b.children, id),
    }));
}

/** Reorder blocks — set order property to match array index */
export function reorder(blocks: BlockData[]): BlockData[] {
  return blocks.map((b, i) => ({
    ...b,
    order: i,
    children: reorder(b.children),
  }));
}

/** Ensure all blocks have children array */
export function normalizeBlocks(blocks: BlockData[]): BlockData[] {
  return blocks.map((b) => ({
    ...b,
    children: normalizeBlocks(b.children ?? []),
  }));
}

/** Find siblings of a block (the array it belongs to) */
export function findSiblings(blocks: BlockData[], blockId: string): BlockData[] {
  for (const b of blocks) {
    if (b.id === blockId) return blocks;
  }
  for (const b of blocks) {
    const found = findSiblingsInner(b.children, blockId);
    if (found) return found;
  }
  return blocks;
}

function findSiblingsInner(blocks: BlockData[], blockId: string): BlockData[] | null {
  for (const b of blocks) {
    if (b.id === blockId) return blocks;
  }
  for (const b of blocks) {
    const found = findSiblingsInner(b.children, blockId);
    if (found) return found;
  }
  return null;
}

/** Check if a block can move up (has a sibling before it) */
export function canMoveUp(blocks: BlockData[], blockId: string): boolean {
  const siblings = findSiblings(blocks, blockId);
  const idx = siblings.findIndex((b) => b.id === blockId);
  return idx > 0;
}

/** Check if a block can move down (has a sibling after it) */
export function canMoveDown(blocks: BlockData[], blockId: string): boolean {
  const siblings = findSiblings(blocks, blockId);
  const idx = siblings.findIndex((b) => b.id === blockId);
  return idx >= 0 && idx < siblings.length - 1;
}
