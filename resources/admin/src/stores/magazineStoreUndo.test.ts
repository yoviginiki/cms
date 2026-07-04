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

  it('setEditingMaster does not pollute history', () => {
    useMagazineStore.getState().addMasterPage('A');
    const depth = useMagazineStore.getState().undoStack.length;
    const master = useMagazineStore.getState().pages.find((p) => p.isMaster)!;
    useMagazineStore.getState().setEditingMaster(master.id);
    useMagazineStore.getState().setEditingMaster(null);
    expect(useMagazineStore.getState().undoStack.length).toBe(depth);
  });
});
