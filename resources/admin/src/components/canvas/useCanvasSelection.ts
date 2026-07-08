import { useCallback, useRef, useState } from 'react';
import type { MagElement } from '@/types/magazine';
import { calculateSmartGuides, snapToGrid } from '@/lib/smartGuides';
import { useCanvasStore } from '@/stores/canvasStore';

export type ResizeHandle = 'nw' | 'n' | 'ne' | 'e' | 'se' | 's' | 'sw' | 'w';
interface Guide { type: 'vertical' | 'horizontal'; position: number; }

type Rect = { x: number; y: number; width: number; height: number; rotation: number };
interface DragState {
  mode: 'move' | 'resize' | 'rotate';
  ids: string[];
  handle?: ResizeHandle;
  startClientX: number;
  startClientY: number;
  orig: Map<string, Rect>;
  primaryId: string;
  cx?: number; cy?: number; startAngle?: number;
}

/**
 * Canvas interaction (drag / resize / rotate / multi-select) for one section.
 * Ported from the Magazine editor's MagSelectionEngine but bound to the canvas
 * store (keeps the Magazine editor untouched); reuses the shared smartGuides.
 * Reads live state via getState() so listeners never go stale.
 */
export function useCanvasSelection(sectionId: string, sectionWidth: number, sectionHeight: number) {
  const [guides, setGuides] = useState<Guide[]>([]);
  const drag = useRef<DragState | null>(null);
  const handlers = useRef<{ move?: (e: PointerEvent) => void; up?: () => void }>({});

  const stop = useCallback(() => {
    drag.current = null;
    setGuides([]);
    if (handlers.current.move) window.removeEventListener('pointermove', handlers.current.move);
    if (handlers.current.up) window.removeEventListener('pointerup', handlers.current.up);
    handlers.current = {};
  }, []);

  const move = useCallback((e: PointerEvent) => {
    const d = drag.current;
    if (!d) return;
    const st = useCanvasStore.getState();
    const zoom = st.zoom || 1;
    const dx = (e.clientX - d.startClientX) / zoom;
    const dy = (e.clientY - d.startClientY) / zoom;
    const primary = d.orig.get(d.primaryId)!;
    const elements = (st.sections.find(s => s.id === sectionId)?.elements) ?? [];

    if (d.mode === 'move') {
      let nx = primary.x + dx;
      let ny = primary.y + dy;
      const active: Guide[] = [];
      if (st.snapEnabled) {
        nx = snapToGrid(nx, st.gridSize);
        ny = snapToGrid(ny, st.gridSize);
        const others = elements.filter(el => !d.ids.includes(el.id)) as unknown as MagElement[];
        const snap = calculateSmartGuides({ x: nx, y: ny, width: primary.width, height: primary.height }, others, sectionWidth, sectionHeight, { top: 0, right: 0, bottom: 0, left: 0 });
        nx = snap.x; ny = snap.y;
        active.push(...snap.guides);
      }
      const adx = nx - primary.x;
      const ady = ny - primary.y;
      st.updateElements(d.ids.map(id => {
        const o = d.orig.get(id)!;
        return { id, patch: { x: Math.round(o.x + adx), y: Math.round(o.y + ady) } };
      }));
      setGuides(active);
      return;
    }

    if (d.mode === 'resize' && d.handle) {
      const o = primary;
      let { x, y, width, height } = o;
      const h = d.handle;
      if (h.includes('e')) width = Math.max(20, o.width + dx);
      if (h.includes('s')) height = Math.max(20, o.height + dy);
      if (h.includes('w')) { width = Math.max(20, o.width - dx); x = o.x + (o.width - width); }
      if (h.includes('n')) { height = Math.max(20, o.height - dy); y = o.y + (o.height - height); }
      st.updateElements([{ id: d.primaryId, patch: { x: Math.round(x), y: Math.round(y), width: Math.round(width), height: Math.round(height) } }]);
      return;
    }

    if (d.mode === 'rotate' && d.cx != null && d.cy != null) {
      const angle = Math.atan2(e.clientY - d.cy, e.clientX - d.cx) * 180 / Math.PI;
      let rot = Math.round(angle - (d.startAngle ?? 0) + primary.rotation);
      if (e.shiftKey) rot = Math.round(rot / 15) * 15;
      st.updateElements([{ id: d.primaryId, patch: { rotation: rot } }]);
    }
  }, [sectionId, sectionWidth, sectionHeight]);

  const begin = useCallback((d: DragState) => {
    drag.current = d;
    handlers.current = { move, up: stop };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', stop);
  }, [move, stop]);

  const origFor = useCallback((ids: string[]): Map<string, Rect> => {
    const els = (useCanvasStore.getState().sections.find(s => s.id === sectionId)?.elements) ?? [];
    const m = new Map<string, Rect>();
    els.forEach(el => { if (ids.includes(el.id)) m.set(el.id, { x: el.x, y: el.y, width: el.width, height: el.height, rotation: el.rotation }); });
    return m;
  }, [sectionId]);

  const onElementPointerDown = useCallback((e: React.PointerEvent, id: string) => {
    e.stopPropagation();
    const st = useCanvasStore.getState();
    const el = st.sections.find(s => s.id === sectionId)?.elements.find(x => x.id === id);
    if (el?.locked) return;
    const additive = e.shiftKey || e.metaKey || e.ctrlKey;
    st.select(id, additive);
    const sel = useCanvasStore.getState().selectedIds;
    const ids = sel.includes(id) ? sel : [id];
    st.pushSnapshot();
    begin({ mode: 'move', ids, primaryId: id, startClientX: e.clientX, startClientY: e.clientY, orig: origFor(ids) });
  }, [sectionId, begin, origFor]);

  const onResizePointerDown = useCallback((e: React.PointerEvent, id: string, handle: ResizeHandle) => {
    e.stopPropagation();
    useCanvasStore.getState().pushSnapshot();
    begin({ mode: 'resize', ids: [id], primaryId: id, handle, startClientX: e.clientX, startClientY: e.clientY, orig: origFor([id]) });
  }, [begin, origFor]);

  const onRotatePointerDown = useCallback((e: React.PointerEvent, id: string, elCenter: { cx: number; cy: number }) => {
    e.stopPropagation();
    useCanvasStore.getState().pushSnapshot();
    const startAngle = Math.atan2(e.clientY - elCenter.cy, e.clientX - elCenter.cx) * 180 / Math.PI;
    begin({ mode: 'rotate', ids: [id], primaryId: id, startClientX: e.clientX, startClientY: e.clientY, orig: origFor([id]), cx: elCenter.cx, cy: elCenter.cy, startAngle });
  }, [begin, origFor]);

  return { guides, onElementPointerDown, onResizePointerDown, onRotatePointerDown };
}
