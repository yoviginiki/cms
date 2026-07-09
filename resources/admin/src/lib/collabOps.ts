import type { CanvasOp, CanvasSection, StampedOp } from '@/types/canvas';

const clone = <T>(x: T): T => JSON.parse(JSON.stringify(x));

/**
 * The inverse op(s) that undo `op`, given the state BEFORE it was applied.
 * Element edits restore each touched element's prior full state (generic
 * restoreElement); add↔del; section ops invert structurally. Returns [] for ops
 * that aren't user-undoable. Pure — used to build the per-client undo stack.
 */
export function invertOp(op: CanvasOp, sections: CanvasSection[]): CanvasOp[] {
  const findEl = (id: string): { sectionId: string; element: CanvasSection['elements'][number] } | null => {
    for (const sec of sections) {
      const e = sec.elements.find((x) => x.id === id);
      if (e) return { sectionId: sec.id, element: e };
    }
    return null;
  };
  const restoreTouched = (): CanvasOp[] => {
    const out: CanvasOp[] = [];
    for (const id of opKeys(op)) {
      const f = findEl(id);
      if (f) out.push({ t: 'restoreElement', sectionId: f.sectionId, element: clone(f.element) });
    }
    return out;
  };

  switch (op.t) {
    case 'add': return [{ t: 'del', ids: [op.element.id] }];
    case 'del':
    case 'layout':
    case 'pin':
    case 'anim':
    case 'mobileClear':
    case 'z':
      return restoreTouched();
    case 'secAdd': return [{ t: 'secDel', id: op.section.id }];
    case 'secDel': {
      const idx = sections.findIndex((s) => s.id === op.id);
      if (idx < 0) return [];
      return [{ t: 'secAdd', section: clone(sections[idx]), afterId: idx > 0 ? sections[idx - 1].id : undefined }];
    }
    case 'secMove': return [{ t: 'secMove', id: op.id, dir: op.dir === 'up' ? 'down' : 'up' }];
    case 'secSettings': {
      const sec = sections.find((s) => s.id === op.id);
      if (!sec) return [];
      const prior: Partial<CanvasSection['settings']> = {};
      for (const k of Object.keys(op.patch) as (keyof CanvasSection['settings'])[]) prior[k] = sec.settings[k] as never;
      return [{ t: 'secSettings', id: op.id, patch: prior }];
    }
    case 'restoreElement': return [];
  }
}

/** The element key(s) an op touches — the unit of Last-Writer-Wins. */
export function opKeys(op: CanvasOp): string[] {
  switch (op.t) {
    case 'layout': return [op.id];
    case 'add': return [op.element.id];
    case 'del': return op.ids;
    case 'z': return op.ids;
    case 'pin': return [op.id];
    case 'anim': return [op.id];
    case 'mobileClear': return [op.id];
    case 'secAdd': return [op.section.id];
    case 'secDel': return [op.id];
    case 'secMove': return [op.id];
    case 'secSettings': return [op.id];
    case 'restoreElement': return [op.element.id];
  }
}

/**
 * Should an incoming op be applied under Last-Writer-Wins?
 * Newer lamport wins; equal lamport breaks by client id; delete always wins
 * (tombstone semantics — a concurrent move can't resurrect a deleted element).
 */
export function lwwNewer(prev: { lamport: number; client: string } | undefined, s: StampedOp): boolean {
  if (s.op.t === 'del') return true;
  if (!prev) return true;
  return s.lamport > prev.lamport || (s.lamport === prev.lamport && s.client > prev.client);
}
