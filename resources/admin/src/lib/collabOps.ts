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
 * Runtime validation for ops arriving from peers over the wire. A malformed
 * payload (unknown `t`, missing id/element fields) must never reach `applyOp`
 * or `opKeys` — those trust the `CanvasOp` shape and would throw on garbage.
 * Validates exactly the fields `opKeys`/`applyOp` dereference for each variant.
 */
export function isCanvasOp(op: unknown): op is CanvasOp {
  if (!op || typeof op !== 'object') return false;
  const o = op as Record<string, unknown>;
  const isStr = (v: unknown) => typeof v === 'string';
  const isStrArr = (v: unknown) => Array.isArray(v) && v.every(isStr);
  const hasId = (v: unknown) => !!v && typeof v === 'object' && isStr((v as Record<string, unknown>).id);
  switch (o.t) {
    case 'layout': case 'pin': case 'anim': case 'mobileClear': return isStr(o.id);
    case 'del': case 'z': return isStrArr(o.ids);
    case 'add': return hasId(o.element);
    case 'restoreElement': return hasId(o.element) && isStr(o.sectionId);
    case 'secAdd': return hasId(o.section);
    case 'secDel': case 'secMove': case 'secSettings': return isStr(o.id);
    default: return false;
  }
}

/** A well-formed stamped op from a peer: valid op + finite lamport + client id. */
export function isStampedOp(s: unknown): s is StampedOp {
  if (!s || typeof s !== 'object') return false;
  const st = s as Record<string, unknown>;
  return Number.isFinite(st.lamport) && typeof st.client === 'string' && isCanvasOp(st.op);
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
