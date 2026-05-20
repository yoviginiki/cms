import { useState, useCallback, useEffect, useRef } from 'react';
import type { MagElement } from '@/types/magazine';
import { calculateSmartGuides, snapToGrid } from '@/lib/smartGuides';

interface SelectionState {
  selectedIds: string[];
  hoveredId: string | null;
  isDragging: boolean;
  isResizing: boolean;
  isRotating: boolean;
  isPanning: boolean;
  dragOffset: { x: number; y: number } | null;
  resizeHandle: string | null;
  guides: Array<{ type: 'vertical' | 'horizontal'; position: number }>;
  marquee: { x: number; y: number; width: number; height: number } | null;
  activeTool: 'select' | 'text' | 'image' | 'rectangle' | 'ellipse' | 'line';
  snapEnabled: boolean;
  gridSize: number;
}

export function useMagSelection(
  elements: MagElement[],
  zoom: number,
  pageWidth: number,
  pageHeight: number,
  margins: { top: number; right: number; bottom: number; left: number },
  onUpdateElement: (id: string, updates: Partial<MagElement>) => void,
  _onAddElement: (type: string, x: number, y: number, w: number, h: number) => void,
  onDeleteElements: (ids: string[]) => void,
  onDuplicateElements: (ids: string[]) => void,
  onMoveToPage?: (elementId: string, direction: 'prev' | 'next', newX: number, newY: number) => void,
) {
  const [state, setState] = useState<SelectionState>({
    selectedIds: [],
    hoveredId: null,
    isDragging: false,
    isResizing: false,
    isRotating: false,
    isPanning: false,
    dragOffset: null,
    resizeHandle: null,
    guides: [],
    marquee: null,
    activeTool: 'select',
    snapEnabled: true,
    gridSize: 8,
  });

  const dragStartRef = useRef<{ x: number; y: number; origPositions: Map<string, { x: number; y: number }> } | null>(null);
  const dragPendingRef = useRef(false);
  const DRAG_DEAD_ZONE = 3; // px — must move this far before drag activates
  const resizeStartRef = useRef<{ x: number; y: number; origBounds: { x: number; y: number; w: number; h: number } } | null>(null);
  const rotationStartRef = useRef<{ startAngle: number; initialRotation: number; cx: number; cy: number; pageRect: DOMRect | null } | null>(null);

  const select = useCallback((id: string, addToSelection = false) => {
    setState(s => ({
      ...s,
      selectedIds: addToSelection ? (s.selectedIds.includes(id) ? s.selectedIds.filter(i => i !== id) : [...s.selectedIds, id]) : [id],
    }));
  }, []);

  const clearSelection = useCallback(() => { setState(s => ({ ...s, selectedIds: [], hoveredId: null })); }, []);
  const setTool = useCallback((tool: SelectionState['activeTool']) => { setState(s => ({ ...s, activeTool: tool })); }, []);
  const toggleSnap = useCallback(() => { setState(s => ({ ...s, snapEnabled: !s.snapEnabled })); }, []);
  const setGridSize = useCallback((size: number) => { setState(s => ({ ...s, gridSize: size })); }, []);

  // Pointer down on element
  const handleElementPointerDown = useCallback((e: React.PointerEvent, id: string) => {
    e.stopPropagation();
    const el = elements.find(el => el.id === id);
    if (!el || el.locked) return;

    const handle = (e.target as HTMLElement).dataset?.handle;
    if (handle === 'rotate') {
      const cx = el.x + el.width / 2;
      const cy = el.y + el.height / 2;
      // Calculate starting angle from mouse to element center (in page coords, accounting for zoom)
      const pageEl = (e.currentTarget as HTMLElement).closest('[data-canvas="page"]');
      const rect = pageEl?.getBoundingClientRect() || null;
      const mouseX = rect ? (e.clientX - rect.left) / zoom : e.clientX;
      const mouseY = rect ? (e.clientY - rect.top) / zoom : e.clientY;
      const startAngle = Math.atan2(mouseY - cy, mouseX - cx) * (180 / Math.PI);
      rotationStartRef.current = { startAngle, initialRotation: el.rotation, cx, cy, pageRect: rect };
      setState(s => ({ ...s, isRotating: true, selectedIds: [id] }));
      return;
    }
    if (handle) {
      setState(s => ({ ...s, isResizing: true, resizeHandle: handle, selectedIds: [id] }));
      resizeStartRef.current = { x: e.clientX, y: e.clientY, origBounds: { x: el.x, y: el.y, w: el.width, h: el.height } };
      return;
    }

    const addToSelection = e.shiftKey;
    select(id, addToSelection);

    // Prepare drag but don't start until pointer moves beyond dead zone (3px)
    // This prevents accidental drag during double-click
    const positions = new Map<string, { x: number; y: number }>();
    const ids = addToSelection ? [...state.selectedIds, id] : [id];
    ids.forEach(selId => {
      const selEl = elements.find(e => e.id === selId);
      if (selEl) positions.set(selId, { x: selEl.x, y: selEl.y });
    });
    dragStartRef.current = { x: e.clientX, y: e.clientY, origPositions: positions };
    // isDragging starts false — handlePointerMove activates it after dead zone
    setState(s => ({ ...s, isDragging: false }));
    dragPendingRef.current = true;
  }, [elements, state.selectedIds, select, zoom]);

  // Pointer move (global)
  const handlePointerMove = useCallback((e: PointerEvent) => {
    // Activate drag after dead zone (prevents drag during double-click)
    if (dragPendingRef.current && dragStartRef.current && !state.isDragging) {
      const dx = Math.abs(e.clientX - dragStartRef.current.x);
      const dy = Math.abs(e.clientY - dragStartRef.current.y);
      if (dx > DRAG_DEAD_ZONE || dy > DRAG_DEAD_ZONE) {
        dragPendingRef.current = false;
        setState(s => ({ ...s, isDragging: true }));
      }
      return; // Don't move yet until drag is activated
    }

    if (state.isDragging && dragStartRef.current) {
      const dx = (e.clientX - dragStartRef.current.x) / zoom;
      const dy = (e.clientY - dragStartRef.current.y) / zoom;

      const firstId = state.selectedIds[0];
      const firstOrig = dragStartRef.current.origPositions.get(firstId);
      if (!firstOrig) return;

      let newX = firstOrig.x + dx;
      let newY = firstOrig.y + dy;

      if (state.snapEnabled) {
        newX = snapToGrid(newX, state.gridSize);
        newY = snapToGrid(newY, state.gridSize);
      }

      // Smart guides
      const firstEl = elements.find(e => e.id === firstId);
      if (firstEl) {
        const others = elements.filter(e => !state.selectedIds.includes(e.id));
        const snap = calculateSmartGuides({ x: newX, y: newY, width: firstEl.width, height: firstEl.height }, others, pageWidth, pageHeight, margins);
        newX = snap.x;
        newY = snap.y;
        setState(s => ({ ...s, guides: snap.guides }));
      }

      // Apply to all selected
      for (const [selId, orig] of dragStartRef.current.origPositions) {
        const offsetFromFirst = { x: orig.x - firstOrig.x, y: orig.y - firstOrig.y };
        onUpdateElement(selId, { x: newX + offsetFromFirst.x, y: newY + offsetFromFirst.y });
      }
    }

    if (state.isResizing && resizeStartRef.current && state.resizeHandle) {
      const dx = (e.clientX - resizeStartRef.current.x) / zoom;
      const dy = (e.clientY - resizeStartRef.current.y) / zoom;
      const orig = resizeStartRef.current.origBounds;
      const h = state.resizeHandle;
      let { x, y, w, h: height } = orig;

      if (h.includes('e')) w = Math.max(20, orig.w + dx);
      if (h.includes('s')) height = Math.max(20, orig.h + dy);
      if (h.includes('w')) { w = Math.max(20, orig.w - dx); x = orig.x + dx; }
      if (h.includes('n')) { height = Math.max(20, orig.h - dy); y = orig.y + dy; }

      if (e.shiftKey && orig.w > 0) {
        const ratio = orig.w / orig.h;
        if (h.includes('e') || h.includes('w')) height = w / ratio;
        else w = height * ratio;
      }

      if (state.selectedIds[0]) {
        onUpdateElement(state.selectedIds[0], { x, y, width: w, height });
      }
    }

    // Rotation
    if (state.isRotating && rotationStartRef.current && state.selectedIds[0]) {
      const el = elements.find(el => el.id === state.selectedIds[0]);
      if (el) {
        const { startAngle, initialRotation, cx, cy, pageRect: rect } = rotationStartRef.current;
        const mouseX = rect ? (e.clientX - rect.left) / zoom : e.clientX;
        const mouseY = rect ? (e.clientY - rect.top) / zoom : e.clientY;
        const currentAngle = Math.atan2(mouseY - cy, mouseX - cx) * (180 / Math.PI);
        let rotation = initialRotation + (currentAngle - startAngle);
        // Normalize to 0-360
        rotation = ((rotation % 360) + 360) % 360;
        // Snap to 15° increments when Shift held
        if (e.shiftKey) rotation = Math.round(rotation / 15) * 15;
        rotation = ((rotation % 360) + 360) % 360; // re-normalize after snap
        onUpdateElement(state.selectedIds[0], { rotation: Math.round(rotation) });
      }
    }
  }, [state, elements, zoom, pageWidth, pageHeight, margins, onUpdateElement]);

  const handlePointerUp = useCallback(() => {
    dragPendingRef.current = false;

    if (state.isDragging) {
      // Check if any selected element was dragged outside the current page bounds
      if (onMoveToPage && state.selectedIds.length === 1) {
        const el = elements.find(e => e.id === state.selectedIds[0]);
        if (el && !el.locked) {
          const centerX = el.x + el.width / 2;

          if (centerX > pageWidth) {
            // Dragged to the right — move to next page
            const newX = centerX - pageWidth - 24 / zoom; // account for page gap
            onMoveToPage(el.id, 'next', Math.max(0, newX - el.width / 2), el.y);
          } else if (centerX < 0) {
            // Dragged to the left — move to previous page
            const newX = pageWidth + centerX - 24 / zoom;
            onMoveToPage(el.id, 'prev', Math.max(0, newX - el.width / 2), el.y);
          }
        }
      }

      setState(s => ({ ...s, isDragging: false, isResizing: false, isRotating: false, resizeHandle: null, guides: [], marquee: null }));
      dragStartRef.current = null;
      resizeStartRef.current = null;
      rotationStartRef.current = null;
    } else if (state.isResizing || state.isRotating) {
      setState(s => ({ ...s, isDragging: false, isResizing: false, isRotating: false, resizeHandle: null, guides: [], marquee: null }));
      dragStartRef.current = null;
      resizeStartRef.current = null;
      rotationStartRef.current = null;
    }
  }, [state, elements, pageWidth, zoom, onMoveToPage]);

  useEffect(() => {
    window.addEventListener('pointermove', handlePointerMove);
    window.addEventListener('pointerup', handlePointerUp);
    return () => {
      window.removeEventListener('pointermove', handlePointerMove);
      window.removeEventListener('pointerup', handlePointerUp);
    };
  }, [handlePointerMove, handlePointerUp]);

  // Keyboard shortcuts
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement || (e.target as HTMLElement).isContentEditable) return;

      if (e.key === 'Delete' || e.key === 'Backspace') { if (state.selectedIds.length) { e.preventDefault(); onDeleteElements(state.selectedIds); clearSelection(); } }
      if (e.key === 'd' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); onDuplicateElements(state.selectedIds); }
      if (e.key === 'a' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); setState(s => ({ ...s, selectedIds: elements.filter(el => !el.locked).map(el => el.id) })); }
      if (e.key === 'Escape') clearSelection();

      // Tool shortcuts
      if (e.key === 'v') setTool('select');
      if (e.key === 't') setTool('text');
      if (e.key === 'i') setTool('image');
      if (e.key === 'r') setTool('rectangle');
      if (e.key === 'e') setTool('ellipse');
      if (e.key === 'l' && !e.ctrlKey) setTool('line');

      // Nudge
      if (state.selectedIds.length && ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
        e.preventDefault();
        const step = e.shiftKey ? 10 : 1;
        for (const id of state.selectedIds) {
          const el = elements.find(el => el.id === id);
          if (!el || el.locked) continue;
          if (e.key === 'ArrowUp') onUpdateElement(id, { y: el.y - step });
          if (e.key === 'ArrowDown') onUpdateElement(id, { y: el.y + step });
          if (e.key === 'ArrowLeft') onUpdateElement(id, { x: el.x - step });
          if (e.key === 'ArrowRight') onUpdateElement(id, { x: el.x + step });
        }
      }

      if (e.key === ';' && e.ctrlKey) { e.preventDefault(); toggleSnap(); }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [state.selectedIds, elements, onUpdateElement, onDeleteElements, onDuplicateElements, clearSelection, setTool, toggleSnap]);

  return {
    ...state,
    select,
    clearSelection,
    setTool,
    toggleSnap,
    setGridSize,
    handleElementPointerDown,
  };
}
