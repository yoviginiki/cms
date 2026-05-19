import React, { useRef, useState, useCallback, useEffect } from 'react';
import DOMPurify from 'dompurify';
import type { MagPageData, MagElement } from '@/types/magazine';
import { MagElementRenderer } from './MagElementRenderer';
import { useMagSelection } from './MagSelectionEngine';
import {
  MousePointer2, Type, ImageIcon, Square, Circle, Minus,
  ZoomIn, ZoomOut, Grid3X3, Magnet, Columns3, AlignVerticalSpaceAround,
} from 'lucide-react';

interface MagazineCanvasProps {
  page: MagPageData;
  elements: MagElement[];
  zoom: number;
  onZoomChange: (z: number) => void;
  onUpdateElement: (id: string, updates: Partial<MagElement>) => void;
  onAddElement: (type: string, x: number, y: number, w: number, h: number) => void;
  onDeleteElements: (ids: string[]) => void;
  onDuplicateElements: (ids: string[]) => void;
  onSelectElement: (id: string | null) => void;
  
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
  elements,
  zoom,
  onZoomChange,
  onUpdateElement,
  onAddElement,
  onDeleteElements,
  onDuplicateElements,
  onSelectElement,
}: MagazineCanvasProps) {
  const viewportRef = useRef<HTMLDivElement>(null);
  const [pan, setPan] = useState({ x: 40, y: 40 });
  const [isPanning, setIsPanning] = useState(false);
  const panStartRef = useRef<{ x: number; y: number; panX: number; panY: number } | null>(null);

  // Guide toggles
  const [showMargins, setShowMargins] = useState(true);
  const [showColumns, setShowColumns] = useState(false);
  const [showBaseline, setShowBaseline] = useState(false);

  const { width: pageW, height: pageH } = page.pageSize;
  const margins = page.margins;

  const selection = useMagSelection(
    elements, zoom, pageW, pageH, margins,
    onUpdateElement, onAddElement, onDeleteElements, onDuplicateElements,
  );

  // Sync selection to parent — use ref to avoid infinite loops
  const prevSelectionRef = useRef<string>('');
  useEffect(() => {
    const key = selection.selectedIds.join(',');
    if (key === prevSelectionRef.current) return;
    prevSelectionRef.current = key;
    if (selection.selectedIds.length === 1) {
      onSelectElement(selection.selectedIds[0]);
    } else if (selection.selectedIds.length === 0) {
      onSelectElement(null);
    }
  }, [selection.selectedIds]); // intentionally omit onSelectElement

  // Inline text editing state — must be before handleCanvasPointerDown
  const [editingId, setEditingId] = useState<string | null>(null);

  // Sanitize HTML from contentEditable — strip event handlers, scripts, dangerous attributes
  const sanitizeHtml = (html: string) => DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'u', 'em', 'strong', 'span', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'sub', 'sup', 'hr', 'div'],
    ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'style'],
    FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover'],
    ALLOW_DATA_ATTR: false,
  });

  // Ref to track the editing element ID — avoids stale closure issues
  const editingIdRef = useRef<string | null>(null);
  useEffect(() => { editingIdRef.current = editingId; }, [editingId]);

  const exitEditing = useCallback(() => {
    const currentEditId = editingIdRef.current;
    if (!currentEditId) return;
    // Flush content from the contentEditable DOM before React unmounts it — scoped by data attribute
    const editableEl = document.querySelector(`[data-editing-id="${currentEditId}"]`) as HTMLElement | null
      ?? document.querySelector('[contenteditable="true"]') as HTMLElement | null;
    if (editableEl) {
      const el = elements.find(e => e.id === currentEditId);
      onUpdateElement(currentEditId, { data: { ...(el?.data || {}), content: sanitizeHtml(editableEl.innerHTML) } } as any);
    }
    editingIdRef.current = null;
    setEditingId(null);
  }, [elements, onUpdateElement]);

  const handleDoubleClick = useCallback((_e: React.MouseEvent, id: string) => {
    const el = elements.find(e => e.id === id);
    if (!el) return;
    const TEXT_TYPES = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'];
    if (TEXT_TYPES.includes(el.type) && !el.locked) {
      setEditingId(id);
    }
  }, [elements]);

  const handleContentChange = useCallback((id: string, html: string) => {
    // Skip if exitEditing already flushed (editingIdRef is null)
    if (!editingIdRef.current) return;
    onUpdateElement(id, { data: { ...(elements.find(e => e.id === id)?.data || {}), content: sanitizeHtml(html) } } as any);
    editingIdRef.current = null;
    setEditingId(null);
  }, [elements, onUpdateElement]);

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

  // Sort elements by zIndex for rendering
  const sortedElements = [...elements].sort((a, b) => a.zIndex - b.zIndex);

  // Column guides
  const columnGuides: number[] = [];
  if (showColumns && page.columns.count > 1) {
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
  if (showBaseline && page.baselineGrid.increment > 0) {
    let y = page.baselineGrid.start || margins.top;
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
          onClick={() => setShowMargins(!showMargins)}
          title="Toggle margins"
        >
          <AlignVerticalSpaceAround size={14} />
        </button>
        <button
          className={`btn btn-xs ${showColumns ? 'btn-secondary' : 'btn-ghost'}`}
          onClick={() => setShowColumns(!showColumns)}
          title="Toggle column guides"
        >
          <Columns3 size={14} />
        </button>
        <button
          className={`btn btn-xs ${showBaseline ? 'btn-secondary' : 'btn-ghost'}`}
          onClick={() => setShowBaseline(!showBaseline)}
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
          {/* Page shadow */}
          <div
            style={{
              width: pageW,
              height: pageH,
              position: 'absolute',
              boxShadow: '0 2px 16px rgba(0,0,0,0.15)',
              borderRadius: 1,
            }}
          />

          {/* Page */}
          <div
            data-canvas="page"
            style={{
              width: pageW,
              height: pageH,
              position: 'relative',
              background: page.backgroundColor || '#ffffff',
              overflow: 'hidden',
            }}
          >
            {/* Margin guides */}
            {showMargins && (
              <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 9000 }}>
                {/* Top */}
                <div style={{ position: 'absolute', top: margins.top, left: 0, right: 0, height: 0, borderTop: '1px dashed rgba(255,0,128,0.35)' }} />
                {/* Bottom */}
                <div style={{ position: 'absolute', bottom: margins.bottom, left: 0, right: 0, height: 0, borderTop: '1px dashed rgba(255,0,128,0.35)' }} />
                {/* Left */}
                <div style={{ position: 'absolute', left: margins.left, top: 0, bottom: 0, width: 0, borderLeft: '1px dashed rgba(255,0,128,0.35)' }} />
                {/* Right */}
                <div style={{ position: 'absolute', right: margins.right, top: 0, bottom: 0, width: 0, borderLeft: '1px dashed rgba(255,0,128,0.35)' }} />
              </div>
            )}

            {/* Column guides */}
            {showColumns && columnGuides.map((x, i) => (
              <div
                key={`col-${i}`}
                style={{
                  position: 'absolute', left: x, top: margins.top,
                  bottom: margins.bottom, width: 0,
                  borderLeft: '1px solid rgba(255,100,150,0.25)',
                  pointerEvents: 'none', zIndex: 9000,
                }}
              />
            ))}

            {/* Baseline grid */}
            {showBaseline && baselineLines.map((y, i) => (
              <div
                key={`bl-${i}`}
                style={{
                  position: 'absolute', top: y, left: margins.left,
                  right: margins.right, height: 0,
                  borderTop: '1px dotted rgba(100,150,255,0.2)',
                  pointerEvents: 'none', zIndex: 9000,
                }}
              />
            ))}

            {/* Elements */}
            {sortedElements.map(el => (
              <MagElementRenderer
                key={el.id}
                element={el}
                isSelected={selection.selectedIds.includes(el.id)}
                isHovered={selection.hoveredId === el.id}
                isEditing={editingId === el.id}
                zoom={zoom}
                onPointerDown={(e, id) => { exitEditing(); selection.handleElementPointerDown(e, id); }}
                onDoubleClick={handleDoubleClick}
                onContentChange={handleContentChange}
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
      </div>
    </div>
  );
}
