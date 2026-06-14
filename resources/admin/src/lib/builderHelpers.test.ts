import { describe, it, expect } from 'vitest';
import {
  deepCloneWithNewIds,
  findInTree,
  removeFromTree,
  reorder,
  normalizeBlocks,
  canMoveUp,
  canMoveDown,
} from './builderHelpers';
import type { BlockData } from '@/types/blocks';

function makeBlock(id: string, type = 'heading', children: BlockData[] = []): BlockData {
  return { id, type, level: 'module', data: { text: `Block ${id}` }, children, order: 0 };
}

describe('deepCloneWithNewIds', () => {
  it('creates a deep copy with a different ID', () => {
    const block = makeBlock('a');
    const clone = deepCloneWithNewIds(block);
    expect(clone.id).not.toBe('a');
    expect(clone.data).toEqual(block.data);
    expect(clone.type).toBe(block.type);
  });

  it('recursively assigns new IDs to children', () => {
    const block = makeBlock('a', 'section', [
      makeBlock('b', 'row', [makeBlock('c')]),
    ]);
    const clone = deepCloneWithNewIds(block);
    expect(clone.id).not.toBe('a');
    expect(clone.children[0].id).not.toBe('b');
    expect(clone.children[0].children[0].id).not.toBe('c');
  });

  it('does not mutate the original', () => {
    const block = makeBlock('a');
    const clone = deepCloneWithNewIds(block);
    clone.data.text = 'changed';
    expect((block.data as any).text).toBe('Block a');
  });
});

describe('findInTree', () => {
  it('finds a root-level block', () => {
    const blocks = [makeBlock('a'), makeBlock('b')];
    const result = findInTree(blocks, 'b');
    expect(result).not.toBeNull();
    expect(result!.block.id).toBe('b');
    expect(result!.index).toBe(1);
  });

  it('finds a nested block', () => {
    const blocks = [makeBlock('a', 'section', [makeBlock('b', 'row', [makeBlock('c')])])];
    const result = findInTree(blocks, 'c');
    expect(result).not.toBeNull();
    expect(result!.block.id).toBe('c');
  });

  it('returns null for missing ID', () => {
    const blocks = [makeBlock('a')];
    expect(findInTree(blocks, 'z')).toBeNull();
  });
});

describe('removeFromTree', () => {
  it('removes a root-level block', () => {
    const blocks = [makeBlock('a'), makeBlock('b'), makeBlock('c')];
    const result = removeFromTree(blocks, 'b');
    expect(result.length).toBe(2);
    expect(result.map(b => b.id)).toEqual(['a', 'c']);
  });

  it('removes a nested block', () => {
    const blocks = [makeBlock('a', 'section', [makeBlock('b'), makeBlock('c')])];
    const result = removeFromTree(blocks, 'c');
    expect(result[0].children.length).toBe(1);
    expect(result[0].children[0].id).toBe('b');
  });
});

describe('reorder', () => {
  it('sets order to match array index', () => {
    const blocks = [
      { ...makeBlock('a'), order: 5 },
      { ...makeBlock('b'), order: 2 },
      { ...makeBlock('c'), order: 9 },
    ];
    const result = reorder(blocks);
    expect(result[0].order).toBe(0);
    expect(result[1].order).toBe(1);
    expect(result[2].order).toBe(2);
  });

  it('reorders children recursively', () => {
    const blocks = [makeBlock('a', 'section', [
      { ...makeBlock('b'), order: 3 },
      { ...makeBlock('c'), order: 1 },
    ])];
    const result = reorder(blocks);
    expect(result[0].children[0].order).toBe(0);
    expect(result[0].children[1].order).toBe(1);
  });
});

describe('normalizeBlocks', () => {
  it('ensures children array exists', () => {
    const blocks = [{ id: 'a', type: 'heading', data: {}, order: 0 }] as any;
    const result = normalizeBlocks(blocks);
    expect(Array.isArray(result[0].children)).toBe(true);
  });
});

describe('canMoveUp / canMoveDown', () => {
  const blocks = [makeBlock('a'), makeBlock('b'), makeBlock('c')];

  it('first block cannot move up', () => {
    expect(canMoveUp(blocks, 'a')).toBe(false);
  });

  it('middle block can move up', () => {
    expect(canMoveUp(blocks, 'b')).toBe(true);
  });

  it('last block cannot move down', () => {
    expect(canMoveDown(blocks, 'c')).toBe(false);
  });

  it('middle block can move down', () => {
    expect(canMoveDown(blocks, 'b')).toBe(true);
  });

  it('works for nested blocks', () => {
    const nested = [makeBlock('a', 'section', [makeBlock('x'), makeBlock('y')])];
    expect(canMoveUp(nested, 'x')).toBe(false);
    expect(canMoveDown(nested, 'x')).toBe(true);
    expect(canMoveUp(nested, 'y')).toBe(true);
    expect(canMoveDown(nested, 'y')).toBe(false);
  });
});
