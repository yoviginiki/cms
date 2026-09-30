// Moving a grid row up/down without tearing areas apart: a row moves together
// with every row an area spans across it (its "band"), and swaps with the
// whole neighbouring band.

/** Inclusive row range [a, b] that must move together with row i. */
export function rowBand(cells: string[][], i: number): [number, number] {
  let a = i, b = i, changed = true;
  while (changed) {
    changed = false;
    const names = new Set(cells.slice(a, b + 1).flat().filter(n => n !== '.'));
    cells.forEach((row, r) => {
      if ((r < a || r > b) && row.some(n => names.has(n))) {
        a = Math.min(a, r); b = Math.max(b, r); changed = true;
      }
    });
  }
  return [a, b];
}

/** New row order (indices) after moving row i one band up (-1) or down (+1); null at an edge. */
export function moveRowOrder(cells: string[][], i: number, dir: -1 | 1): number[] | null {
  const [a, b] = rowBand(cells, i);
  const idx = cells.map((_, r) => r);
  if (dir < 0) {
    if (a === 0) return null;
    const [c] = rowBand(cells, a - 1);
    return [...idx.slice(0, c), ...idx.slice(a, b + 1), ...idx.slice(c, a), ...idx.slice(b + 1)];
  }
  if (b === cells.length - 1) return null;
  const [, d] = rowBand(cells, b + 1);
  return [...idx.slice(0, a), ...idx.slice(b + 1, d + 1), ...idx.slice(a, b + 1), ...idx.slice(d + 1)];
}
