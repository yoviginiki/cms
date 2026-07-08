import { describe, it, expect } from 'vitest';
import { opKeys, lwwNewer } from './collabOps';
import type { StampedOp } from '@/types/canvas';

const layout = (id: string, lamport: number, client: string): StampedOp =>
  ({ op: { t: 'layout', id, patch: { x: 1 }, bp: 'desktop' }, lamport, client });

describe('collabOps LWW', () => {
  it('opKeys extracts the touched element ids', () => {
    expect(opKeys({ t: 'layout', id: 'a', patch: {}, bp: 'desktop' })).toEqual(['a']);
    expect(opKeys({ t: 'del', ids: ['a', 'b'] })).toEqual(['a', 'b']);
    expect(opKeys({ t: 'z', ids: ['c'], mode: 'front' })).toEqual(['c']);
  });

  it('accepts a first op and rejects a stale (lower-lamport) one', () => {
    expect(lwwNewer(undefined, layout('a', 1, 'c1'))).toBe(true);
    const applied = { lamport: 5, client: 'c1' };
    expect(lwwNewer(applied, layout('a', 4, 'c2'))).toBe(false); // older → ignore
    expect(lwwNewer(applied, layout('a', 6, 'c2'))).toBe(true);  // newer → apply
  });

  it('breaks equal-lamport ties by client id (deterministic across peers)', () => {
    const applied = { lamport: 5, client: 'c1' };
    expect(lwwNewer(applied, layout('a', 5, 'c2'))).toBe(true);  // c2 > c1 wins
    expect(lwwNewer(applied, layout('a', 5, 'c0'))).toBe(false); // c0 < c1 loses
  });

  it('delete always wins (tombstone — a concurrent move cannot resurrect it)', () => {
    const applied = { lamport: 100, client: 'zzz' };
    const del: StampedOp = { op: { t: 'del', ids: ['a'] }, lamport: 1, client: 'aaa' };
    expect(lwwNewer(applied, del)).toBe(true);
  });
});
