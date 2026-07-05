// GOLDEN TESTS — DTP save/load round-trip integrity (audit W0-6)
// A field that survives pagesToDtpApi -> dtpApiToPages is truly persisted.

import { describe, it, expect } from 'vitest';
import { dtpApiToPages, pagesToDtpApi, normalizeMasterPages } from './dtpAdapters';
import type { MagElement, MagPageData } from '@/types/magazine';
import { DEFAULT_ELEMENT_STYLE, DEFAULT_TYPOGRAPHY } from '@/types/magazine';

function el(partial: Partial<MagElement>): MagElement {
  return {
    id: crypto.randomUUID(), type: 'text_frame', name: 'Body',
    data: { content: '<p>hello world</p>', columnsInFrame: 2, columnGap: 14, textInset: { top: 8, right: 8, bottom: 8, left: 8 } },
    x: 10, y: 20, width: 300, height: 200, rotation: 0, scaleX: 1, scaleY: 1,
    zIndex: 3, locked: false, visible: true, layerName: null,
    style: structuredClone(DEFAULT_ELEMENT_STYLE),
    typography: { ...DEFAULT_TYPOGRAPHY, fontSize: 16.5, hyphenation: true },
    textWrap: { type: 'bounding-box', offset: { top: 6, right: 6, bottom: 6, left: 6 }, side: 'both', customPath: null, invert: false },
    threadId: 'thread-1', threadOrder: 2, pageNumber: 1, onMaster: false,
    positionMode: 'free', spanMode: 'page', parentId: null, children: [], responsiveOverrides: {},
    ...partial,
  };
}

function page(elements: MagElement[]): MagPageData {
  return {
    id: crypto.randomUUID(), pageNumber: 1,
    pageSize: { width: 595, height: 842 },
    margins: { top: 36, right: 36, bottom: 36, left: 36 },
    bleed: { top: 9, right: 9, bottom: 9, left: 9 },
    columns: { count: 1, gutter: 12 }, baselineGrid: { increment: 14, start: 36 },
    isMaster: false, masterPageId: 'master-a', spreadWith: null,
    backgroundColor: '#ffffff', backgroundAssetId: null, elements,
  };
}

function roundTrip(pages: MagPageData[], extras?: any) {
  const payload = pagesToDtpApi(pages, [], [], { layoutMode: 'book' }, {}, extras);
  return { payload, pages: dtpApiToPages(payload) };
}

