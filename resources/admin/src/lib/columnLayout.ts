/**
 * P5 column geometry on a 12-unit grid. Row column widths are stored as
 * `col_spans` — an array of integers summing to 12, one per column. When
 * absent, the row falls back to its `layout` preset; `presetToSpans` seeds the
 * grid from that preset the first time a user drags a divider.
 */

export const GRID_UNITS = 12;

/** Even-as-possible split of the 12 grid units across `count` columns. */
export function equalSpans(count: number): number[] {
  const n = Math.max(1, Math.min(GRID_UNITS, Math.floor(count)));
  const base = Math.floor(GRID_UNITS / n);
  const spans = Array(n).fill(base);
  let remainder = GRID_UNITS - base * n;
  for (let i = 0; remainder > 0; i++, remainder--) spans[i] += 1; // front-load the remainder
  return spans;
}

/** Map a legacy `layout` preset (+ column count) to 12-grid spans. */
export function presetToSpans(layout: string | undefined, count: number): number[] {
  const map: Record<string, number[]> = {
    '1': [12],
    '1/1': [12],
    '1/2+1/2': [6, 6],
    '1/3+2/3': [4, 8],
    '2/3+1/3': [8, 4],
    '1/3+1/3+1/3': [4, 4, 4],
    '1/4+1/4+1/4+1/4': [3, 3, 3, 3],
    '1/4+3/4': [3, 9],
    '3/4+1/4': [9, 3],
  };
  const preset = layout ? map[layout] : undefined;
  return preset ? [...preset] : equalSpans(count);
}

/**
 * Coerce arbitrary input into a valid span array of exactly `count` integers,
 * each ≥ 1, summing to 12. Guards the render path against malformed data.
 */
export function normalizeSpans(input: unknown, count: number): number[] {
  const n = Math.max(1, Math.min(GRID_UNITS, Math.floor(count)));
  let spans = Array.isArray(input)
    ? input.map((v) => Math.floor(Number(v))).filter((v) => Number.isFinite(v))
    : [];

  if (spans.length < n) spans = equalSpans(n);
  else spans = spans.slice(0, n);

  spans = spans.map((v) => Math.max(1, Math.min(GRID_UNITS, v)));

  // Rebalance to sum exactly 12 without dropping any column below 1.
  let sum = spans.reduce((a, b) => a + b, 0);
  let guard = 0;
  while (sum !== GRID_UNITS && guard++ < 100) {
    if (sum > GRID_UNITS) {
      const i = spans.indexOf(Math.max(...spans));
      if (spans[i] > 1) { spans[i] -= 1; sum -= 1; } else break;
    } else {
      const i = spans.indexOf(Math.min(...spans));
      spans[i] += 1; sum += 1;
    }
  }
  return spans;
}

/**
 * Move `delta` grid units across the divider between column `i` and `i+1`
 * (positive = column `i` grows). Both neighbours stay ≥ 1; the total is
 * preserved so the row always fills 12 units.
 */
export function resizeSpans(spans: number[], i: number, delta: number): number[] {
  if (i < 0 || i >= spans.length - 1) return spans;
  const next = [...spans];
  // clamp delta so neither neighbour drops below 1
  const maxGrow = next[i + 1] - 1;
  const maxShrink = next[i] - 1;
  const d = Math.max(-maxShrink, Math.min(maxGrow, Math.round(delta)));
  next[i] += d;
  next[i + 1] -= d;
  return next;
}

/** `grid-template-columns` value for a span array, e.g. [4,8] → "4fr 8fr". */
export function spansToGridTemplate(spans: number[]): string {
  return spans.map((s) => `${s}fr`).join(' ');
}

/**
 * The CSS `order` for the column originally at index `i`, given a mobile
 * `stack_order` permutation (display order → original index). Columns not
 * named in the permutation keep their natural order.
 */
export function orderFor(stackOrder: number[] | undefined, i: number): number {
  if (!Array.isArray(stackOrder) || stackOrder.length === 0) return i;
  const pos = stackOrder.indexOf(i);
  return pos === -1 ? i : pos;
}

/** True when a stack order is a real reordering (not the identity permutation). */
export function isCustomStackOrder(stackOrder: number[] | undefined, count: number): boolean {
  if (!Array.isArray(stackOrder) || stackOrder.length !== count) return false;
  return stackOrder.some((v, idx) => v !== idx);
}
