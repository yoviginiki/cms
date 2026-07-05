// ═══════════════════════════════════════════════════════════════════════════
// Wrap-to-contour ([pro]): trace an image's alpha silhouette into horizontal
// BANDS in element-local coordinates. Bands feed two consumers:
//   1. the flow engine — one thin rect exclusion per band (the carving loop
//      already handles arbitrary rects), so text QUANTITY per frame is right;
//   2. a CSS shape-outside polygon (editor + publish) for the visible ragged
//      edge in single-column frames.
// ═══════════════════════════════════════════════════════════════════════════

export interface ContourBand {
  y0: number;
  y1: number;
  x0: number;
  x1: number;
}

export const CONTOUR_MAX_BANDS = 48;
const ALPHA_THRESHOLD = 24;

/** replicate the element's object-fit on a canvas and scan the alpha */
export async function traceImageContour(
  src: string,
  elW: number,
  elH: number,
  fit: 'cover' | 'contain' | 'fill' = 'cover',
): Promise<ContourBand[]> {
  const img = await new Promise<HTMLImageElement>((resolve, reject) => {
    const i = new Image();
    i.crossOrigin = 'anonymous';
    i.onload = () => resolve(i);
    i.onerror = () => reject(new Error('image load failed'));
    i.src = src;
  });

  // sample at ~2px-per-band resolution, capped
  const scale = Math.min(1, 480 / Math.max(elW, elH));
  const cw = Math.max(8, Math.round(elW * scale));
  const ch = Math.max(8, Math.round(elH * scale));
  const canvas = document.createElement('canvas');
  canvas.width = cw;
  canvas.height = ch;
  const ctx = canvas.getContext('2d', { willReadFrequently: true })!;

  // fit mapping (same rules the renderer uses)
  const ir = img.naturalWidth / img.naturalHeight;
  const er = cw / ch;
  let dw = cw, dh = ch, dx = 0, dy = 0;
  if (fit === 'contain') {
    if (ir > er) { dw = cw; dh = cw / ir; dy = (ch - dh) / 2; }
    else { dh = ch; dw = ch * ir; dx = (cw - dw) / 2; }
  } else if (fit === 'cover') {
    if (ir > er) { dh = ch; dw = ch * ir; dx = (cw - dw) / 2; }
    else { dw = cw; dh = cw / ir; dy = (ch - dh) / 2; }
  }
  ctx.drawImage(img, dx, dy, dw, dh);

  const data = ctx.getImageData(0, 0, cw, ch).data;
  const bandCount = Math.min(CONTOUR_MAX_BANDS, Math.max(8, Math.floor(elH / 8)));
  const rowsPerBand = ch / bandCount;
  const bands: ContourBand[] = [];

  for (let b = 0; b < bandCount; b++) {
    const rowStart = Math.floor(b * rowsPerBand);
    const rowEnd = Math.min(ch - 1, Math.ceil((b + 1) * rowsPerBand) - 1);
    let minX = cw, maxX = -1;
    for (let y = rowStart; y <= rowEnd; y++) {
      const off = y * cw * 4;
      for (let x = 0; x < cw; x++) {
        if (data[off + x * 4 + 3] > ALPHA_THRESHOLD) {
          if (x < minX) minX = x;
          if (x > maxX) maxX = x;
        }
      }
    }
    if (maxX >= minX) {
      bands.push({
        y0: (rowStart / ch) * elH,
        y1: ((rowEnd + 1) / ch) * elH,
        x0: (minX / cw) * elW,
        x1: ((maxX + 1) / cw) * elW,
      });
    }
  }
  return mergeBands(bands);
}

/** merge vertically-adjacent bands whose extents differ < 1.5pt (fewer rects) */
export function mergeBands(bands: ContourBand[]): ContourBand[] {
  const out: ContourBand[] = [];
  for (const b of bands) {
    const last = out[out.length - 1];
    if (last && Math.abs(last.x0 - b.x0) < 1.5 && Math.abs(last.x1 - b.x1) < 1.5 && Math.abs(last.y1 - b.y0) < 0.5) {
      last.y1 = b.y1;
      last.x0 = Math.min(last.x0, b.x0);
      last.x1 = Math.max(last.x1, b.x1);
    } else {
      out.push({ ...b });
    }
  }
  return out;
}

/**
 * CSS shape-outside polygon for a float shim (element-local px). side 'left'
 * = image sits left of the text (shim floats left, polygon follows x1 edge);
 * 'right' mirrors. Margin inflates the silhouette.
 */