describe('DTP adapter round-trip (W0-6)', () => {
  it('text frame: threading, flow bookkeeping, wrap, typography survive', () => {
    const src = el({
      data: { content: '<p>slice</p>', columnsInFrame: 3, columnGap: 10, _autoFlow: true, _flowHash: 'abc123', textInset: { top: 4, right: 4, bottom: 4, left: 4 }, verticalAlign: 'center' },
      scaleX: 1.2, scaleY: 0.9,
    });
    const { pages } = roundTrip([page([src])]);
    const out = pages[0].elements[0];
    expect(out.threadId).toBe('thread-1');
    expect(out.threadOrder).toBe(2);
    expect((out.data as any)._autoFlow).toBe(true);
    expect((out.data as any)._flowHash).toBe('abc123');
    expect(out.textWrap.type).toBe('bounding-box');
    expect(out.textWrap.offset.top).toBe(6);
    expect(out.typography?.fontSize).toBe(16.5);
    expect(out.typography?.hyphenation).toBe(true);
    expect((out.data as any).columnsInFrame).toBe(3);
    expect((out.data as any).verticalAlign).toBe('center');
    expect(out.scaleX).toBeCloseTo(1.2);
    expect(out.scaleY).toBeCloseTo(0.9);
  });

  it('image frame: content-mode fields and filters survive (previously lost)', () => {
    const img = el({
      type: 'image_frame',
      typography: null, threadId: null, threadOrder: null,
      data: {
        src: '/media/x.webp', alt: 'Alt', fit: 'fit', focalPoint: { x: 0.3, y: 0.7 },
        imageOffsetX: 12, imageOffsetY: -8, imageScale: 1.4, imageRotation: 15,
        filters: { brightness: 90, contrast: 110, saturation: 100, grayscale: true, duotone: null },
        clipShape: 'ellipse',
      },
    });
    const { pages } = roundTrip([page([img])]);
    const out = pages[0].elements[0];
    expect((out.data as any).imageOffsetX).toBe(12);
    expect((out.data as any).imageOffsetY).toBe(-8);
    expect((out.data as any).imageScale).toBeCloseTo(1.4);
    expect((out.data as any).imageRotation).toBe(15);
    expect((out.data as any).filters.grayscale).toBe(true);
    expect((out.data as any).clipShape).toBe('ellipse');
    expect((out.data as any).focalPoint).toEqual({ x: 0.3, y: 0.7 });
    expect((out.data as any).fit).toBe('fit');
  });

  it('styles and master pages ride in meta', () => {
    const styles = [{ id: 's1', name: 'Body', type: 'paragraph', properties: { fontSize: 15 }, basedOnId: null, nextStyleId: null, isDefault: true }];
    const master = { ...page([el({ onMaster: true })]), isMaster: true, pageNumber: -1 };
    const { payload } = roundTrip([page([])], { styles, masterPages: [master] });
    const meta = payload.meta as any;
    expect(meta.styles).toHaveLength(1);
    expect(meta.styles[0].properties.fontSize).toBe(15);
    expect(meta.masterPages).toHaveLength(1);
    expect(meta.masterPages[0].isMaster).toBe(true);
    expect(meta.masterPages[0].elements).toHaveLength(1);
  });

  it('W1-4: pages pair into real spreads with verso/recto sides', () => {
    const pages = [page([]), page([]), page([]), page([]), page([])].map((p, i) => ({ ...p, id: crypto.randomUUID(), pageNumber: i + 1 }));
    // standalone cover: [1] [2,3] [4,5]
    const out = pagesToDtpApi(pages, [], [], { coverMode: 'standalone' }, {}) as any;
    expect(out.spreads).toHaveLength(3);
    expect(out.pages[0].side).toBe('single');
    expect(out.pages[1].side).toBe('left');
    expect(out.pages[2].side).toBe('right');
    expect(out.pages[1].spread_id).toBe(out.pages[2].spread_id);
    expect(out.pages[0].spread_id).not.toBe(out.pages[1].spread_id);
    // spread cover: [1,2] [3,4] [5]
    const out2 = pagesToDtpApi(pages, [], [], { coverMode: 'spread' }, {}) as any;
    expect(out2.spreads).toHaveLength(3);
    expect(out2.pages[0].side).toBe('left');
    expect(out2.pages[4].side).toBe('single');
  });

  it('tables round-trip with data and specific type (previously published empty)', () => {
    const tbl = el({
      type: 'table_frame', typography: null, threadId: null, threadOrder: null,
      data: { headers: ['Name', 'Qty'], rows: [['Washi', '3'], ['Vermilion', '1']], stripes: false, borderColor: '#333333' },
    });
    const { pages } = roundTrip([page([tbl])]);
    const out = pages[0].elements[0];
    expect(out.type).toBe('table_frame'); // _magType restore (was flattened)
    expect((out.data as any).headers).toEqual(['Name', 'Qty']);
    expect((out.data as any).rows).toEqual([['Washi', '3'], ['Vermilion', '1']]);
    expect((out.data as any).stripes).toBe(false);
    expect((out.data as any).borderColor).toBe('#333333');
  });

  it('clip-shape image types survive reload via _magType', () => {
    const img = el({ type: 'circular_image', typography: null, threadId: null, threadOrder: null, data: { src: '/m/x.webp', alt: '', fit: 'fill', focalPoint: { x: 0.5, y: 0.5 } } });
    const { pages } = roundTrip([page([img])]);
    expect(pages[0].elements[0].type).toBe('circular_image');
  });

  it('partial persisted styles merge with defaults (crash fix: cornerRadius.tl)', () => {
    const payload = pagesToDtpApi([page([el({})])], [], [], {}, {});
    // simulate a frame saved with ONLY a fill (the seeded-headline case)
    (payload.frames as any[])[0].style = { fill: { color: '#ffffff' } };
    const pages2 = dtpApiToPages(payload);
    const st = pages2[0].elements[0].style;
    expect(st.fill.color).toBe('#ffffff');
    expect(st.cornerRadius.tl).toBe(0);
    expect(st.stroke.width).toBe(0);
    expect(st.opacity).toBe(1);
  });

  it('API-seeded master elements normalize (children/textWrap/style)', () => {
    const masters = normalizeMasterPages([{ id: 'm1', pageNumber: -1, elements: [
      { id: 'e1', type: 'running_header', x: 0, y: 0, width: 100, height: 20, data: { customText: 'F' } },
    ] }]);
    const el2 = masters[0].elements[0];
    expect(el2.children).toEqual([]);
    expect(el2.textWrap.type).toBe('none');
    expect(el2.style.cornerRadius.tl).toBe(0);
    expect(el2.onMaster).toBe(true);
  });

  it('ruler guides round-trip via page metadata (W2-1)', () => {
    const pg = { ...page([]), _guides: { v: [120, 250.5], h: [400] } } as any;
    const { pages } = roundTrip([pg]);
    expect((pages[0] as any)._guides).toEqual({ v: [120, 250.5], h: [400] });
  });

  it('groups round-trip: children serialize flat and reassemble (W2)', () => {
    const childA = el({ id: 'c-a', threadId: null, threadOrder: null, x: 20, y: 20 });
    const childB = el({ id: 'c-b', type: 'rectangle', typography: null, threadId: null, threadOrder: null, x: 120, y: 40, data: { fillColor: '#ccc' } });
    const grp = el({
      id: 'g-1', type: 'group', typography: null, threadId: null, threadOrder: null,
      x: 20, y: 20, width: 180, height: 100, data: { label: 'Group' },
      children: [ { ...childA, parentId: 'g-1' }, { ...childB, parentId: 'g-1' } ],
    });
    const { payload, pages } = roundTrip([page([grp])]);
    expect((payload.frames as any[]).length).toBe(3);
    const out = pages[0].elements;
    expect(out).toHaveLength(1);
    expect(out[0].type).toBe('group');
    expect(out[0].children).toHaveLength(2);
    expect(out[0].children.map((c) => c.x).sort((x, y) => x - y)).toEqual([20, 120]);
  });

  it('sections round-trip via page metadata (W2-11)', () => {
    const pg = { ...page([]), _section: { startAt: 1, format: 'roman-lower' } } as any;
    const { pages } = roundTrip([pg]);
    expect((pages[0] as any)._section).toEqual({ startAt: 1, format: 'roman-lower' });
  });

  it('page master assignment survives', () => {
    const { pages } = roundTrip([page([])]);
    expect(pages[0].masterPageId).toBe('master-a');
  });
});
