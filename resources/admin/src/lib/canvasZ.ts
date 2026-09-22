import type { CanvasElement } from '@/types/canvas';

/**
 * One-step layer reorder for a section: the selected elements swap places with
 * the nearest unselected neighbour above (forward) or below (backward) in the
 * section's z-order. Pure and deterministic (same input → same output), so it
 * can run identically on every collab client from the same op.
 *
 * Elements are first renormalised to a dense 1..n z-order (stable by current
 * zIndex, then array order) so ties never make the step a no-op.
 */
export function stepZ(elements: CanvasElement[], ids: string[], dir: 'forward' | 'backward'): CanvasElement[] {
  if (!elements.length) return elements;
  const sel = new Set(ids);
  const order = elements
    .map((el, i) => ({ el, i }))
    .sort((a, b) => (a.el.zIndex - b.el.zIndex) || (a.i - b.i))
    .map(x => x.el);

  // Swap each selected element with its unselected neighbour, walking from the
  // leading edge so a contiguous selected run moves as a group past one neighbour.
  if (dir === 'forward') {
    for (let i = order.length - 2; i >= 0; i--) {
      if (sel.has(order[i].id) && !sel.has(order[i + 1].id)) [order[i], order[i + 1]] = [order[i + 1], order[i]];
    }
  } else {
    for (let i = 1; i < order.length; i++) {
      if (sel.has(order[i].id) && !sel.has(order[i - 1].id)) [order[i - 1], order[i]] = [order[i], order[i - 1]];
    }
  }
  const z = new Map(order.map((el, i) => [el.id, i + 1]));
  return elements.map(el => (el.zIndex === z.get(el.id) ? el : { ...el, zIndex: z.get(el.id)! }));
}

/** Elements whose zIndex differs between two same-shaped arrays (for undo inverses). */
export function changedZ(before: CanvasElement[], after: CanvasElement[]): CanvasElement[] {
  const map = new Map(after.map(e => [e.id, e.zIndex]));
  return before.filter(e => map.get(e.id) !== e.zIndex);
}
