import React, { useRef, useState, useCallback, useEffect } from 'react';
import DOMPurify from 'dompurify';
import type { MagPageData, MagElement } from '@/types/magazine';
import { MagElementRenderer } from './MagElementRenderer';
import { useMagSelection } from './MagSelectionEngine';
import { useMagazineStore } from '@/stores/magazineStore';
import { pageSide, computeDisplayNumbers } from '@/lib/magazineFormat';
// Threading engine disabled — Pour splits content at save time
import {
  MousePointer2, Type, ImageIcon, Square, Circle, Minus,
  ZoomIn, ZoomOut, Grid3X3, Magnet, Columns3, AlignVerticalSpaceAround, Eye,
} from 'lucide-react';

type ViewMode = 'single' | 'spread' | 'grid';

interface MagazineCanvasProps {
  page: MagPageData;
  allPages?: MagPageData[];
  viewMode?: ViewMode;
  gridColumns?: number;
  elements: MagElement[];
  zoom: number;
  onZoomChange: (z: number) => void;
  onUpdateElement: (id: string, updates: Partial<MagElement>) => void;
  onAddElement: (type: string, x: number, y: number, w: number, h: number) => void;
  onDeleteElements: (ids: string[]) => void;
  onDuplicateElements: (ids: string[]) => void;
  onSelectElement: (id: string | null) => void;
  onPageClick?: (pageNumber: number) => void;
  onContinueText?: (elementId: string) => void;
  onMoveToPage?: (elementId: string, direction: 'prev' | 'next', newX: number, newY: number) => void;
  /** Session E: image file dropped on the page → upload + place a frame */
  onImageDrop?: (file: File, x: number, y: number) => void;
  onToggleFixed?: (elementId: string, mode: 'free' | 'fixed') => void;
  onToggleSpan?: (elementId: string, mode: 'page' | 'spread') => void;
  onEditingChange?: (editingId: string | null) => void;
  startEditingId?: string | null;
  layoutMode?: 'single' | 'book' | 'presentation';
  coverMode?: 'standalone' | 'spread';
  /** engine flow state — threads whose chain currently oversets */
  oversetThreads?: Record<string, boolean>;
  /** jump to a linked frame from a thread port badge (W1-5) */
  onNavigateThread?: (pageNumber: number, frameId: string) => void;
}

const ZOOM_STEPS = [0.25, 0.5, 0.75, 1, 1.25, 1.5, 2, 3, 4];

const TOOL_ICONS: Record<string, React.ReactNode> = {
  select: <MousePointer2 size={14} />,
  text: <Type size={14} />,
  image: <ImageIcon size={14} />,
  rectangle: <Square size={14} />,
  ellipse: <Circle size={14} />,
  line: <Minus size={14} />,
};

const TOOL_LABELS: Record<string, string> = {
  select: 'V',
  text: 'T',
  image: 'F',
  rectangle: 'R',
  ellipse: 'E',
  line: 'L',
};

