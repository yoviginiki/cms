/**
 * M3 DTP Canvas Prototype — Snap Engine
 *
 * Generates snap lines from page geometry and other frames,
 * and snaps a moving/resizing frame to them.
 * Prototype-local — no persistence, no API.
 */
import type { DtpFrame, DtpPage } from './mockDocument';

export interface SnapLine {
  axis: 'x' | 'y';       // Which axis this line is on
  position: number;       // Position in page coordinates
  type: 'page-edge' | 'margin' | 'center' | 'frame-edge' | 'frame-center';
  label?: string;
}

export interface SnapResult {
  x: number;
  y: number;
  activeLines: SnapLine[];  // Lines that caused snapping (for visual feedback)
}

const BASE_SNAP_TOLERANCE = 5;  // px base tolerance

/**
 * Generate all snap lines for a page + other frames.
 */
export function getSnapLines(
  page: DtpPage,
  frames: DtpFrame[],
  excludeIds: string[],
): SnapLine[] {
  const lines: SnapLine[] = [];

  // Page edges
  lines.push({ axis: 'x', position: 0, type: 'page-edge', label: 'Page left' });
  lines.push({ axis: 'x', position: page.width, type: 'page-edge', label: 'Page right' });
  lines.push({ axis: 'y', position: 0, type: 'page-edge', label: 'Page top' });
  lines.push({ axis: 'y', position: page.height, type: 'page-edge', label: 'Page bottom' });

  // Page center
  lines.push({ axis: 'x', position: page.width / 2, type: 'center', label: 'Page center H' });
  lines.push({ axis: 'y', position: page.height / 2, type: 'center', label: 'Page center V' });

  // Margin guides
  lines.push({ axis: 'x', position: page.margins.left, type: 'margin', label: 'Margin left' });
  lines.push({ axis: 'x', position: page.width - page.margins.right, type: 'margin', label: 'Margin right' });
  lines.push({ axis: 'y', position: page.margins.top, type: 'margin', label: 'Margin top' });
  lines.push({ axis: 'y', position: page.height - page.margins.bottom, type: 'margin', label: 'Margin bottom' });

  // Other frame edges and centers
  for (const frame of frames) {
    if (excludeIds.includes(frame.id)) continue;
    // X axis: left edge, right edge, center
    lines.push({ axis: 'x', position: frame.x, type: 'frame-edge' });
    lines.push({ axis: 'x', position: frame.x + frame.width, type: 'frame-edge' });
    lines.push({ axis: 'x', position: frame.x + frame.width / 2, type: 'frame-center' });
    // Y axis: top edge, bottom edge, center
    lines.push({ axis: 'y', position: frame.y, type: 'frame-edge' });
    lines.push({ axis: 'y', position: frame.y + frame.height, type: 'frame-edge' });
    lines.push({ axis: 'y', position: frame.y + frame.height / 2, type: 'frame-center' });
  }

  return lines;
}

/**
 * Snap a frame's position to nearby snap lines.
 * Tests frame's left, right, center (x) and top, bottom, center (y).
 * Tolerance is zoom-corrected: at low zoom, snap radius is larger in canvas space.
 */
export function snapPosition(
  x: number, y: number, width: number, height: number,
  snapLines: SnapLine[],
  zoom: number = 1,
): SnapResult {
  const tolerance = BASE_SNAP_TOLERANCE / zoom;
  let snappedX = x;
  let snappedY = y;
  const activeLines: SnapLine[] = [];

  // Frame reference points
  const xPoints = [x, x + width / 2, x + width];           // left, center, right
  const yPoints = [y, y + height / 2, y + height];          // top, center, bottom

  // Snap X
  let bestDx = tolerance + 1;
  for (const line of snapLines) {
    if (line.axis !== 'x') continue;
    for (let i = 0; i < xPoints.length; i++) {
      const dist = Math.abs(xPoints[i] - line.position);
      if (dist < bestDx) {
        bestDx = dist;
        snappedX = x + (line.position - xPoints[i]);
        // Clear previous X snap lines, add this one
        const idx = activeLines.findIndex(l => l.axis === 'x');
        if (idx >= 0) activeLines.splice(idx, 1);
        activeLines.push(line);
      }
    }
  }

  // Snap Y
  let bestDy = tolerance + 1;
  for (const line of snapLines) {
    if (line.axis !== 'y') continue;
    for (let i = 0; i < yPoints.length; i++) {
      const dist = Math.abs(yPoints[i] - line.position);
      if (dist < bestDy) {
        bestDy = dist;
        snappedY = y + (line.position - yPoints[i]);
        const idx = activeLines.findIndex(l => l.axis === 'y');
        if (idx >= 0) activeLines.splice(idx, 1);
        activeLines.push(line);
      }
    }
  }

  return { x: snappedX, y: snappedY, activeLines };
}

/**
 * Align frames to a reference bound.
 */
export function alignFrames(
  frames: DtpFrame[],
  direction: 'left' | 'center-h' | 'right' | 'top' | 'center-v' | 'bottom',
  toPage?: DtpPage,
): Partial<DtpFrame>[] {
  if (frames.length === 0) return [];

  // Calculate bounds
  let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
  if (toPage) {
    minX = 0; minY = 0; maxX = toPage.width; maxY = toPage.height;
  } else {
    for (const f of frames) {
      minX = Math.min(minX, f.x);
      minY = Math.min(minY, f.y);
      maxX = Math.max(maxX, f.x + f.width);
      maxY = Math.max(maxY, f.y + f.height);
    }
  }

  return frames.map(f => {
    switch (direction) {
      case 'left': return { x: minX };
      case 'right': return { x: maxX - f.width };
      case 'center-h': return { x: (minX + maxX) / 2 - f.width / 2 };
      case 'top': return { y: minY };
      case 'bottom': return { y: maxY - f.height };
      case 'center-v': return { y: (minY + maxY) / 2 - f.height / 2 };
    }
  });
}

/**
 * Distribute frames evenly along an axis.
 * Requires 3+ frames.
 */
export function distributeFrames(
  frames: DtpFrame[],
  axis: 'horizontal' | 'vertical',
): Partial<DtpFrame>[] {
  if (frames.length < 3) return frames.map(() => ({}));

  const sorted = [...frames].sort((a, b) =>
    axis === 'horizontal' ? a.x - b.x : a.y - b.y
  );

  if (axis === 'horizontal') {
    const totalWidth = sorted.reduce((sum, f) => sum + f.width, 0);
    const firstLeft = sorted[0].x;
    const lastRight = sorted[sorted.length - 1].x + sorted[sorted.length - 1].width;
    const totalSpace = lastRight - firstLeft - totalWidth;
    const gap = totalSpace / (sorted.length - 1);

    let currentX = firstLeft;
    return sorted.map((f, i) => {
      const result = i === 0 ? {} : { x: Math.round(currentX) };
      currentX += f.width + gap;
      return result;
    });
  } else {
    const totalHeight = sorted.reduce((sum, f) => sum + f.height, 0);
    const firstTop = sorted[0].y;
    const lastBottom = sorted[sorted.length - 1].y + sorted[sorted.length - 1].height;
    const totalSpace = lastBottom - firstTop - totalHeight;
    const gap = totalSpace / (sorted.length - 1);

    let currentY = firstTop;
    return sorted.map((f, i) => {
      const result = i === 0 ? {} : { y: Math.round(currentY) };
      currentY += f.height + gap;
      return result;
    });
  }
}
