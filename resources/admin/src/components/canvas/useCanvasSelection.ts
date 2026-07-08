import { useCallback, useRef, useState } from 'react';
import type { MagElement } from '@/types/magazine';
import { calculateSmartGuides, snapToGrid } from '@/lib/smartGuides';
import { useCanvasStore } from '@/stores/canvasStore';
import { effectiveLayout } from '@/types/canvas';

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
  snapshotted: boolean; // undo snapshot taken on the FIRST real move, not on down
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
    // One undo entry per gesture: snapshot the pre-drag state on the first real
    // move, so a bare click (down + up, no move) leaves the undo stack alone.
    if (!d.snapshotted) { st.pushSnapshot(); d.snapshotted = true; }
    const bp = st.activeBreakpoint;
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
        const others = elements.filter(el => !d.ids.includes(el.id)).map(el => effectiveLayout(el, bp)) as unknown as MagElement[];
        const snap = calculateSmartGuides({ x: nx, y: ny, width: primary.width, height: primary.height }, others, sectionWidth, sectionHeight, { top: 0, right: 0, bottom: 0, left: 0 });
        nx = snap.x; ny = snap.y;
        active.push(...snap.guides);
      }
      const adx = nx - primary.x;
      const ady = ny - primary.y;
      d.ids.forEach(id => {
        const o = d.orig.get(id)!;
        st.updateElementLayout(id, { x: Math.round(o.x + adx), y: Math.round(o.y + ady) }, bp);
      });
      setGuides(active);
      return;
    }

    if (d.mode === 'resize' && d.handle) {
      const o = primary;
      const rad = (o.rotation || 0) * Math.PI / 180;
      const cos = Math.cos(rad), sin = Math.sin(rad);
      const rot = (vx: number, vy: number) => ({ x: vx * cos - vy * sin, y: vx * sin + vy * cos });

      // Project the screen-space drag onto the element's local (unrotated) axes,
      // so a rotated element resizes along its own edges, not the screen's.
      const lx = dx * cos + dy * sin;
      const ly = -dx * sin + dy * cos;
      const H = d.handle;
      const sx = H.includes('e') ? 1 : H.includes('w') ? -1 : 0;
      const sy = H.includes('s') ? 1 : H.includes('n') ? -1 : 0;
      const width = Math.max(20, o.width + sx * lx);
      const height = Math.max(20, o.height + sy * ly);

      // Keep the anchor (the corner/edge opposite the dragged handle) fixed in
      // screen space. Rotation is about the center, so recentre after resizing.
      const ax = sx > 0 ? 0 : sx < 0 ? 1 : 0.5;
      const ay = sy > 0 ? 0 : sy < 0 ? 1 : 0.5;
      const cx0 = o.x + o.width / 2, cy0 = o.y + o.height / 2;
      const aOld = rot((ax - 0.5) * o.width, (ay - 0.5) * o.height);
      const aScreen = { x: cx0 + aOld.x, y: cy0 + aOld.y };
      const aNew = rot((ax - 0.5) * width, (ay - 0.5) * height);
      const x = (aScreen.x - aNew.x) - width / 2;
      const y = (aScreen.y - aNew.y) - height / 2;

      st.updateElementLayout(d.primaryId, { x: Math.round(x), y: Math.round(y), width: Math.round(width), height: Math.round(height) }, bp);
      return;
    }

    if (d.mode === 'rotate' && d.cx != null && d.cy != null) {
      const angle = Math.atan2(e.clientY - d.cy, e.clientX - d.cx) * 180 / Math.PI;
      let rot = Math.round(angle - (d.startAngle ?? 0) + primary.rotation);
      if (e.shiftKey) rot = Math.round(rot / 15) * 15;
      st.updateElementLayout(d.primaryId, { rotation: rot }, bp);
    }
  }, [sectionId, sectionWidth, sectionHeight]);

  const begin = useCallback((d: DragState) => {
    drag.current = d;
    handlers.current = { move, up: stop };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', stop);
  }, [move, stop]);

  const origFor = useCallback((ids: string[]): Map<string, Rect> => {
    const st = useCanvasStore.getState();
    const bp = st.activeBreakpoint;
    const els = (st.sections.find(s => s.id === sectionId)?.elements) ?? [];
    const m = new Map<string, Rect>();
    els.forEach(el => {
      if (ids.includes(el.id)) {
        const L = effectiveLayout(el, bp);
        m.set(el.id, { x: L.x, y: L.y, width: L.width, height: L.height, rotation: L.rotation });
      }
    });
    return m;
  }, [sectionId]);

  const onElementPointerDown = useCallback((e: React.PointerEvent, id: string) => {
    e.stopPropagation();
    const st = useCanvasStore.getState();
    const el = st.sections.find(s => s.id === sectionId)?.elements.find(x => x.id === id);
    if (el?.locked) return;
    const additive = e.shiftKey || e.metaKey || e.ctrlKey;
    const alreadySelected = st.selectedIds.includes(id);
    // Keep an existing multi-selection when grabbing one of its members without
    // a modifier — so the whole group drags. Only reset when clicking outside it.
    if (additive) st.select(id, true);
    else if (!alreadySelected) st.select(id, false);
    const sel = useCanvasStore.getState().selectedIds;
    const ids = sel.includes(id) ? sel : [id];
    begin({ mode: 'move', ids, primaryId: id, snapshotted: false, startClientX: e.clientX, startClientY: e.clientY, orig: origFor(ids) });
  }, [sectionId, begin, origFor]);

  const onResizePointerDown = useCallback((e: React.PointerEvent, id: string, handle: ResizeHandle) => {
    e.stopPropagation();
    begin({ mode: 'resize', ids: [id], primaryId: id, handle, snapshotted: false, startClientX: e.clientX, startClientY: e.clientY, orig: origFor([id]) });
  }, [begin, origFor]);

  const onRotatePointerDown = useCallback((e: React.PointerEvent, id: string, elCenter: { cx: number; cy: number }) => {
    e.stopPropagation();
    const startAngle = Math.atan2(e.clientY - elCenter.cy, e.clientX - elCenter.cx) * 180 / Math.PI;
    begin({ mode: 'rotate', ids: [id], primaryId: id, snapshotted: false, startClientX: e.clientX, startClientY: e.clientY, orig: origFor([id]), cx: elCenter.cx, cy: elCenter.cy, startAngle });
  }, [begin, origFor]);

  return { guides, onElementPointerDown, onResizePointerDown, onRotatePointerDown };
}