export function bandsToPolygon(
  bands: ContourBand[],
  boxW: number,
  boxH: number,
  side: 'left' | 'right',
  margin = 0,
): string {
  if (!bands.length) return '';
  const pts: string[] = [];
  const cl = (v: number, max: number) => Math.max(0, Math.min(max, Math.round(v * 10) / 10));
  if (side === 'left') {
    pts.push(`0px 0px`);
    for (const b of bands) {
      const x = cl(b.x1 + margin, boxW);
      pts.push(`${x}px ${cl(b.y0 - margin, boxH)}px`);
      pts.push(`${x}px ${cl(b.y1 + margin, boxH)}px`);
    }
    pts.push(`0px ${cl(bands[bands.length - 1].y1 + margin, boxH)}px`);
  } else {
    pts.push(`${boxW}px 0px`);
    for (const b of bands) {
      const x = cl(b.x0 - margin, boxW);
      pts.push(`${x}px ${cl(b.y0 - margin, boxH)}px`);
      pts.push(`${x}px ${cl(b.y1 + margin, boxH)}px`);
    }
    pts.push(`${boxW}px ${cl(bands[bands.length - 1].y1 + margin, boxH)}px`);
  }
  return `polygon(${pts.join(', ')})`;
}

export interface WrapShim {
  side: 'left' | 'right';
  w: number;
  h: number;
  polygon: string;
}

interface GeomEl {
  x: number; y: number; width: number; height: number;
  visible?: boolean;
  textWrap?: any;
  data?: any;
}

/**
 * Visible runaround for SINGLE-COLUMN text frames: float shims with
 * shape-outside so the browser (editor AND publish) rags the text against
 * the wrap object — bands when traced (object-shape), one band for
 * bounding-box. Multi-column frames rely on engine carving alone.
 */
export function computeWrapShims(textEl: GeomEl, others: GeomEl[]): WrapShim[] {
  const data = textEl.data || {};
  if ((data.columnsInFrame || 1) !== 1) return [];
  const inset = data.textInset || { top: 0, right: 0, bottom: 0, left: 0 };
  const ax = textEl.x + (inset.left || 0);
  const ay = textEl.y + (inset.top || 0);
  const aw = textEl.width - (inset.left || 0) - (inset.right || 0);
  const ah = textEl.height - (inset.top || 0) - (inset.bottom || 0);
  if (aw <= 0 || ah <= 0) return [];

  const shims: WrapShim[] = [];
  for (const o of others) {
    if (o === textEl || o.visible === false) continue;
    const wrap = o.textWrap;
    if (!wrap || (wrap.type !== 'bounding-box' && wrap.type !== 'object-shape')) continue;
    const off = wrap.offset || {};
    const margin = Math.max(0, off.top ?? 0, off.right ?? 0, off.bottom ?? 0, off.left ?? 0);
    const ix = o.x - ax;
    const iy = o.y - ay;
    const iw = o.width;
    const ih = o.height;
    // overlap (inflated) with the text area?
    if (ix - margin >= aw || ix + iw + margin <= 0 || iy - margin >= ah || iy + ih + margin <= 0) continue;

    const side: 'left' | 'right' = ix + iw / 2 <= aw / 2 ? 'left' : 'right';
    const shimW = Math.min(aw, Math.max(0, side === 'left' ? ix + iw + margin : aw - ix + margin));
    const shimH = Math.min(ah, Math.max(0, iy + ih + margin));
    if (shimW < 2 || shimH < 2) continue;

    const rawBands: ContourBand[] = wrap.type === 'object-shape' && Array.isArray(wrap.customPath?.bands) && wrap.customPath.bands.length
      ? wrap.customPath.bands
      : [{ y0: 0, y1: ih, x0: 0, x1: iw }];
    const shimX0 = side === 'left' ? 0 : aw - shimW;
    const shimBands = rawBands
      .map((b: ContourBand) => ({
        y0: iy + b.y0 - margin,
        y1: iy + b.y1 + margin,
        x0: ix + b.x0 - margin - shimX0,
        x1: ix + b.x1 + margin - shimX0,
      }))
      .filter((b: ContourBand) => b.y1 > 0 && b.y0 < shimH);
    if (!shimBands.length) continue;
    const polygon = bandsToPolygon(shimBands, shimW, shimH, side, 0);
    if (polygon) shims.push({ side, w: Math.round(shimW * 10) / 10, h: Math.round(shimH * 10) / 10, polygon });
  }
  return shims;
}

/** trusted html for the shims (numeric-only interpolation) */
export function wrapShimsHtml(shims: WrapShim[]): string {
  return shims
    .map((s2) => `<div style="float:${s2.side};width:${s2.w}px;height:${s2.h}px;shape-outside:${s2.polygon};pointer-events:none;" aria-hidden="true"></div>`)
    .join('');
}