export function MagazineCanvas({
  page,
  allPages,
  viewMode = 'single',
  gridColumns = 2,
  elements,
  zoom,
  onZoomChange,
  onUpdateElement,
  onAddElement,
  onDeleteElements,
  onDuplicateElements,
  onSelectElement,
  onPageClick,
  onContinueText,
  onMoveToPage, onImageDrop,
  onEditingChange,
  startEditingId,
  layoutMode = 'single',
  coverMode = 'standalone',
  onToggleFixed,
  onToggleSpan,
  oversetThreads,
  onNavigateThread,
}: MagazineCanvasProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const [pan, setPan] = useState({ x: 40, y: 40 });
  const [isPanning, setIsPanning] = useState(false);
  const panStartRef = useRef<{ x: number; y: number; panX: number; panY: number } | null>(null);
  // marquee drag (W2-6): page rect captured at start, coords page-local
  const marqueeRef = useRef<{ rect: DOMRect; x0: number; y0: number } | null>(null);
  // ruler guide drag (W2-1): new (index null) or existing guide being moved
  const [guideDrag, setGuideDrag] = useState<{ axis: 'v' | 'h'; pos: number; index: number | null } | null>(null);
  const guidePageRectRef = useRef<DOMRect | null>(null);
  const [snapMenuOpen, setSnapMenuOpen] = useState(false);
  const snapPrefs = useMagazineStore((st) => st.snapPrefs);
  const toggleSnapPref = useMagazineStore((st) => st.toggleSnapPref);
  const storeAddGuide = useMagazineStore((st) => st.addGuide);
  const storeMoveGuide = useMagazineStore((st) => st.moveGuide);
  const storeRemoveGuide = useMagazineStore((st) => st.removeGuide);

  const beginGuideDrag = useCallback((axis: 'v' | 'h', index: number | null, e: React.PointerEvent) => {
    const pageEl = viewportRef.current?.querySelector('[data-canvas="page"]');
    if (!pageEl) return;
    e.preventDefault();
    e.stopPropagation();
    guidePageRectRef.current = pageEl.getBoundingClientRect();
    const r = guidePageRectRef.current;
    const pos = axis === 'v' ? (e.clientX - r.left) / zoom : (e.clientY - r.top) / zoom;
    setGuideDrag({ axis, pos, index });
    try { (viewportRef.current as HTMLElement).setPointerCapture(e.pointerId); } catch (_) { /* noop */ }
  }, [zoom]);

  // Guide toggles — ONE source of truth: the store (audit W0-3). The top
  // toolbar (MagazineToolbar) writes these same flags, so both toolbars agree.
  const showMargins = useMagazineStore((st) => st.showGuides);
  const showColumns = useMagazineStore((st) => st.showGrid);
  const showBaseline = useMagazineStore((st) => st.showBaseline);
  const toggleMargins = useMagazineStore((st) => st.toggleGuides);
  const toggleColumns = useMagazineStore((st) => st.toggleGrid);
  const toggleBaseline = useMagazineStore((st) => st.toggleBaseline);
  const storeActiveTool = useMagazineStore((st) => st.activeTool);
  const previewMode = useMagazineStore((st) => st.previewMode);
  const togglePreview = useMagazineStore((st) => st.togglePreview);

  const { width: pageW, height: pageH } = page.pageSize || { width: 595, height: 842 };
  const margins = page.margins || { top: 36, right: 36, bottom: 36, left: 36 };

  const selection = useMagSelection(
    elements, zoom, pageW, pageH, margins,
    onUpdateElement, onAddElement, onDeleteElements, onDuplicateElements,
    onMoveToPage,
  );

  // right-click context menu (W3): element ops at the cursor
  const [ctxMenu, setCtxMenu] = useState<{ x: number; y: number; elementId: string | null } | null>(null);
  const storeApi = useMagazineStore;
  const handleContextMenu = useCallback((e: React.MouseEvent) => {
    if (previewMode) return;
    e.preventDefault();
    const hit = (e.target as HTMLElement).closest('[data-mag-el]');
    const id = hit?.getAttribute('data-mag-el') || null;
    if (id && !selection.selectedIds.includes(id)) selection.setSelectedIds?.([id]);
    setCtxMenu({ x: e.clientX, y: e.clientY, elementId: id });
  }, [previewMode, selection]);
  useEffect(() => {
    if (!ctxMenu) return;
    const close = () => setCtxMenu(null);
    window.addEventListener('pointerdown', close);
    window.addEventListener('keydown', close);
    return () => { window.removeEventListener('pointerdown', close); window.removeEventListener('keydown', close); };
  }, [ctxMenu]);

  // Top toolbar (MagazineToolbar) writes store.activeTool; mirror it into the
  // selection engine so BOTH toolbars drive one tool state (audit W0-3)
  useEffect(() => {
    if (selection.activeTool !== storeActiveTool) selection.setTool(storeActiveTool as any);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [storeActiveTool]);

  // Sync selection to parent — use ref to avoid infinite loops
  const prevSelectionRef = useRef<string>('');
  useEffect(() => {
    const key = selection.selectedIds.join(',');
    if (key === prevSelectionRef.current) return;
    prevSelectionRef.current = key;
    if (selection.selectedIds.length === 1) {
      onSelectElement(selection.selectedIds[0]);
    } else if (selection.selectedIds.length === 0) {
      try { exitEditing(); } catch(_) {}
      onSelectElement(null);
    }
  }, [selection.selectedIds]); // intentionally omit onSelectElement

  // Inline text editing state — must be before handleCanvasPointerDown
  const [editingId, setEditingId] = useState<string | null>(null);

  // Sanitize HTML from contentEditable — strip event handlers, scripts, dangerous attributes
  const sanitizeHtml = (html: string) => DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'u', 'em', 'strong', 'span', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'sub', 'sup', 'hr', 'div', 'img'],
    ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'style', 'src', 'alt', 'width', 'height'],
    FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover'],
    ALLOW_DATA_ATTR: false,
  });

  // Ref to track the editing element ID — avoids stale closure issues
  const editingIdRef = useRef<string | null>(null);
  useEffect(() => { editingIdRef.current = editingId; }, [editingId]);

  // Use ref to always have latest elements for flush — prevents stale closure content loss
  const elementsRef = useRef(elements);
  useEffect(() => { elementsRef.current = elements; }, [elements]);

  // (page-change flush moved after exitEditing definition)

  // Also track allPages for cross-page element lookup during flush
  const allPagesRef = useRef(allPages);
  useEffect(() => { allPagesRef.current = allPages; }, [allPages]);

  // Track whether blur already saved content — prevents double-save crash
  const blurSavedRef = useRef(false);
  // Session F: what the editable held when editing STARTED — exitEditing may
  // only flush when the USER changed it, never because the STORE moved on
  // (large-paste dialog / flow slices update content mid-session).
  const editEntryHtmlRef = useRef<string | null>(null);
  useEffect(() => {
    if (!editingId) { editEntryHtmlRef.current = null; return; }
    // editable mounts a tick after setEditingId
    const t = setTimeout(() => {
      const el = document.querySelector(`[data-editing-id="${CSS.escape(editingId)}"]`) as HTMLElement | null;
      editEntryHtmlRef.current = el ? el.innerHTML : null;
    }, 50);
    return () => clearTimeout(t);
  }, [editingId]);

  const exitEditing = useCallback(() => {
    const currentEditId = editingIdRef.current;
    if (!currentEditId) return;

    // If blur already saved, just clear editing state — don't re-process DOM
    if (blurSavedRef.current) {
      blurSavedRef.current = false;
      editingIdRef.current = null;
      setEditingId(null);
      return;
    }

    // Flush content from the contentEditable DOM before React unmounts it.
    try {
      const editableEl = document.querySelector(`[data-editing-id="${CSS.escape(currentEditId)}"]`) as HTMLElement | null;
      if (editableEl) {
        const currentHtml = sanitizeHtml(editableEl.innerHTML);
        let el = elementsRef.current.find(e => e.id === currentEditId);
        if (!el && allPagesRef.current) {
          for (const p of allPagesRef.current) {
            el = p.elements?.find(e => e.id === currentEditId);
            if (el) break;
          }
        }
        if (el) {
          const storedContent = (el.data as any)?.content || '';
          const entryHtml = editEntryHtmlRef.current;
          const userChanged = entryHtml === null || sanitizeHtml(entryHtml) !== currentHtml;
          if (userChanged && currentHtml !== storedContent) {
            onUpdateElement(currentEditId, { data: { ...(el.data || {}), content: currentHtml } } as any);
          }
        }
      }
    } catch (_e) {
      // DOM query failed — content was already saved by blur or element was unmounted
    }
    editingIdRef.current = null;
    setEditingId(null);
  }, [onUpdateElement]);

  // Flush editing when page changes — prevents content loss on page switch
  const prevPageRef = useRef(page.pageNumber);
  useEffect(() => {
    if (page.pageNumber !== prevPageRef.current) {
      exitEditing();
      prevPageRef.current = page.pageNumber;
    }
  }, [page.pageNumber, exitEditing]);

  // Notify parent when editing changes (for rich text toolbar in panel)
  useEffect(() => { onEditingChange?.(editingId); }, [editingId, onEditingChange]);

  // Direct Escape handler — ensures editing exits even if selection engine doesn't trigger effect
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && editingIdRef.current) {
        exitEditing();
      }
    };
    window.addEventListener('keydown', handleEscape, true); // capture phase
    return () => window.removeEventListener('keydown', handleEscape, true);
  }, [exitEditing]);

  // Handle external start-editing request (touchscreen "Edit text" button)
  // Also handles '__exit__' sentinel to force exit editing from Done button
  useEffect(() => {
    if (startEditingId === '__exit__') {
      exitEditing();
      return;
    }
    if (startEditingId && startEditingId !== editingId) {
      const el = elementsRef.current?.find(e => e.id === startEditingId);
      if (el) {
        const TEXT_TYPES = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'];
        if (TEXT_TYPES.includes(el.type) && !el.locked) {
          exitEditing();
          setEditingId(startEditingId);
        }
      }
    }
  }, [startEditingId, editingId, exitEditing]);

  const handleDoubleClick = useCallback((_e: React.MouseEvent, id: string) => {
    const el = elementsRef.current.find(e => e.id === id);
    if (!el) return;
    const TEXT_TYPES = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'];
    if (TEXT_TYPES.includes(el.type) && !el.locked) {
      // Ensure any previous editing is flushed first
      exitEditing();
      setEditingId(id);
    }
  }, [exitEditing]);

  const handleContentChange = useCallback((id: string, html: string) => {
    // Called on blur from contentEditable — save content.
    // Mark as saved so exitEditing() doesn't re-process the DOM.
    const el = elementsRef.current.find(e => e.id === id);
    if (!el) return;
    try {
      onUpdateElement(id, { data: { ...(el.data || {}), content: sanitizeHtml(html) } } as any);
      blurSavedRef.current = true;
    } catch (_e) {
      // Sanitize or update failed — don't crash the app
    }
  }, [onUpdateElement]);

  // Canvas background click -> clear selection or create element with active tool
  const handleCanvasPointerDown = useCallback((e: React.PointerEvent) => {
    // Middle mouse or space+click for panning
    if (e.button === 1 || (e.button === 0 && e.altKey)) {
      e.preventDefault();
      setIsPanning(true);
      panStartRef.current = { x: e.clientX, y: e.clientY, panX: pan.x, panY: pan.y };
      return;
    }

    const isPageClick = e.target === e.currentTarget || (e.target as HTMLElement).dataset?.canvas === 'page';
    if (!isPageClick) return;

    // If a drawing tool is active, create element at click position
    if (selection.activeTool !== 'select') {
      // Calculate position on the page
      const pageEl = (e.currentTarget as HTMLElement).querySelector('[data-canvas="page"]') || e.currentTarget;
      const rect = pageEl.getBoundingClientRect();
      const x = (e.clientX - rect.left) / zoom;
      const y = (e.clientY - rect.top) / zoom;

      // Default sizes per tool
      const sizes: Record<string, { w: number; h: number }> = {
        text: { w: 300, h: 100 },
        image: { w: 250, h: 200 },
        rectangle: { w: 200, h: 120 },
        ellipse: { w: 150, h: 150 },
        line: { w: 300, h: 4 },
      };
      const size = sizes[selection.activeTool] || { w: 200, h: 100 };

      onAddElement(selection.activeTool, Math.max(0, x), Math.max(0, y), size.w, size.h);

      // Switch back to select tool after creating
      selection.setTool('select');
      return;
    }

    // Select tool on empty page: begin MARQUEE (click = clear on pointerup)
    exitEditing();
    const pageEl = (e.target as HTMLElement).closest('[data-canvas="page"]') ||
      (e.currentTarget as HTMLElement).querySelector('[data-canvas="page"]');
    if (pageEl) {
      const rect = (pageEl as HTMLElement).getBoundingClientRect();
      const x0 = (e.clientX - rect.left) / zoom;
      const y0 = (e.clientY - rect.top) / zoom;
      marqueeRef.current = { rect, x0, y0 };
      selection.setMarquee({ x: x0, y: y0, width: 0, height: 0 });
      try { (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId); } catch (_) { /* noop */ }
    } else {
      selection.clearSelection();
    }
  }, [selection, pan, zoom, onAddElement, exitEditing]);

  const handleCanvasPointerMove = useCallback((e: React.PointerEvent) => {
    if (guideDrag && guidePageRectRef.current) {
      const r = guidePageRectRef.current;
      const pos = guideDrag.axis === 'v' ? (e.clientX - r.left) / zoom : (e.clientY - r.top) / zoom;
      setGuideDrag({ ...guideDrag, pos });
      return;
    }
    if (marqueeRef.current) {
      const { rect, x0, y0 } = marqueeRef.current;
      const x1 = (e.clientX - rect.left) / zoom;
      const y1 = (e.clientY - rect.top) / zoom;
      selection.setMarquee({
        x: Math.min(x0, x1), y: Math.min(y0, y1),
        width: Math.abs(x1 - x0), height: Math.abs(y1 - y0),
      });
      return;
    }
    if (isPanning && panStartRef.current) {
      setPan({
        x: panStartRef.current.panX + (e.clientX - panStartRef.current.x),
        y: panStartRef.current.panY + (e.clientY - panStartRef.current.y),
      });
    }
  }, [isPanning, guideDrag, zoom, selection]);

  const handleCanvasPointerUp = useCallback(() => {
    if (guideDrag) {
      const limit = guideDrag.axis === 'v' ? pageW : pageH;
      const inside = guideDrag.pos >= 0 && guideDrag.pos <= limit;
      if (inside && guideDrag.index === null) storeAddGuide(page.pageNumber, guideDrag.axis, guideDrag.pos);
      else if (inside && guideDrag.index !== null) storeMoveGuide(page.pageNumber, guideDrag.axis, guideDrag.index, guideDrag.pos);
      else if (!inside && guideDrag.index !== null) storeRemoveGuide(page.pageNumber, guideDrag.axis, guideDrag.index);
      setGuideDrag(null);
      guidePageRectRef.current = null;
      return;
    }
    if (marqueeRef.current) {
      const m = selection.marquee;
      marqueeRef.current = null;
      if (m && (m.width > 4 || m.height > 4)) {
        const hit = elements
          .filter(el => !el.locked && el.visible !== false &&
            el.x < m.x + m.width && el.x + el.width > m.x &&
            el.y < m.y + m.height && el.y + el.height > m.y)
          .map(el => el.id);
        selection.setSelectedIds(hit);
      } else {
        selection.clearSelection();
      }
      selection.setMarquee(null);
      return;
    }
    if (isPanning) {
      setIsPanning(false);
      panStartRef.current = null;
    }
  }, [isPanning, selection, elements, guideDrag, page.pageNumber, pageW, pageH, storeAddGuide, storeMoveGuide, storeRemoveGuide]);

  // Zoom with wheel
  const handleWheel = useCallback((e: React.WheelEvent) => {
    if (e.ctrlKey || e.metaKey) {
      e.preventDefault();
      const delta = e.deltaY > 0 ? -0.1 : 0.1;
      const newZoom = Math.max(0.1, Math.min(5, zoom + delta));
      onZoomChange(newZoom);
    } else {
      // Scroll to pan
      setPan(p => ({ x: p.x - e.deltaX, y: p.y - e.deltaY }));
    }
  }, [zoom, onZoomChange]);

  const zoomIn = () => {
    const next = ZOOM_STEPS.find(z => z > zoom);
    if (next) onZoomChange(next);
  };
  const zoomOut = () => {
    const prev = [...ZOOM_STEPS].reverse().find(z => z < zoom);
    if (prev) onZoomChange(prev);
  };
  const zoomFit = () => {
    if (!viewportRef.current) return;
    const vw = viewportRef.current.clientWidth - 80;
    const vh = viewportRef.current.clientHeight - 80;
    const fit = Math.min(vw / pageW, vh / pageH);
    onZoomChange(Math.round(fit * 100) / 100);
    setPan({ x: 40, y: 40 });
  };

  // (editing state, exitEditing, handleDoubleClick, handleContentChange moved above handleCanvasPointerDown)

  // Text threading disabled — Pour splits content at save time, each frame has its own content

  // Column guides
  const columnGuides: number[] = [];
  if (showColumns && page.columns?.count > 1) {
    const contentW = pageW - margins.left - margins.right;
    const colW = (contentW - (page.columns.count - 1) * page.columns.gutter) / page.columns.count;
    for (let i = 0; i < page.columns.count; i++) {
      const x = margins.left + i * (colW + page.columns.gutter);
      columnGuides.push(x);
      columnGuides.push(x + colW);
    }
  }

  // Baseline grid lines
  const baselineLines: number[] = [];
  if (showBaseline && page.baselineGrid?.increment > 0) {
    let y = page.baselineGrid?.start || margins.top;
    while (y < pageH - margins.bottom) {
      baselineLines.push(y);
      y += page.baselineGrid.increment;
    }
  }

  return (
    <div className="flex flex-col h-full w-full">
      {/* Toolbar */}
      <div className="flex items-center gap-1 px-2 py-1 bg-base-200 border-b border-base-300 shrink-0">
        {/* Tool buttons */}
        <div className="flex items-center gap-0.5 mr-2">
          {(['select', 'text', 'image', 'rectangle', 'ellipse', 'line'] as const).map(tool => (
            <button
              key={tool}
              className={`btn btn-xs ${selection.activeTool === tool ? 'btn-primary' : 'btn-ghost'}`}
              onClick={() => selection.setTool(tool)}
              title={`${tool} (${TOOL_LABELS[tool]})`}
            >
              {TOOL_ICONS[tool]}
            </button>
          ))}
        </div>

        <div className="w-px h-5 bg-base-300 mx-1" />

        {/* Zoom controls */}
        <button className="btn btn-ghost btn-xs" onClick={zoomOut} title="Zoom out"><ZoomOut size={14} /></button>
        <button className="btn btn-ghost btn-xs min-w-[48px] font-mono text-[11px]" onClick={zoomFit} title="Fit to view">
          {Math.round(zoom * 100)}%
        </button>
        <button className="btn btn-ghost btn-xs" onClick={zoomIn} title="Zoom in"><ZoomIn size={14} /></button>

        <div className="w-px h-5 bg-base-300 mx-1" />

        {/* Guide toggles */}
        <button
          className={`btn btn-xs ${showMargins ? 'btn-secondary' : 'btn-ghost'}`}
          onClick={toggleMargins}
          title="Toggle margins"
        >
          <AlignVerticalSpaceAround size={14} />
        </button>
        <button
          className={`btn btn-xs ${showColumns ? 'btn-secondary' : 'btn-ghost'}`}
          onClick={toggleColumns}
          title="Toggle column guides"
        >
          <Columns3 size={14} />
        </button>
        <button
          className={`btn btn-xs ${showBaseline ? 'btn-secondary' : 'btn-ghost'}`}
          onClick={toggleBaseline}
          title="Toggle baseline grid"
        >
          <Grid3X3 size={14} />
        </button>
        <button
          className={`btn btn-xs ${selection.snapEnabled ? 'btn-accent' : 'btn-ghost'}`}
          onClick={selection.toggleSnap}
          title="Toggle snap (Ctrl+;)"
        >
          <Magnet size={14} />
        </button>
        <button
          className={`btn btn-xs ${previewMode ? 'btn-primary' : 'btn-ghost'}`}
          onClick={togglePreview}
          title="Preview mode — hide all editor chrome (W)"
        >
          <Eye size={14} />
        </button>
        <div className="relative">
          <button className="btn btn-xs btn-ghost px-1" onClick={() => setSnapMenuOpen(v => !v)} title="Snapping options">▾</button>
          {snapMenuOpen && (
            <div className="absolute top-6 left-0 z-[10001] bg-base-100 border border-base-300 shadow-lg p-2 space-y-1 w-36">
              {([['grid', 'Grid'], ['guides', 'Guides'], ['margins', 'Margins'], ['objects', 'Objects'], ['baseline', 'Baseline']] as const).map(([k, lbl]) => (
                <label key={k} className="flex items-center gap-1.5 text-[10px] cursor-pointer">
                  <input type="checkbox" name={`snap-${k}`} className="checkbox checkbox-xs" checked={snapPrefs[k]} onChange={() => toggleSnapPref(k)} />
                  Snap to {lbl}
                </label>
              ))}
            </div>
          )}
        </div>

        <div className="w-px h-5 bg-base-300 mx-1" />

        {/* View mode selector */}
        {allPages && (
          <div className="flex items-center gap-0.5">
            <button className={`btn btn-xs ${viewMode === 'single' ? 'btn-primary' : 'btn-ghost'}`}
              onClick={() => onPageClick && onPageClick(-1)} title="Single page">
              <span className="text-[10px]">1</span>
            </button>
            <button className={`btn btn-xs ${viewMode === 'spread' ? 'btn-primary' : 'btn-ghost'}`}
              onClick={() => onPageClick && onPageClick(-2)} title="Spread (2 pages)">
              <span className="text-[10px]">2</span>
            </button>
            <button className={`btn btn-xs ${viewMode === 'grid' ? 'btn-primary' : 'btn-ghost'}`}
              onClick={() => onPageClick && onPageClick(-3)} title="Grid view">
              <span className="text-[10px]">G</span>
            </button>
            {viewMode === 'grid' && (
              <select name="mag-magazinecanvas-1" className="select select-xs select-bordered w-14 text-[10px]"
                value={gridColumns} onChange={e => onPageClick && onPageClick(-(10 + parseInt(e.target.value)))}>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
              </select>
            )}
          </div>
        )}

        <div className="flex-1" />

        {/* Selection info */}
        {selection.selectedIds.length > 0 && (
          <span className="text-xs text-base-content/50">
            {selection.selectedIds.length} selected
          </span>
        )}
      </div>

      {/* Canvas viewport */}
      <div
        ref={viewportRef}
        className="flex-1 overflow-hidden bg-base-300/50 relative"
        style={{ cursor: isPanning ? 'grabbing' : selection.activeTool === 'select' ? 'default' : 'crosshair' }}
        onPointerDown={handleCanvasPointerDown}
        onPointerMove={handleCanvasPointerMove}
        onPointerUp={handleCanvasPointerUp}
        onWheel={handleWheel}
        onContextMenu={handleContextMenu}
        onDragOver={(e) => { if (e.dataTransfer?.types?.includes('Files')) e.preventDefault(); }}
        onDrop={(e) => {
          const file = Array.from(e.dataTransfer?.files || []).find(f => f.type.startsWith('image/'));
          if (!file || !onImageDrop) return;
          e.preventDefault();
          const pageEl = viewportRef.current?.querySelector('[data-canvas="page"]');
          const r = pageEl?.getBoundingClientRect();
          const x = r ? (e.clientX - r.left) / zoom : 60;
          const y = r ? (e.clientY - r.top) / zoom : 60;
          onImageDrop(file, Math.max(0, x), Math.max(0, y));
        }}
      >
        {/* Rulers (W2-1) — drag OUT of a ruler to create a guide */}
        {!previewMode && (
          <>
            <div
              className="absolute top-0 left-5 right-0 h-5 bg-base-200/95 border-b border-base-300 z-[10000] cursor-row-resize select-none overflow-hidden"
              title="Drag down to create a horizontal guide"
              onPointerDown={(e) => beginGuideDrag('h', null, e)}
            >
              {Array.from({ length: Math.floor(pageW / 25) + 1 }, (_, i) => i * 25).map((pt) => (
                <div key={pt} style={{ position: 'absolute', left: pan.x + pt * zoom - 20, bottom: 0, width: 1, height: pt % 100 === 0 ? 10 : 5, background: 'rgba(120,113,108,0.55)' }}>
                  {pt % 100 === 0 && <span style={{ position: 'absolute', bottom: 9, left: 2, fontSize: 7, color: 'rgba(120,113,108,0.8)' }}>{pt}</span>}
                </div>
              ))}
            </div>
            <div
              className="absolute top-5 left-0 bottom-0 w-5 bg-base-200/95 border-r border-base-300 z-[10000] cursor-col-resize select-none overflow-hidden"
              title="Drag right to create a vertical guide"
              onPointerDown={(e) => beginGuideDrag('v', null, e)}
            >
              {Array.from({ length: Math.floor(pageH / 25) + 1 }, (_, i) => i * 25).map((pt) => (
                <div key={pt} style={{ position: 'absolute', top: pan.y + pt * zoom, right: 0, height: 1, width: pt % 100 === 0 ? 10 : 5, background: 'rgba(120,113,108,0.55)' }}>
                  {pt % 100 === 0 && <span style={{ position: 'absolute', left: -1, top: 2, fontSize: 7, color: 'rgba(120,113,108,0.8)', writingMode: 'vertical-rl' }}>{pt}</span>}
                </div>
              ))}
            </div>
          </>
        )}
        {ctxMenu && (() => {
          const st = storeApi.getState();
          const selIds = selection.selectedIds.length ? selection.selectedIds : (ctxMenu.elementId ? [ctxMenu.elementId] : []);
          const one = selIds.length === 1 ? st.pages.flatMap(pp => pp.elements).find(el2 => el2.id === selIds[0]) : null;
          const isGroup = one && (one.type === 'group' || one.type === 'clipping_group');
          const item = 'w-full text-left px-3 py-1 text-[11px] hover:bg-base-300/30 disabled:opacity-30 disabled:hover:bg-transparent';
          const run = (fn: () => void) => { fn(); setCtxMenu(null); };
          return (
            <div className="fixed z-[10005] bg-base-100 border border-base-300 shadow-xl rounded py-1 w-44"
              style={{ left: Math.min(ctxMenu.x, window.innerWidth - 190), top: Math.min(ctxMenu.y, window.innerHeight - 300) }}
              onPointerDown={(e) => e.stopPropagation()}>
              <button className={item} disabled={!selIds.length} onClick={() => run(() => st.copy())}>Copy <span className="float-right text-base-content/30">Ctrl+C</span></button>
              <button className={item} disabled={!selIds.length} onClick={() => run(() => st.cut())}>Cut <span className="float-right text-base-content/30">Ctrl+X</span></button>
              <button className={item} onClick={() => run(() => st.paste())}>Paste <span className="float-right text-base-content/30">Ctrl+V</span></button>
              <button className={item} disabled={!selIds.length} onClick={() => run(() => st.duplicateElements(selIds))}>Duplicate <span className="float-right text-base-content/30">Ctrl+D</span></button>
              <div className="border-t border-base-300/30 my-1" />
              <button className={item} disabled={selIds.length < 2} onClick={() => run(() => st.groupElements(selIds))}>Group <span className="float-right text-base-content/30">Ctrl+G</span></button>
              <button className={item} disabled={!isGroup} onClick={() => run(() => st.ungroupElements(selIds[0]))}>Ungroup <span className="float-right text-base-content/30">⇧Ctrl+G</span></button>
              <button className={item} disabled={selIds.length !== 1} onClick={() => run(() => st.bringForward(selIds[0]))}>Bring forward</button>
              <button className={item} disabled={selIds.length !== 1} onClick={() => run(() => st.sendBackward(selIds[0]))}>Send backward</button>
              <div className="border-t border-base-300/30 my-1" />
              <button className={item} disabled={!one} onClick={() => run(() => st.updateElement(selIds[0], { locked: !one!.locked } as any))}>{one?.locked ? 'Unlock' : 'Lock'}</button>
              <button className={`${item} text-error`} disabled={!selIds.length} onClick={() => run(() => st.deleteElements(selIds))}>Delete <span className="float-right text-base-content/30">Del</span></button>
            </div>
          );
        })()}
        {/* Transformed layer */}
        <div
          style={{
            transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
            transformOrigin: '0 0',
            position: 'absolute',
            top: 0,
            left: 0,
          }}
        >
          {/* Multi-page layout wrapper */}
          {(() => {
            // Determine which pages to show
            const pagesToShow: MagPageData[] = (() => {
              if (!allPages || allPages.length === 0) return [page];
              const contentPages = allPages.filter(p => !p.isMaster);

              if (viewMode === 'single') return [page];

              if (viewMode === 'spread') {
                if (layoutMode === 'book') {
                  // Book mode: cover alone (page 1), then pairs (2-3, 4-5, ...)
                  const idx = contentPages.findIndex(p => p.pageNumber === page.pageNumber);
                  if (coverMode === 'standalone' && idx === 0) return [contentPages[0]]; // cover alone
                  // Adjust index for standalone cover offset
                  const adjIdx = coverMode === 'standalone' ? idx - 1 : idx;
                  const startIdx = adjIdx % 2 === 0 ? idx : Math.max(coverMode === 'standalone' ? 1 : 0, idx - 1);
                  return contentPages.slice(startIdx, startIdx + 2);
                }
                // Default spread: pair by index
                const idx = contentPages.findIndex(p => p.pageNumber === page.pageNumber);
                const startIdx = idx % 2 === 0 ? idx : Math.max(0, idx - 1);
                return contentPages.slice(startIdx, startIdx + 2);
              }

              if (viewMode === 'grid' && layoutMode === 'presentation') {
                // Presentation: show only current page (one slide at a time)
                return [page];
              }

              return contentPages; // grid: show all
            })();

            const cols = viewMode === 'grid' ? gridColumns : viewMode === 'spread' ? pagesToShow.length : 1;
            const isBookSpread = viewMode === 'spread' && layoutMode === 'book' && pagesToShow.length === 2;
            const pageGap = viewMode === 'single' ? 0 : isBookSpread ? 0 : 24;

            return (
              <div style={{
                display: 'grid',
                gridTemplateColumns: `repeat(${cols}, ${pageW}px)`,
                gap: pageGap,
                position: 'relative',
              }}>
                {/* Red divider line between spread pages in book mode */}
                {isBookSpread && (
                  <div style={{
                    position: 'absolute',
                    left: pageW,
                    top: -4,
                    bottom: -4,
                    width: 2,
                    background: '#ef4444',
                    zIndex: 10001,
                    pointerEvents: 'none',
                  }} />
                )}
                {pagesToShow.map((pg, pgIdx) => {
                  const pgElements = pg.elements || [];
                  const sortedEls = [...pgElements].sort((a, b) => a.zIndex - b.zIndex);
                  const isActivePage = pg.pageNumber === page.pageNumber;
                  const pgMargins = pg.margins || { top: 36, right: 36, bottom: 36, left: 36 };
                  const pgW = pg.pageSize?.width || pageW;
                  const pgH = pg.pageSize?.height || pageH;

                  // For spread images: find elements spanning across both pages
                  const spreadElements = isBookSpread ? pgElements.filter(e => e.spanMode === 'spread') : [];
                  const isLeftPage = isBookSpread && pgIdx === 0;

                  return (
                    <div key={pg.id || pg.pageNumber} style={{ position: 'relative' }}
                      onClick={() => onPageClick && !isActivePage && onPageClick(pg.pageNumber)}>
                      {/* Page shadow — full spread shadow for book mode */}
                      {isBookSpread ? (
                        pgIdx === 0 && <div style={{ width: pgW * 2, height: pgH, position: 'absolute', boxShadow: '0 2px 16px rgba(0,0,0,0.15)', borderRadius: 1 }} />
                      ) : (
                        <div style={{ width: pgW, height: pgH, position: 'absolute', boxShadow: '0 2px 16px rgba(0,0,0,0.15)', borderRadius: 1 }} />
                      )}

                      {/* Page number label */}
                      {viewMode !== 'single' && (
                        <div style={{ position: 'absolute', top: -18, left: 0, right: 0, textAlign: 'center' }}>
                          <span style={{ fontSize: 10, color: isActivePage ? '#3b82f6' : 'rgba(255,255,255,0.4)', fontWeight: isActivePage ? 600 : 400 }}>
                            {pg.pageNumber}
                          </span>
                        </div>
                      )}

                      {/* Active page indicator */}
                      {viewMode !== 'single' && isActivePage && !isBookSpread && (
                        <div style={{ position: 'absolute', inset: -2, border: '2px solid #3b82f6', borderRadius: 2, pointerEvents: 'none', zIndex: 9999 }} />
                      )}

                      {/* Non-active page overlay in grid mode */}
                      {viewMode === 'grid' && !isActivePage && (
                        <div style={{ position: 'absolute', inset: 0, zIndex: 9998, cursor: 'pointer' }} />
                      )}

                      <div data-canvas="page" style={{ width: pgW, height: pgH, position: 'relative', background: pg.backgroundColor || '#ffffff', overflow: previewMode && !isBookSpread ? 'hidden' : 'visible' }}>
                        {/* Spread image elements that span across both pages */}
                        {isLeftPage && spreadElements.map(el => (
                          <div key={`spread-${el.id}`} style={{
                            position: 'absolute',
                            left: el.x,
                            top: el.y,
                            width: pgW * 2 - el.x, // span to right edge of right page
                            height: el.height,
                            zIndex: el.zIndex + 1,
                            pointerEvents: 'none',
                            overflow: 'hidden',
                          }}>
                            {(el.data as any)?.src && (
                              <img
                                src={(el.data as any).src}
                                alt={(el.data as any)?.alt || ''}
                                style={{ width: '100%', height: '100%', objectFit: (el.data as any)?.fit || 'cover' }}
                              />
                            )}
                            <div style={{ position: 'absolute', top: 2, left: 2, background: 'rgba(168,85,247,0.8)', color: 'white', fontSize: 7, padding: '1px 4px', borderRadius: 2, fontWeight: 700 }}>SPREAD</div>
                          </div>
                        ))}
            {/* Ruler guides (W2-1): cyan lines, drag to move, drag off page to delete */}
            {!previewMode && (() => {
              const g = (page as any)._guides as { v: number[]; h: number[] } | undefined;
              const isCurrent = pg.pageNumber === page.pageNumber;
              if (!g || !isCurrent) return null;
              return (
                <div style={{ position: 'absolute', inset: 0, zIndex: 9500, pointerEvents: 'none' }}>
                  {(g.v || []).map((x, i) => (
                    <div key={`gv-${i}`} title={`x = ${x}`} onPointerDown={(e) => beginGuideDrag('v', i, e)}
                      style={{ position: 'absolute', left: x, top: 0, bottom: 0, width: 0, borderLeft: '1px solid rgba(34,211,238,0.85)', cursor: 'col-resize', pointerEvents: 'auto' }} />
                  ))}
                  {(g.h || []).map((y, i) => (
                    <div key={`gh-${i}`} title={`y = ${y}`} onPointerDown={(e) => beginGuideDrag('h', i, e)}
                      style={{ position: 'absolute', top: y, left: 0, right: 0, height: 0, borderTop: '1px solid rgba(34,211,238,0.85)', cursor: 'row-resize', pointerEvents: 'auto' }} />
                  ))}
                  {guideDrag && (
                    guideDrag.axis === 'v'
                      ? <div style={{ position: 'absolute', left: guideDrag.pos, top: 0, bottom: 0, width: 0, borderLeft: '1px dashed rgba(34,211,238,1)' }} />
                      : <div style={{ position: 'absolute', top: guideDrag.pos, left: 0, right: 0, height: 0, borderTop: '1px dashed rgba(34,211,238,1)' }} />
                  )}
                </div>
              );
            })()}

            {/* Margin guides — hide inner edges in book spread */}
            {showMargins && !previewMode && (
              <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 9000 }}>
                <div style={{ position: 'absolute', top: pgMargins.top, left: 0, right: 0, height: 0, borderTop: '1px dashed rgba(255,0,128,0.35)' }} />
                <div style={{ position: 'absolute', bottom: pgMargins.bottom, left: 0, right: 0, height: 0, borderTop: '1px dashed rgba(255,0,128,0.35)' }} />
                {/* Hide left margin on right page in book spread, hide right margin on left page */}
                {!(isBookSpread && pgIdx === 1) && <div style={{ position: 'absolute', left: pgMargins.left, top: 0, bottom: 0, width: 0, borderLeft: '1px dashed rgba(255,0,128,0.35)' }} />}
                {!(isBookSpread && pgIdx === 0) && <div style={{ position: 'absolute', right: pgMargins.right, top: 0, bottom: 0, width: 0, borderLeft: '1px dashed rgba(255,0,128,0.35)' }} />}
              </div>
            )}

            {/* Column guides overlay (audit W0-3: was computed, never rendered) */}
            {showColumns && !previewMode && columnGuides.length > 0 && (
              <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 9000 }}>
                {columnGuides.map((x, i) => (
                  <div key={`cg-${i}`} style={{ position: 'absolute', left: x, top: pgMargins.top, bottom: pgMargins.bottom, width: 0, borderLeft: '1px solid rgba(59,130,246,0.30)' }} />
                ))}
              </div>
            )}

            {/* Baseline grid overlay (audit W0-3: was computed, never rendered) */}
            {showBaseline && !previewMode && baselineLines.length > 0 && (
              <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 9000 }}>
                {baselineLines.map((y, i) => (
                  <div key={`bl-${i}`} style={{ position: 'absolute', top: y, left: pgMargins.left, right: pgMargins.right, height: 0, borderTop: '1px solid rgba(56,189,248,0.22)' }} />
                ))}
              </div>
            )}

            {/* Master elements (read-only, rendered behind page elements) */}
            {(() => {
              if (!page.masterPageId || !allPages) return null;
              const masterPage = allPages.find(p => p.id === page.masterPageId && p.isMaster);
              if (!masterPage) return null;
              // verso/recto application (masters v2)
              const applies = (masterPage as any)._appliesTo || 'all';
              if (applies !== 'all' && pageSide(page.pageNumber, coverMode) !== applies) return null;
              return masterPage.elements.map(mel => {
                // Resolve dynamic page number
                const disp = computeDisplayNumbers(allPages as any)[page.pageNumber];
                const resolvedEl = mel.type === 'page_number'
                  ? { ...mel, data: { ...mel.data, startAt: disp?.n ?? page.pageNumber, format: (mel.data as any)?.format && (mel.data as any).format !== 'decimal' ? (mel.data as any).format : (disp?.format || 'decimal') } }
                  : mel;
                return (
                  <div key={`master-${mel.id}`} style={{ position: 'absolute', left: mel.x || 0, top: mel.y || 0, width: mel.width || 100, height: mel.height || 20, zIndex: mel.zIndex || 0, opacity: 0.6, pointerEvents: 'none' }}>
                    <MagElementRenderer
                      element={resolvedEl}
                      isSelected={false}
                      isHovered={false}
                      zoom={zoom}
                      onPointerDown={() => {}}
                      onDoubleClick={() => {}}
                    />
                    {!previewMode && <div className="absolute top-0 left-0 bg-warning/20 text-warning text-[6px] px-1 rounded-br font-bold pointer-events-none">MASTER</div>}
                    {!previewMode && <div className="absolute inset-0 border border-dashed border-warning/30 rounded pointer-events-none" />}
                  </div>
                );
              });
            })()}

            {/* Elements */}
            {sortedEls.map(el => (
              <MagElementRenderer
                key={el.id}
                element={el}
                isSelected={selection.selectedIds.includes(el.id)}
                isHovered={selection.hoveredId === el.id}
                isEditing={editingId === el.id}
                threadedContent={undefined}
                zoom={zoom}
                onPointerDown={(e, id) => { try { exitEditing(); } catch(_) {} selection.handleElementPointerDown(e, id); }}
                onDoubleClick={handleDoubleClick}
                onContentChange={handleContentChange}
                onContinueText={onContinueText}
                onToggleFixed={onToggleFixed}
                onToggleSpan={onToggleSpan}
                onStartEditing={(id) => {
                  exitEditing();
                  setEditingId(id);
                }}
                onStopEditing={() => {
                  exitEditing();
                }}
                allPages={allPages}
                oversetThreads={oversetThreads}
                onNavigateThread={onNavigateThread}
                previewMode={previewMode}
              />
            ))}

            {/* Smart guide lines */}
            {selection.guides.map((guide, i) => (
              <div
                key={`guide-${i}`}
                style={{
                  position: 'absolute',
                  pointerEvents: 'none',
                  zIndex: 10000,
                  ...(guide.type === 'vertical'
                    ? { left: guide.position, top: 0, bottom: 0, width: 0, borderLeft: '1px solid rgba(255,50,50,0.7)' }
                    : { top: guide.position, left: 0, right: 0, height: 0, borderTop: '1px solid rgba(255,50,50,0.7)' }
                  ),
                }}
              />
            ))}

            {/* Marquee selection (W2-6) */}
            {selection.marquee && (
              <div
                style={{
                  position: 'absolute',
                  left: selection.marquee.x,
                  top: selection.marquee.y,
                  width: selection.marquee.width,
                  height: selection.marquee.height,
                  border: '1px dashed rgba(59,130,246,0.6)',
                  background: 'rgba(59,130,246,0.08)',
                  pointerEvents: 'none',
                  zIndex: 10000,
                }}
              />
            )}
                      </div>
                    </div>
                  );
                })}
              </div>
            );
          })()}
          {/* end multi-page layout */}
        </div>
      </div>
    </div>
  );
}
