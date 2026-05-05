import type { MagElement } from '@/types/magazine';

interface GuideLine {
  type: 'vertical' | 'horizontal';
  position: number;
  label?: string;
}

interface SnapResult {
  x: number;
  y: number;
  guides: GuideLine[];
}

const THRESHOLD = 4;

export function calculateSmartGuides(
  dragged: { x: number; y: number; width: number; height: number },
  others: MagElement[],
  pageWidth: number,
  pageHeight: number,
  margins: { top: number; right: number; bottom: number; left: number },
): SnapResult {
  const guides: GuideLine[] = [];
  let { x, y } = dragged;
  const cx = x + dragged.width / 2;
  const cy = y + dragged.height / 2;
  const r = x + dragged.width;
  const b = y + dragged.height;

  // Page center
  const pcx = pageWidth / 2;
  const pcy = pageHeight / 2;
  if (Math.abs(cx - pcx) < THRESHOLD) { x = pcx - dragged.width / 2; guides.push({ type: 'vertical', position: pcx }); }
  if (Math.abs(cy - pcy) < THRESHOLD) { y = pcy - dragged.height / 2; guides.push({ type: 'horizontal', position: pcy }); }

  // Page margins
  if (Math.abs(x - margins.left) < THRESHOLD) { x = margins.left; guides.push({ type: 'vertical', position: margins.left }); }
  if (Math.abs(r - (pageWidth - margins.right)) < THRESHOLD) { x = pageWidth - margins.right - dragged.width; guides.push({ type: 'vertical', position: pageWidth - margins.right }); }
  if (Math.abs(y - margins.top) < THRESHOLD) { y = margins.top; guides.push({ type: 'horizontal', position: margins.top }); }
  if (Math.abs(b - (pageHeight - margins.bottom)) < THRESHOLD) { y = pageHeight - margins.bottom - dragged.height; guides.push({ type: 'horizontal', position: pageHeight - margins.bottom }); }

  // Other elements
  for (const el of others) {
    const eEdges = { l: el.x, r: el.x + el.width, t: el.y, b: el.y + el.height, cx: el.x + el.width / 2, cy: el.y + el.height / 2 };

    // Vertical alignment
    for (const [mv, label] of [[x, 'left'], [r, 'right'], [cx, 'center']] as [number, string][]) {
      for (const [ev, ] of [[eEdges.l, 'left'], [eEdges.r, 'right'], [eEdges.cx, 'center']] as [number, string][]) {
        if (Math.abs(mv - ev) < THRESHOLD) {
          if (label === 'left') x = ev;
          else if (label === 'right') x = ev - dragged.width;
          else x = ev - dragged.width / 2;
          guides.push({ type: 'vertical', position: ev });
        }
      }
    }

    // Horizontal alignment
    for (const [mv, label] of [[y, 'top'], [b, 'bottom'], [cy, 'center']] as [number, string][]) {
      for (const [ev, ] of [[eEdges.t, 'top'], [eEdges.b, 'bottom'], [eEdges.cy, 'center']] as [number, string][]) {
        if (Math.abs(mv - ev) < THRESHOLD) {
          if (label === 'top') y = ev;
          else if (label === 'bottom') y = ev - dragged.height;
          else y = ev - dragged.height / 2;
          guides.push({ type: 'horizontal', position: ev });
        }
      }
    }
  }

  return { x, y, guides };
}

export function snapToGrid(value: number, gridSize: number): number {
  return Math.round(value / gridSize) * gridSize;
}
