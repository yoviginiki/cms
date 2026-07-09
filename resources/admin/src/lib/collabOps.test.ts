import { describe, it, expect } from 'vitest';
import { opKeys, lwwNewer, invertOp, isCanvasOp, isStampedOp } from './collabOps';
import type { CanvasSection, StampedOp } from '@/types/canvas';

const sectionsFixture = (): CanvasSection[] => [
  {
    id: 's1', settings: { height: 400, bleed: false, background: '#fff' }, data: {}, style: {},
    elements: [{ id: 'e1', blockType: 'heading', data: {}, x: 10, y: 20, width: 100, height: 40, rotation: 0, zIndex: 1, locked: false, style: {} }],
  },
  { id: 's2', settings: { height: 300, bleed: true, background: '' }, data: {}, style: {}, elements: [] },
];

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

describe('collabOps invertOp', () => {
  it('inverts add → del and del → restoreElement(s)', () => {
    const s = sectionsFixture();
    expect(invertOp({ t: 'add', sectionId: 's1', element: { id: 'x' } as never }, s)).toEqual([{ t: 'del', ids: ['x'] }]);
    const inv = invertOp({ t: 'del', ids: ['e1'] }, s);
    expect(inv).toHaveLength(1);
    expect(inv[0]).toMatchObject({ t: 'restoreElement', sectionId: 's1' });
    expect((inv[0] as { element: { x: number } }).element.x).toBe(10); // captured prior state
  });

  it('inverts an element edit to restore its prior full state', () => {
    const s = sectionsFixture();
    const inv = invertOp({ t: 'layout', id: 'e1', patch: { x: 999 }, bp: 'desktop' }, s);
    expect(inv[0]).toMatchObject({ t: 'restoreElement', sectionId: 's1' });
    expect((inv[0] as { element: { x: number } }).element.x).toBe(10); // NOT 999 — the prior value
  });

  it('inverts section ops structurally', () => {
    const s = sectionsFixture();
    expect(invertOp({ t: 'secAdd', section: { id: 'sn' } as never }, s)).toEqual([{ t: 'secDel', id: 'sn' }]);
    expect(invertOp({ t: 'secMove', id: 's2', dir: 'up' }, s)).toEqual([{ t: 'secMove', id: 's2', dir: 'down' }]);
    const delInv = invertOp({ t: 'secDel', id: 's2' }, s);
    expect(delInv[0]).toMatchObject({ t: 'secAdd', afterId: 's1' });      // re-add after its prior neighbor
    const setInv = invertOp({ t: 'secSettings', id: 's1', patch: { bleed: true } }, s);
    expect(setInv).toEqual([{ t: 'secSettings', id: 's1', patch: { bleed: false } }]); // prior value
  });
});

describe('collabOps wire validation (defends applyOp/opKeys from garbage peers)', () => {
  it('accepts well-formed ops of every variant', () => {
    const ok: unknown[] = [
      { t: 'layout', id: 'a', patch: { x: 1 }, bp: 'desktop' },
      { t: 'del', ids: ['a', 'b'] },
      { t: 'z', ids: ['a'], mode: 'front' },
      { t: 'add', sectionId: 's1', element: { id: 'e' } },
      { t: 'restoreElement', sectionId: 's1', element: { id: 'e' } },
      { t: 'secAdd', section: { id: 's2' } },
      { t: 'secMove', id: 's1', dir: 'up' },
    ];
    ok.forEach((o) => expect(isCanvasOp(o)).toBe(true));
  });

  it('rejects unknown t, missing id/ids/element, and non-objects', () => {
    const bad: unknown[] = [
      null, undefined, 42, 'layout', {},
      { t: 'nope' },                          // unknown discriminant
      { t: 'layout' },                        // missing id
      { t: 'layout', id: 5 },                 // id not a string
      { t: 'del' },                           // missing ids
      { t: 'del', ids: [1, 2] },              // ids not strings
      { t: 'add', element: {} },              // element without id
      { t: 'secAdd', section: {} },           // section without id
    ];
    bad.forEach((o) => expect(isCanvasOp(o)).toBe(false));
  });

  it('isStampedOp requires a finite lamport, string client, and valid op', () => {
    const good: StampedOp = { op: { t: 'layout', id: 'a', patch: {}, bp: 'desktop' }, lamport: 3, client: 'c1' };
    expect(isStampedOp(good)).toBe(true);
    expect(isStampedOp({ ...good, lamport: NaN })).toBe(false);        // poisoned clock
    expect(isStampedOp({ ...good, lamport: undefined as never })).toBe(false);
    expect(isStampedOp({ ...good, client: 7 as never })).toBe(false);
    expect(isStampedOp({ ...good, op: { t: 'bogus' } as never })).toBe(false);
    expect(isStampedOp(null)).toBe(false);
  });
});
