import React, { useRef, useState, useCallback, useEffect } from 'react';
import DOMPurify from 'dompurify';
import type { MagPageData, MagElement } from '@/types/magazine';
import { MagElementRenderer } from './MagElementRenderer';
import { useMagSelection } from './MagSelectionEngine';
import { useMagazineStore } from '@/stores/magazineStore';
// Threading engine disabled — Pour splits content at save time
import {
  MousePointer2, Type, ImageIcon, Square, Circle, Minus,
  ZoomIn, ZoomOut, Grid3X3, Magnet, Columns3, AlignVerticalSpaceAround,
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
  onToggleFixed?: (elementId: string, mode: 'free' | 'fixed') => void;
  onToggleSpan?: (elementId: string, mode: 'page' | 'spread') => void;
  onEditingChange?: (editingId: string | null) => void;
  startEditingId?: string | null;
  layoutMode?: 'single' | 'book' | 'presentation';
  coverMode?: 'standalone' | 'spread';
  /** engine flow state — threads whose chain currently oversets */
  oversetThreads?: Record<string, boolean>;
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
  onMoveToPage,
  onEditingChange,
  startEditingId,
  layoutMode = 'single',
  coverMode = 'standalone',
  onToggleFixed,
  onToggleSpan,
  oversetThreads,
}: MagazineCanvasProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const [pan, setPan] = useState({ x: 40, y: 40 });
  const [isPanning, setIsPanning] = useState(false);
  const panStartRef = useRef<{ x: number; y: number; panX: number; panY: number } | null>(null);

  // Guide toggles — ONE source of truth: the store (audit W0-3). The top
  // toolbar (MagazineToolbar) writes these same flags, so both toolbars agree.
  const showMargins = useMagazineStore((st) => st.showGuides);
  const showColumns = useMagazineStore((st) => st.showGrid);
  const showBaseline = useMagazineStore((st) => st.showBaseline);
  const toggleMargins = useMagazineStore((st) => st.toggleGuides);
  const toggleColumns = useMagazineStore((st) => st.toggleGrid);
  const toggleBaseline = useMagazineStore((st) => st.toggleBaseline);
  const storeActiveTool = useMagazineStore((st) => st.activeTool);

  const { width: pageW, height: pageH } = page.pageSize || { width: 595, height: 842 };
  const margins = page.margins || { top: 36, right: 36, bottom: 36, left: 36 };

  const selection = useMagSelection(
    elements, zoom, pageW, pageH, margins,
    onUpdateElement, onAddElement, onDeleteElements, onDuplicateElements,
    onMoveToPage,
  );

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
          if (currentHtml !== storedContent) {
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

    // Select tool: clear selection
    exitEditing();
    selection.clearSelection();
  }, [selection, pan, zoom, onAddElement, exitEditing]);

  const handleCanvasPointerMove = useCallback((e: React.PointerEvent) => {
    if (isPanning && panStartRef.current) {
      setPan({
        x: panStartRef.current.panX + (e.clientX - panStartRef.current.x),
        y: panStartRef.current.panY + (e.clientY - panStartRef.current.y),
      });
    }
  }, [isPanning]);

  const handleCanvasPointerUp = useCallback(() => {
    if (isPanning) {
      setIsPanning(false);
      panStartRef.current = null;
    }
  }, [isPanning]);

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
              <select className="select select-xs select-bordered w-14 text-[10px]"
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
      >
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

                      <div data-canvas="page" style={{ width: pgW, height: pgH, position: 'relative', background: pg.backgroundColor || '#ffffff', overflow: isBookSpread ? 'visible' : 'hidden' }}>
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
            {/* Margin guides — hide inner edges in book spread */}
            {showMargins && (
              <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 9000 }}>
                <div style={{ position: 'absolute', top: pgMargins.top, left: 0, right: 0, height: 0, borderTop: '1px dashed rgba(255,0,128,0.35)' }} />
                <div style={{ position: 'absolute', bottom: pgMargins.bottom, left: 0, right: 0, height: 0, borderTop: '1px dashed rgba(255,0,128,0.35)' }} />
                {/* Hide left margin on right page in book spread, hide right margin on left page */}
                {!(isBookSpread && pgIdx === 1) && <div style={{ position: 'absolute', left: pgMargins.left, top: 0, bottom: 0, width: 0, borderLeft: '1px dashed rgba(255,0,128,0.35)' }} />}
                {!(isBookSpread && pgIdx === 0) && <div style={{ position: 'absolute', right: pgMargins.right, top: 0, bottom: 0, width: 0, borderLeft: '1px dashed rgba(255,0,128,0.35)' }} />}
              </div>
            )}

            {/* Column guides overlay (audit W0-3: was computed, never rendered) */}
            {showColumns && columnGuides.length > 0 && (
              <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 9000 }}>
                {columnGuides.map((x, i) => (
                  <div key={`cg-${i}`} style={{ position: 'absolute', left: x, top: pgMargins.top, bottom: pgMargins.bottom, width: 0, borderLeft: '1px solid rgba(59,130,246,0.30)' }} />
                ))}
              </div>
            )}

            {/* Baseline grid overlay (audit W0-3: was computed, never rendered) */}
            {showBaseline && baselineLines.length > 0 && (
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
              return masterPage.elements.map(mel => {
                // Resolve dynamic page number
                const resolvedEl = mel.type === 'page_number'
                  ? { ...mel, data: { ...mel.data, startAt: page.pageNumber } }
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
                    <div className="absolute top-0 left-0 bg-warning/20 text-warning text-[6px] px-1 rounded-br font-bold pointer-events-none">MASTER</div>
                    <div className="absolute inset-0 border border-dashed border-warning/30 rounded pointer-events-none" />
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

            {/* Marquee selection (future) */}
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
