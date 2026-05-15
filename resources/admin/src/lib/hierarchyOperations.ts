/**
 * Tree operations for the 4-level block hierarchy.
 * Mirrors server-side BlockHierarchyService logic.
 */

import type { HierarchicalBlock } from '@/types/block-hierarchy';
import { ALLOWED_CHILDREN } from '@/types/block-hierarchy';

/** Find a block by ID in a nested tree. */
export function findBlockById(tree: HierarchicalBlock[], id: string): HierarchicalBlock | null {
  for (const block of tree) {
    if (block.id === id) return block;
    if (block.children?.length) {
      const found = findBlockById(block.children, id);
      if (found) return found;
    }
  }
  return null;
}

/** Find the parent of a block by ID. Returns null for root blocks. */
export function findBlockParent(tree: HierarchicalBlock[], id: string, parent: HierarchicalBlock | null = null): HierarchicalBlock | null {
  for (const block of tree) {
    if (block.id === id) return parent;
    if (block.children?.length) {
      const found = findBlockParent(block.children, id, block);
      if (found !== undefined) return found;
    }
  }
  return null;
}

/** Check if moving a block to a target parent is valid per containment rules. */
export function canMoveBlock(
  tree: HierarchicalBlock[],
  blockId: string,
  targetParentId: string | null,
): { valid: boolean; reason?: string } {
  const block = findBlockById(tree, blockId);
  if (!block) return { valid: false, reason: 'Block not found.' };

  // Moving to root
  if (targetParentId === null) {
    if (block.level !== 'section' && block.level !== 'module') {
      return { valid: false, reason: `${block.level} cannot be at root level. Only Sections can be root blocks.` };
    }
    return { valid: true };
  }

  const targetParent = findBlockById(tree, targetParentId);
  if (!targetParent) return { valid: false, reason: 'Target parent not found.' };

  // Check containment
  const allowed = ALLOWED_CHILDREN[targetParent.level];
  if (!allowed.includes(block.level)) {
    return {
      valid: false,
      reason: `${block.level} cannot be inside ${targetParent.level}. ${targetParent.level} can only contain: ${allowed.join(', ')}.`,
    };
  }

  // Prevent moving into own descendant (circular)
  if (isDescendant(block, targetParentId)) {
    return { valid: false, reason: 'Cannot move a block into its own descendant.' };
  }

  return { valid: true };
}

/** Check if targetId is a descendant of block. */
function isDescendant(block: HierarchicalBlock, targetId: string): boolean {
  for (const child of block.children || []) {
    if (child.id === targetId) return true;
    if (isDescendant(child, targetId)) return true;
  }
  return false;
}

/** Remove a block from the tree by ID. Returns [newTree, removedBlock]. */
export function removeBlock(tree: HierarchicalBlock[], id: string): [HierarchicalBlock[], HierarchicalBlock | null] {
  for (let i = 0; i < tree.length; i++) {
    if (tree[i].id === id) {
      const removed = tree[i];
      return [[...tree.slice(0, i), ...tree.slice(i + 1)], removed];
    }
    if (tree[i].children?.length) {
      const [newChildren, found] = removeBlock(tree[i].children, id);
      if (found) {
        const newTree = [...tree];
        newTree[i] = { ...newTree[i], children: newChildren };
        return [newTree, found];
      }
    }
  }
  return [tree, null];
}

/** Add a child block to a specific parent. */
export function addChildToBlock(tree: HierarchicalBlock[], parentId: string, child: HierarchicalBlock): HierarchicalBlock[] {
  return tree.map(block => {
    if (block.id === parentId) {
      return { ...block, children: [...(block.children || []), child] };
    }
    if (block.children?.length) {
      return { ...block, children: addChildToBlock(block.children, parentId, child) };
    }
    return block;
  });
}

/** Move a block to a new parent at a specific order. */
export function moveBlock(
  tree: HierarchicalBlock[],
  blockId: string,
  newParentId: string | null,
  newOrder: number,
): HierarchicalBlock[] {
  const [treeWithout, removed] = removeBlock(tree, blockId);
  if (!removed) return tree;

  const movedBlock = { ...removed, order: newOrder };

  if (newParentId === null) {
    // Move to root
    const newTree = [...treeWithout];
    newTree.splice(newOrder, 0, movedBlock);
    return newTree;
  }

  return addChildToBlock(treeWithout, newParentId, movedBlock);
}

/** Deep clone and assign new IDs to a block and its children. */
export function duplicateBlock(tree: HierarchicalBlock[], blockId: string): HierarchicalBlock[] {
  const block = findBlockById(tree, blockId);
  if (!block) return tree;

  const parent = findBlockParent(tree, blockId);
  const cloned = deepCloneWithNewIds(block);

  if (parent) {
    return addChildToBlock(tree, parent.id, cloned);
  }
  return [...tree, cloned];
}

function deepCloneWithNewIds(block: HierarchicalBlock): HierarchicalBlock {
  return {
    ...block,
    id: crypto.randomUUID(),
    children: (block.children || []).map(deepCloneWithNewIds),
  };
}
