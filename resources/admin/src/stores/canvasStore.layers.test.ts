import { describe, it, expect, beforeEach } from 'vitest';
import { useCanvasStore } from './canvasStore';
import type { BlockData } from '@/types/blocks';

const section = (id: string): BlockData => ({
  id, type: 'section', level: 'section', order: 0, data: { canvas: { height: 400, bleed: false, background: '' } }, children: [],
} as unknown as BlockData);

const st = () => useCanvasStore.getState();
const zOrder = () => [...st().sections[0].elements].sort((a, b) => a.zIndex - b.zIndex).map(e => e.id);

describe('canvasStore layers, editing and data patches', () => {
  let a: string, b: string, c: string;
  beforeEach(() => {
    st().loadFromBlocks([section('s1')], { pageType: 'website', width: 1200 });
    a = st().addElement('s1', 'text', 0, 0, 100, 50);
    b = st().addElement('s1', 'text', 10, 10, 100, 50);
    c = st().addElement('s1', 'text', 20, 20, 100, 50);
  });

  it('bringForward / sendBackward move one step and are undoable', () => {
    expect(zOrder()).toEqual([a, b, c]);
    st().bringForward([a]);
    expect(zOrder()).toEqual([b, a, c]);
    st().sendBackward([c]);
    expect(zOrder()).toEqual([b, c, a]);
    st().undo();
    expect(zOrder()).toEqual([b, a, c]);
    st().undo();
    expect(zOrder()).toEqual([a, b, c]);
  });

  it('a remote step op applies through applyOp with the same result', () => {
    st().applyOp({ t: 'z', ids: [a], mode: 'forward' });
    expect(zOrder()).toEqual([b, a, c]);
  });

  it('setEditing selects the element; moving the selection leaves edit mode', () => {
    st().setEditing(a);
    expect(st().editingId).toBe(a);
    expect(st().selectedIds).toEqual([a]);
    st().select(b);
    expect(st().editingId).toBeNull();
    st().setEditing(b);
    st().clearSelection();
    expect(st().editingId).toBeNull();
    st().setEditing(c);
    st().deleteElements([c]);
    expect(st().editingId).toBeNull();
  });

  it('updateElementData merges partial data and routes __style to the element style (keeping layout)', () => {
    st().updateElement(a, { style: { layout: { maxWidth: '50%' } } });
    st().updateElementData(a, { content: '<p>hi</p>' });
    st().updateElementData(a, { textAlign: 'center', __style: { typography: { fontSize: '2rem' } } });
    const el = st().sections[0].elements.find(e => e.id === a)!;
    expect(el.data).toEqual({ content: '<p>hi</p>', textAlign: 'center' });
    expect(el.style).toEqual({ layout: { maxWidth: '50%' }, typography: { fontSize: '2rem' } });
  });

  it('opacity lives in the layout patch and round-trips through toBlocks', () => {
    st().updateElementLayout(a, { opacity: 0.4 }, 'desktop');
    const blocks = st().toBlocks();
    const layout = (blocks[0].children[0].style as Record<string, Record<string, unknown>>).layout;
    expect(layout.opacity).toBe(0.4);
    // opaque elements keep the key out of the JSON
    const layoutB = (blocks[0].children[1].style as Record<string, Record<string, unknown>>).layout;
    expect('opacity' in layoutB).toBe(false);
    st().loadFromBlocks(blocks, { pageType: 'website', width: 1200 });
    expect(st().sections[0].elements[0].opacity).toBe(0.4);
    expect(st().sections[0].elements[1].opacity).toBeUndefined();
  });
});
