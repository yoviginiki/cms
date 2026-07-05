import { describe, it, expect } from 'vitest';
import { mergeBands, bandsToPolygon, computeWrapShims, wrapShimsHtml } from './contourTrace';

describe('wrap-to-contour helpers ([pro])', () => {
  it('mergeBands collapses similar adjacent bands', () => {
    const merged = mergeBands([
      { y0: 0, y1: 8, x0: 10, x1: 50 },
      { y0: 8, y1: 16, x0: 10.5, x1: 50.5 },   // ~same extent → merge
      { y0: 16, y1: 24, x0: 30, x1: 50 },      // different → new band
    ]);
    expect(merged).toHaveLength(2);
    expect(merged[0].y1).toBe(16);
  });

  it('bandsToPolygon follows the silhouette on the chosen side', () => {
    const poly = bandsToPolygon([{ y0: 0, y1: 20, x0: 5, x1: 40 }], 100, 50, 'right');
    expect(poly).toContain('polygon(');
    expect(poly).toContain('100px 0px'); // anchored to the right edge
    expect(poly).toContain('5px');       // follows x0 of the band
  });

  it('computeWrapShims: right-side image in a 1-col frame → right float shim', () => {
    const text = { x: 0, y: 0, width: 300, height: 400, data: { columnsInFrame: 1, textInset: { top: 0, right: 0, bottom: 0, left: 0 } } };
    const img = {
      x: 200, y: 50, width: 100, height: 100, visible: true,
      textWrap: { type: 'object-shape', offset: { top: 4, right: 4, bottom: 4, left: 4 }, customPath: { bands: [{ y0: 0, y1: 100, x0: 20, x1: 100 }] } },
    };
    const shims = computeWrapShims(text as any, [img] as any);
    expect(shims).toHaveLength(1);
    expect(shims[0].side).toBe('right');
    expect(shims[0].h).toBeCloseTo(154, 0); // 50 + 100 + 4 margin
    const html = wrapShimsHtml(shims);
    expect(html).toContain('float:right');
    expect(html).toContain('shape-outside:polygon(');
  });

  it('multi-column frames get NO shims (engine carving only)', () => {
    const text = { x: 0, y: 0, width: 300, height: 400, data: { columnsInFrame: 2 } };
    const img = { x: 200, y: 50, width: 100, height: 100, textWrap: { type: 'bounding-box', offset: {} } };
    expect(computeWrapShims(text as any, [img] as any)).toEqual([]);
  });

  it('non-overlapping and none-type wraps are ignored', () => {
    const text = { x: 0, y: 0, width: 300, height: 400, data: {} };
    expect(computeWrapShims(text as any, [
      { x: 900, y: 900, width: 50, height: 50, textWrap: { type: 'bounding-box', offset: {} } },
      { x: 10, y: 10, width: 50, height: 50, textWrap: { type: 'none', offset: {} } },
    ] as any)).toEqual([]);
  });
});
