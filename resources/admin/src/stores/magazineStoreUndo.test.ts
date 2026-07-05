// GOLDEN TESTS — undo/redo coverage (audit W0-5)
// Pins that the highest-frequency mutation path (updateElement) snapshots
// per GESTURE, that styles are inside history, and that clipboard works.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useMagazineStore } from './magazineStore';
import type { MagPageData } from '@/types/magazine';

function makeDoc(): MagPageData[] {
  const s = useMagazineStore.getState();
  s.setDocument(
    [{
      id: 'page-1', pageNumber: 1,
      pageSize: { width: 595, height: 842 },
      margins: { top: 36, right: 36, bottom: 36, left: 36 },
      bleed: { top: 9, right: 9, bottom: 9, left: 9 },
      columns: { count: 1, gutter: 12 },
      baselineGrid: { increment: 14, start: 36 },
      isMaster: false, masterPageId: null, spreadWith: null,
      backgroundColor: '#fff', backgroundAssetId: null, elements: [],
    }],
    [],
  );
  return useMagazineStore.getState().pages;
}

describe('magazineStore undo/redo (W0-5)', () => {
  beforeEach(() => {
    vi.useFakeTimers(); // keep requestFlow's debounce from firing mid-test
    makeDoc();
  });

  it('geometry change via updateElement is undoable', () => {
    const s = useMagazineStore.getState();
    const id = s.addElement('text_frame', 10, 10, 200, 100);
    const before = useMagazineStore.getState().pages[0].elements[0].x;
    vi.setSystemTime(Date.now() + 1000); // new gesture
    useMagazineStore.getState().updateElement(id, { x: 300 });
    expect(useMagazineStore.getState().pages[0].elements[0].x).toBe(300);
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().pages[0].elements[0].x).toBe(before);
    useMagazineStore.getState().redo();
    expect(useMagazineStore.getState().pages[0].elements[0].x).toBe(300);
  });

  it('a rapid drag gesture coalesces into ONE undo step', () => {
    const s = useMagazineStore.getState();
    const id = s.addElement('text_frame', 10, 10, 200, 100);
    vi.setSystemTime(Date.now() + 1000);
    const depth = useMagazineStore.getState().undoStack.length;
    // simulate pointermove burst: same element, same keys, <600ms apart
    for (let i = 1; i <= 20; i++) useMagazineStore.getState().updateElement(id, { x: 10 + i, y: 10 + i });
    expect(useMagazineStore.getState().undoStack.length).toBe(depth + 1);
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().pages[0].elements[0].x).toBe(10);
  });

  it('separate gestures snapshot separately', () => {
    const s = useMagazineStore.getState();
    const id = s.addElement('text_frame', 10, 10, 200, 100);
    vi.setSystemTime(Date.now() + 1000);
    useMagazineStore.getState().updateElement(id, { x: 100 });
    vi.setSystemTime(Date.now() + 1000); // pause > gesture window
    useMagazineStore.getState().updateElement(id, { x: 200 });
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().pages[0].elements[0].x).toBe(100);
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().pages[0].elements[0].x).toBe(10);
  });

  it('style definitions are covered by history', () => {
    const st = { id: 'st1', name: 'Body', type: 'paragraph' as const, properties: {}, basedOnId: null, nextStyleId: null, isDefault: false };
    useMagazineStore.getState().addStyle(st);
    expect(useMagazineStore.getState().styles).toHaveLength(1);
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().styles).toHaveLength(0);
    useMagazineStore.getState().redo();
    expect(useMagazineStore.getState().styles).toHaveLength(1);
  });

  it('copy/paste works and paste is undoable', () => {
    const s = useMagazineStore.getState();
    const id = s.addElement('text_frame', 10, 10, 200, 100);
    useMagazineStore.getState().selectElement(id);
    useMagazineStore.getState().copy();
    useMagazineStore.getState().paste();
    expect(useMagazineStore.getState().pages[0].elements).toHaveLength(2);
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().pages[0].elements).toHaveLength(1);
  });

  it('delete + undo restores the element', () => {
    const s = useMagazineStore.getState();
    const id = s.addElement('text_frame', 10, 10, 200, 100);
    useMagazineStore.getState().deleteElements([id]);
    expect(useMagazineStore.getState().pages[0].elements).toHaveLength(0);
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().pages[0].elements).toHaveLength(1);
  });

  it('masters v2: primary text frame instantiates on pages created from master', () => {
    const s = useMagazineStore.getState();
    s.addMasterPage('A');
    const master = useMagazineStore.getState().pages.find((p) => p.isMaster)!;
    useMagazineStore.getState().updatePage(master.pageNumber, {
      elements: [{
        id: 'primary-1', type: 'text_frame', name: 'Body', data: { content: '<p>x</p>', _primaryFlow: true },
        x: 36, y: 36, width: 500, height: 700, rotation: 0, scaleX: 1, scaleY: 1, zIndex: 1,
        locked: false, visible: true, layerName: null, style: {} as any, typography: null,
        textWrap: { type: 'none', offset: { top: 0, right: 0, bottom: 0, left: 0 }, side: 'both', customPath: null, invert: false },
        threadId: null, threadOrder: null, pageNumber: master.pageNumber, onMaster: true,
        positionMode: 'free', spanMode: 'page', parentId: null, children: [], responsiveOverrides: {},
      }],
    } as any);
    useMagazineStore.getState().assignMaster(1, master.id);
    useMagazineStore.getState().addPage(1);
    const p2 = useMagazineStore.getState().pages.find((p) => p.pageNumber === 2 && !p.isMaster)!;
    expect(p2.masterPageId).toBe(master.id);
    expect(p2.elements).toHaveLength(1);
    expect(p2.elements[0].type).toBe('text_frame');
    expect(p2.elements[0].onMaster).toBe(false);
    expect((p2.elements[0].data as any)._primaryFlow).toBeUndefined();
    expect((p2.elements[0].data as any).content).toBe('');
  });

  it('masters v2: detachMaster copies elements and unlinks (undoable)', () => {
    const s = useMagazineStore.getState();
    s.addMasterPage('A');
    const master = useMagazineStore.getState().pages.find((p) => p.isMaster)!;
    useMagazineStore.getState().updatePage(master.pageNumber, {
      elements: [{
        id: 'pn-1', type: 'page_number', name: null, data: { format: 'decimal', prefix: '', suffix: '', startAt: 1 },
        x: 500, y: 800, width: 40, height: 20, rotation: 0, scaleX: 1, scaleY: 1, zIndex: 0,
        locked: false, visible: true, layerName: null, style: {} as any, typography: null,
        textWrap: { type: 'none', offset: { top: 0, right: 0, bottom: 0, left: 0 }, side: 'both', customPath: null, invert: false },
        threadId: null, threadOrder: null, pageNumber: master.pageNumber, onMaster: true,
        positionMode: 'free', spanMode: 'page', parentId: null, children: [], responsiveOverrides: {},
      }],
    } as any);
    useMagazineStore.getState().assignMaster(1, master.id);
    useMagazineStore.getState().detachMaster(1);
    const p1 = useMagazineStore.getState().pages.find((p) => p.pageNumber === 1 && !p.isMaster)!;
    expect(p1.masterPageId).toBeNull();
    expect(p1.elements).toHaveLength(1);
    expect(p1.elements[0].onMaster).toBe(false);
    expect((p1.elements[0].data as any).startAt).toBe(1); // resolved to page 1
    useMagazineStore.getState().undo();
    const p1b = useMagazineStore.getState().pages.find((p) => p.pageNumber === 1 && !p.isMaster)!;
    expect(p1b.masterPageId).toBe(master.id);
    expect(p1b.elements).toHaveLength(0);
  });

  it('step-and-repeat clones with offsets and is undoable (W2-6)', () => {
    const s = useMagazineStore.getState();
    const id = s.addElement('rectangle', 10, 10, 50, 50);
    useMagazineStore.getState().stepAndRepeat([id], 3, 20, 5);
    const els = useMagazineStore.getState().pages[0].elements;
    expect(els).toHaveLength(4);
    expect(els[3].x).toBe(70); // 10 + 20*3
    expect(els[3].y).toBe(25); // 10 + 5*3
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().pages[0].elements).toHaveLength(1);
  });

  it('ruler guides: add/move/remove/clear round-trip in store (W2-1)', () => {
    const st = useMagazineStore.getState();
    st.addGuide(1, 'v', 120.34);
    st.addGuide(1, 'h', 300);
    let g = (useMagazineStore.getState().pages[0] as any)._guides;
    expect(g.v).toEqual([120.3]);
    expect(g.h).toEqual([300]);
    useMagazineStore.getState().moveGuide(1, 'v', 0, 150);
    g = (useMagazineStore.getState().pages[0] as any)._guides;
    expect(g.v).toEqual([150]);
    useMagazineStore.getState().removeGuide(1, 'h', 0);
    g = (useMagazineStore.getState().pages[0] as any)._guides;
    expect(g.h).toEqual([]);
    useMagazineStore.getState().undo(); // undo remove
    g = (useMagazineStore.getState().pages[0] as any)._guides;
    expect(g.h).toEqual([300]);
    useMagazineStore.getState().clearGuides(1);
    g = (useMagazineStore.getState().pages[0] as any)._guides;
    expect(g.v).toEqual([]);
  });

  it('group/ungroup: bounding box, child transforms, undo (W2)', () => {
    const st = useMagazineStore.getState();
    const a = st.addElement('rectangle', 10, 10, 50, 50);
    const b = useMagazineStore.getState().addElement('rectangle', 100, 40, 40, 40);
    useMagazineStore.getState().groupElements([a, b]);
    let els = useMagazineStore.getState().pages[0].elements;
    expect(els).toHaveLength(1);
    const g = els[0];
    expect(g.type).toBe('group');
    expect([g.x, g.y, g.width, g.height]).toEqual([10, 10, 130, 70]);
    expect(g.children).toHaveLength(2);
    vi.setSystemTime(Date.now() + 1000);
    useMagazineStore.getState().updateElement(g.id, { x: 110, y: 10 });
    const g2 = useMagazineStore.getState().pages[0].elements[0];
    expect(g2.children[0].x).toBe(110);
    expect(g2.children[1].x).toBe(200);
    vi.setSystemTime(Date.now() + 1000);
    useMagazineStore.getState().updateElement(g.id, { width: 260 });
    const g3 = useMagazineStore.getState().pages[0].elements[0];
    expect(g3.children[1].x).toBe(290); // 110 + (200-110)*2
    expect(g3.children[1].width).toBe(80);
    useMagazineStore.getState().ungroupElements(g.id);
    els = useMagazineStore.getState().pages[0].elements;
    expect(els).toHaveLength(2);
    expect(els.every((e) => e.parentId === null)).toBe(true);
    useMagazineStore.getState().undo();
    expect(useMagazineStore.getState().pages[0].elements).toHaveLength(1);
  });

  it('footnotes: frame created at page bottom with jump wrap, numbering increments (pro)', () => {
    const st = useMagazineStore.getState();
    const n1 = st.insertFootnote(1, 'First source');
    expect(n1).toBe(1);
    let page = useMagazineStore.getState().pages[0];
    const fn = page.elements.find((e) => e.type === 'footnote_frame')!;
    expect(fn).toBeTruthy();
    expect(fn.textWrap.type).toBe('jump');
    expect(fn.y + fn.height).toBeLessThanOrEqual(page.pageSize.height - page.margins.bottom + 0.01);
    expect((fn.data as any).content).toContain('<sup>1</sup> First source');
    const n2 = useMagazineStore.getState().insertFootnote(1, 'Second source');
    expect(n2).toBe(2);
    page = useMagazineStore.getState().pages[0];
    expect(page.elements.filter((e) => e.type === 'footnote_frame')).toHaveLength(1); // reused
    expect((page.elements.find((e) => e.type === 'footnote_frame')!.data as any).content).toContain('<sup>2</sup> Second source');
    useMagazineStore.getState().undo();
    page = useMagazineStore.getState().pages[0];
    expect((page.elements.find((e) => e.type === 'footnote_frame')!.data as any).content).not.toContain('Second');
  });

  it('setEditingMaster does not pollute history', () => {
    useMagazineStore.getState().addMasterPage('A');
    const depth = useMagazineStore.getState().undoStack.length;
    const master = useMagazineStore.getState().pages.find((p) => p.isMaster)!;
    useMagazineStore.getState().setEditingMaster(master.id);
    useMagazineStore.getState().setEditingMaster(null);
    expect(useMagazineStore.getState().undoStack.length).toBe(depth);
  });
});
