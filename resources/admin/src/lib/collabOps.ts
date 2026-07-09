import type { CanvasOp, StampedOp } from '@/types/canvas';

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
