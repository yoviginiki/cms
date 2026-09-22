import { describe, it, expect } from 'vitest';
import { stepZ, changedZ } from './canvasZ';
import type { CanvasElement } from '@/types/canvas';

const el = (id: string, zIndex: number): CanvasElement =>
  ({ id, blockType: 'text', data: {}, x: 0, y: 0, width: 10, height: 10, rotation: 0, zIndex, locked: false, style: {} });

const zOf = (els: CanvasElement[]) => [...els].sort((a, b) => a.zIndex - b.zIndex).map(e => e.id);

describe('stepZ', () => {
  it('brings one element forward one step', () => {
    const out = stepZ([el('a', 1), el('b', 2), el('c', 3)], ['a'], 'forward');
    expect(zOf(out)).toEqual(['b', 'a', 'c']);
  });

  it('sends one element backward one step', () => {
    const out = stepZ([el('a', 1), el('b', 2), el('c', 3)], ['c'], 'backward');
    expect(zOf(out)).toEqual(['a', 'c', 'b']);
  });

  it('is a no-op at the edge (already on top / bottom) but still normalises z', () => {
    const out = stepZ([el('a', 5), el('b', 9)], ['b'], 'forward');
    expect(zOf(out)).toEqual(['a', 'b']);
    expect(out.map(e => e.zIndex)).toEqual([1, 2]);
  });

  it('breaks z ties by array order so the step is never swallowed', () => {
    // a and b both z=1 (fresh imports do this); a is first in the array = below b
    const out = stepZ([el('a', 1), el('b', 1), el('c', 2)], ['a'], 'forward');
    expect(zOf(out)).toEqual(['b', 'a', 'c']);
  });

  it('moves a contiguous multi-selection as a group', () => {
    const out = stepZ([el('a', 1), el('b', 2), el('c', 3), el('d', 4)], ['b', 'c'], 'forward');
    expect(zOf(out)).toEqual(['a', 'd', 'b', 'c']);
  });

  it('keeps untouched element refs when nothing changes for them', () => {
    const a = el('a', 1), b = el('b', 2), c = el('c', 3);
    const out = stepZ([a, b, c], ['b'], 'forward');
    expect(out[0]).toBe(a);   // a: z 1 → 1, same ref
    expect(changedZ([a, b, c], out).map(e => e.id)).toEqual(['b', 'c']);
  });
});
