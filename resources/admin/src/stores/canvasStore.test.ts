import { describe, it, expect, beforeEach } from 'vitest';
import { useCanvasStore } from './canvasStore';
import { effectiveLayout } from '@/types/canvas';
import type { BlockData } from '@/types/blocks';

const section = (id: string, children: BlockData[] = []): BlockData => ({
  id, type: 'section', level: 'section', order: 0, data: { canvas: { height: 400, bleed: false, background: '' } }, children,
} as unknown as BlockData);

function reset() {
  useCanvasStore.getState().loadFromBlocks([section('s1')], { pageType: 'website', width: 1200 });
}

describe('canvasStore', () => {
  beforeEach(reset);

  it('loads sections and defaults width/active section', () => {
    const s = useCanvasStore.getState();
    expect(s.sections).toHaveLength(1);
    expect(s.width).toBe(1200);
    expect(s.gridSize).toBe(100);
    expect(s.activeSectionId).toBe('s1');
    expect(s.isDirty).toBe(false);
  });

  it('adds / moves / deletes sections', () => {
    const st = useCanvasStore.getState();
    st.addSection('s1');
    expect(useCanvasStore.getState().sections).toHaveLength(2);
    const [a, b] = useCanvasStore.getState().sections;
    st.moveSection(b.id, 'up');
    expect(useCanvasStore.getState().sections[0].id).toBe(b.id);
    st.deleteSection(a.id);
    expect(useCanvasStore.getState().sections).toHaveLength(1);
  });

  it('adds an element on top and updates it', () => {
    const st = useCanvasStore.getState();
    const id = st.addElement('s1', 'heading', 40, 40, 300, 100);
    let el = useCanvasStore.getState().sections[0].elements[0];
    expect(el.id).toBe(id);
    expect(el.zIndex).toBe(1);
    expect(useCanvasStore.getState().selectedIds).toEqual([id]);
    st.updateElement(id, { x: 120, y: 200 });
    el = useCanvasStore.getState().sections[0].elements[0];
    expect([el.x, el.y]).toEqual([120, 200]);
  });

  it('z-order: bringToFront / sendToBack', () => {
    const st = useCanvasStore.getState();
    const a = st.addElement('s1', 'heading', 0, 0);
    const b = st.addElement('s1', 'text', 0, 0);
    st.sendToBack([b]);
    const els = useCanvasStore.getState().sections[0].elements;
    const za = els.find(e => e.id === a)!.zIndex;
    const zb = els.find(e => e.id === b)!.zIndex;
    expect(zb).toBeLessThan(za);
    st.bringToFront([b]);
    const els2 = useCanvasStore.getState().sections[0].elements;
    expect(els2.find(e => e.id === b)!.zIndex).toBeGreaterThan(els2.find(e => e.id === a)!.zIndex);
  });

  it('duplicates with offset and deep-copied data', () => {
    const st = useCanvasStore.getState();
    const id = st.addElement('s1', 'heading', 50, 50, 200, 80);
    st.updateElement(id, { data: { text: 'orig' } });
    st.duplicateElements([id]);
    const els = useCanvasStore.getState().sections[0].elements;
    expect(els).toHaveLength(2);
    const dupe = els[1];
    expect(dupe.id).not.toBe(id);
    expect([dupe.x, dupe.y]).toEqual([74, 74]);
    expect(dupe.data).toEqual({ text: 'orig' });
    (dupe.data as { text: string }).text = 'changed';
    expect((els[0].data as { text: string }).text).toBe('orig'); // deep copy, not shared
  });

  it('deletes elements and clears their selection', () => {
    const st = useCanvasStore.getState();
    const id = st.addElement('s1', 'heading', 0, 0);
    st.deleteElements([id]);
    expect(useCanvasStore.getState().sections[0].elements).toHaveLength(0);
    expect(useCanvasStore.getState().selectedIds).toEqual([]);
  });

  it('undo / redo restores element state', () => {
    const st = useCanvasStore.getState();
    st.addElement('s1', 'heading', 0, 0);
    expect(useCanvasStore.getState().sections[0].elements).toHaveLength(1);
    st.undo();
    expect(useCanvasStore.getState().sections[0].elements).toHaveLength(0);
    st.redo();
    expect(useCanvasStore.getState().sections[0].elements).toHaveLength(1);
  });

  it('carries non-section passthrough blocks through toBlocks (safe mode switch)', () => {
    useCanvasStore.getState().loadFromBlocks([
      section('s1'),
      { id: 'r', type: 'row', data: { keep: true }, order: 1, children: [] } as unknown as BlockData,
    ], { pageType: 'website', width: 1200 });
    const st = useCanvasStore.getState();
    st.addElement('s1', 'heading', 10, 10);
    const out = st.toBlocks();
    expect(out.map(b => b.type)).toEqual(['section', 'row']);
    expect(out.find(b => b.id === 'r')?.data).toEqual({ keep: true });
  });

  it('writes to the base on desktop and to the mobile override on mobile', () => {
    const st = useCanvasStore.getState();
    const id = st.addElement('s1', 'heading', 100, 100, 300, 80);

    // desktop write → base changes
    st.updateElementLayout(id, { x: 150 }, 'desktop');
    let el = useCanvasStore.getState().sections[0].elements[0];
    expect(el.x).toBe(150);
    expect(el.bp).toBeUndefined();

    // mobile write → only the override changes; base stays put
    st.updateElementLayout(id, { x: 10, y: 20 }, 'mobile');
    el = useCanvasStore.getState().sections[0].elements[0];
    expect(el.x).toBe(150);                                   // base untouched
    expect(el.bp?.mobile).toEqual({ x: 10, y: 20 });
    expect(effectiveLayout(el, 'mobile')).toMatchObject({ x: 10, y: 20, width: 300, height: 80 }); // inherits w/h
    expect(effectiveLayout(el, 'desktop')).toMatchObject({ x: 150, y: 100 });

    // clear the override → back to inheriting desktop
    st.clearMobileOverride(id);
    el = useCanvasStore.getState().sections[0].elements[0];
    expect(el.bp).toBeUndefined();
    expect(effectiveLayout(el, 'mobile')).toMatchObject({ x: 150, y: 100 });
  });

  it('applyOp applies a remote op without emitting or snapshotting', () => {
    const st = useCanvasStore.getState();
    const id = st.addElement('s1', 'heading', 10, 10, 100, 40);
    const undoBefore = useCanvasStore.getState().undoStack.length;
    const emitted: unknown[] = [];
    st.setLocalOpSink((op) => emitted.push(op));

    st.applyOp({ t: 'layout', id, patch: { x: 200, y: 90 }, bp: 'desktop' });
    const el = useCanvasStore.getState().sections[0].elements[0];
    expect([el.x, el.y]).toEqual([200, 90]);
    expect(emitted).toHaveLength(0);                                   // remote op does not re-emit
    expect(useCanvasStore.getState().undoStack.length).toBe(undoBefore); // no snapshot

    // add + del via applyOp
    st.applyOp({ t: 'add', sectionId: 's1', element: { id: 'rx', blockType: 'text', data: {}, x: 5, y: 5, width: 50, height: 20, rotation: 0, zIndex: 1, locked: false, style: {} } });
    expect(useCanvasStore.getState().sections[0].elements.some((e) => e.id === 'rx')).toBe(true);
    st.applyOp({ t: 'del', ids: ['rx'] });
    expect(useCanvasStore.getState().sections[0].elements.some((e) => e.id === 'rx')).toBe(false);
    st.setLocalOpSink(null);
  });

  it('local mutations emit ops to the sink', () => {
    const st = useCanvasStore.getState();
    const emitted: Array<{ t: string }> = [];
    st.setLocalOpSink((op) => emitted.push(op));
    const id = st.addElement('s1', 'heading', 0, 0);
    st.updateElementLayout(id, { x: 10 }, 'desktop');
    st.bringToFront([id]);
    st.deleteElements([id]);
    expect(emitted.map((o) => o.t)).toEqual(['add', 'layout', 'z', 'del']);
    st.setLocalOpSink(null);
  });

  it('applyOp handles section + property ops (full collab coverage)', () => {
    const st = useCanvasStore.getState();
    const id = st.addElement('s1', 'heading', 0, 0);

    st.applyOp({ t: 'pin', id, pinX: 'right' });
    expect(useCanvasStore.getState().sections[0].elements[0].pinX).toBe('right');
    st.applyOp({ t: 'anim', id, anim: { type: 'fade', delay: 100 } });
    expect(useCanvasStore.getState().sections[0].elements[0].anim).toEqual({ type: 'fade', delay: 100 });

    st.applyOp({ t: 'secAdd', section: { id: 'sec-remote', settings: { height: 300, bleed: false, background: '' }, data: {}, style: {}, elements: [] }, afterId: 's1' });
    expect(useCanvasStore.getState().sections.map((s) => s.id)).toEqual(['s1', 'sec-remote']);
    st.applyOp({ t: 'secSettings', id: 'sec-remote', patch: { bleed: true } });
    expect(useCanvasStore.getState().sections[1].settings.bleed).toBe(true);
    st.applyOp({ t: 'secMove', id: 'sec-remote', dir: 'up' });
    expect(useCanvasStore.getState().sections.map((s) => s.id)).toEqual(['sec-remote', 's1']);
    st.applyOp({ t: 'secDel', id: 'sec-remote' });
    expect(useCanvasStore.getState().sections.map((s) => s.id)).toEqual(['s1']);
    // secAdd is idempotent (a re-delivered op won't duplicate)
    st.applyOp({ t: 'secAdd', section: useCanvasStore.getState().sections[0], afterId: undefined });
    expect(useCanvasStore.getState().sections).toHaveLength(1);
  });

  it('section + property mutations emit ops', () => {
    const st = useCanvasStore.getState();
    const emitted: Array<{ t: string }> = [];
    st.setLocalOpSink((op) => emitted.push(op));
    const id = st.addElement('s1', 'heading', 0, 0);
    st.setElementPin(id, 'center');
    st.setElementAnim(id, { type: 'zoom' });
    st.updateSectionSettings('s1', { bleed: true });
    st.addSection('s1');
    st.moveSection('s1', 'down');
    st.duplicateElements([id]);
    expect(emitted.map((o) => o.t)).toEqual(['add', 'pin', 'anim', 'secSettings', 'secAdd', 'secMove', 'add']);
    st.setLocalOpSink(null);
  });

  it('setWidth clamps and recomputes the grid', () => {
    const st = useCanvasStore.getState();
    st.setWidth(60);           // below min
    expect(useCanvasStore.getState().width).toBe(320);
    st.setWidth(960);
    expect(useCanvasStore.getState().gridSize).toBe(80);
  });
});
