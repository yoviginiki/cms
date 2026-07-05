import { describe, it, expect } from 'vitest';
import { runPreflight } from './magazinePreflight';
import type { MagPageData, MagElement } from '@/types/magazine';

const el = (over: Partial<MagElement>): MagElement => ({
  id: over.id || crypto.randomUUID(), type: 'text_frame', name: null,
  data: { content: '<p>words here</p>' },
  x: 36, y: 36, width: 200, height: 100, rotation: 0, scaleX: 1, scaleY: 1,
  zIndex: 1, locked: false, visible: true, layerName: null,
  style: {} as any, typography: null,
  textWrap: { type: 'none', offset: { top: 0, right: 0, bottom: 0, left: 0 }, side: 'both', customPath: null, invert: false },
  threadId: null, threadOrder: null, pageNumber: 1, onMaster: false,
  positionMode: 'free', spanMode: 'page', parentId: null, children: [], responsiveOverrides: {},
  ...over,
});

const page = (n: number, elements: MagElement[]): MagPageData => ({
  id: `p${n}`, pageNumber: n, pageSize: { width: 595, height: 842 },
  margins: { top: 36, right: 36, bottom: 36, left: 36 },
  bleed: { top: 9, right: 9, bottom: 9, left: 9 },
  columns: { count: 1, gutter: 12 }, baselineGrid: { increment: 14, start: 36 },
  isMaster: false, masterPageId: null, spreadWith: null,
  backgroundColor: '#fff', backgroundAssetId: null, elements,
});

describe('runPreflight (W3 preflight v2)', () => {
  it('flags overset on the LAST frame of the chain', () => {
    const pages = [
      page(1, [el({ id: 'a', threadId: 't1', threadOrder: 0 })]),
      page(2, [el({ id: 'b', threadId: 't1', threadOrder: 1, pageNumber: 2 })]),
    ];
    const issues = runPreflight(pages, { t1: true });
    const ov = issues.find((i) => i.code === 'overset')!;
    expect(ov.severity).toBe('error');
    expect(ov.pageNumber).toBe(2);
    expect(ov.elementId).toBe('b');
  });

  it('flags empty text frames but not auto-created continuations', () => {
    const pages = [page(1, [
      el({ id: 'empty', data: { content: '<p>   </p>' } }),
      el({ id: 'auto', data: { content: '', _autoFlow: true } }),
    ])];
    const issues = runPreflight(pages, {});
    expect(issues.some((i) => i.code === 'empty-text' && i.elementId === 'empty')).toBe(true);
    expect(issues.some((i) => i.elementId === 'auto')).toBe(false);
  });

  it('flags missing image src as error, missing alt as warning', () => {
    const pages = [page(1, [
      el({ id: 'noimg', type: 'image_frame', data: { src: '' } }),
      el({ id: 'noalt', type: 'image_frame', data: { src: '/media/x/y', alt: '' } }),
    ])];
    const issues = runPreflight(pages, {});
    expect(issues.find((i) => i.elementId === 'noimg')!.severity).toBe('error');
    expect(issues.find((i) => i.elementId === 'noalt')!.code).toBe('no-alt');
  });

  it('flags pasteboard-parked elements and sorts errors first', () => {
    const pages = [page(1, [
      el({ id: 'parked', x: 700, data: { content: '<p>staged</p>' } }),
      el({ id: 'noimg', type: 'image_frame', data: { src: '' } }),
    ])];
    const issues = runPreflight(pages, {});
    expect(issues[0].severity).toBe('error');
    expect(issues.some((i) => i.code === 'pasteboard' && i.elementId === 'parked')).toBe(true);
  });

  it('clean document produces no issues', () => {
    const pages = [page(1, [
      el({}),
      el({ type: 'image_frame', data: { src: '/media/a/b', alt: 'A photo' } }),
    ])];
    expect(runPreflight(pages, {})).toEqual([]);
  });
});
