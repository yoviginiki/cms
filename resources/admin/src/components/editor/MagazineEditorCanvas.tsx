import { useState, useRef, useCallback, useEffect } from 'react';
import { useEditorStore } from '@/stores/editorStore';
import { blockRegistry } from '@/components/blocks/registry';
import { useDroppable } from '@dnd-kit/core';
import { ZoomIn, ZoomOut, Grid3x3, Maximize } from 'lucide-react';
import type { BlockData } from '@/types/blocks';

// ─── Constants ───
const GRID_SIZE = 8;
const GUIDE_THRESHOLD = 6;

interface DragState {
  blockId: string;
  startX: number;
  startY: number;
  origX: number;
  origY: number;
}

interface ResizeState {
  blockId: string;
  handle: string;
  startX: number;
  startY: number;
  origX: number;
  origY: number;
  origW: number;
  origH: number;
}

interface Guide {
  type: 'vertical' | 'horizontal';
  position: number;
}

export function MagazineEditorCanvas() {
  const canvasRef = useRef<HTMLDivElement>(null);
  const viewportRef = useRef<HTMLDivElement>(null);

  const blocks = useEditorStore(s => s.blocks);
  const selectedBlockId = useEditorStore(s => s.selectedBlockId);
  const selectBlock = useEditorStore(s => s.selectBlock);
  const updateBlock = useEditorStore(s => s.updateBlock);
  const removeBlock = useEditorStore(s => s.removeBlock);

  const [zoom, setZoom] = useState(0.7);
  const [panOffset, setPanOffset] = useState({ x: 0, y: 0 });
  const [isPanning, setIsPanning] = useState(false);
  const [panStart, setPanStart] = useState({ x: 0, y: 0 });
  const [showGrid, setShowGrid] = useState(true);
  const [snapToGrid, setSnapToGrid] = useState(true);
  const [dragging, setDragging] = useState<DragState | null>(null);
  const [resizing, setResizing] = useState<ResizeState | null>(null);
  const [guides, setGuides] = useState<Guide[]>([]);
  const [spaceHeld, setSpaceHeld] = useState(false);

  // Canvas dimensions (configurable per page — A4 default at 96dpi)
  const canvasW = 794;
  const canvasH = 1123;

  const { setNodeRef: setDropRef } = useDroppable({ id: 'magazine-canvas' });

  // ─── Snap helper ───
  const snap = (v: number) => snapToGrid ? Math.round(v / GRID_SIZE) * GRID_SIZE : v;

  // ─── Smart guides ───
  const computeGuides = useCallback((movingId: string, x: number, y: number, w: number, h: number): Guide[] => {
    const result: Guide[] = [];
    const movingEdges = { left: x, right: x + w, cx: x + w / 2, top: y, bottom: y + h, cy: y + h / 2 };

    // Page center guides
    if (Math.abs(movingEdges.cx - canvasW / 2) < GUIDE_THRESHOLD) result.push({ type: 'vertical', position: canvasW / 2 });
    if (Math.abs(movingEdges.cy - canvasH / 2) < GUIDE_THRESHOLD) result.push({ type: 'horizontal', position: canvasH / 2 });

    blocks.forEach(b => {
      if (b.id === movingId) return;
      const bx = (b.style?.layout?.x as number) ?? 0;
      const by = (b.style?.layout?.y as number) ?? 0;
      const bw = parseFloat(String(b.style?.layout?.width ?? 200));
      const bh = parseFloat(String(b.style?.layout?.minHeight ?? 100));
      const edges = { left: bx, right: bx + bw, cx: bx + bw / 2, top: by, bottom: by + bh, cy: by + bh / 2 };

      // Vertical guides (x-axis alignment)
      for (const [, mv] of Object.entries({ left: movingEdges.left, right: movingEdges.right, cx: movingEdges.cx })) {
        for (const [, bv] of Object.entries({ left: edges.left, right: edges.right, cx: edges.cx })) {
          if (Math.abs(mv - bv) < GUIDE_THRESHOLD) result.push({ type: 'vertical', position: bv });
        }
      }
      // Horizontal guides
      for (const [, mv] of Object.entries({ top: movingEdges.top, bottom: movingEdges.bottom, cy: movingEdges.cy })) {
        for (const [, bv] of Object.entries({ top: edges.top, bottom: edges.bottom, cy: edges.cy })) {
          if (Math.abs(mv - bv) < GUIDE_THRESHOLD) result.push({ type: 'horizontal', position: bv });
        }
      }
    });

    return result;
  }, [blocks, canvasW, canvasH]);

  // ─── Get block position/size from style ───
  const getBlockRect = (block: BlockData) => ({
    x: (block.style?.layout?.x as number) ?? 20,
    y: (block.style?.layout?.y as number) ?? 20,
    w: parseFloat(String(block.style?.layout?.width ?? 200)),
    h: parseFloat(String(block.style?.layout?.minHeight ?? 100)),
    z: (block.style?.layout?.zIndex as number) ?? 0,
    locked: block.style?.layout?.locked ?? false,
  });

  // ─── Update block position ───
  const setBlockPosition = (blockId: string, x: number, y: number, w?: number, h?: number) => {
    const block = blocks.find(b => b.id === blockId);
    if (!block) return;
    const style = block.style || {};
    const layout = { ...(style.layout || {}), position: 'absolute' as const, x: snap(x), y: snap(y) };
    if (w !== undefined) layout.width = String(snap(w)) + 'px';
    if (h !== undefined) layout.minHeight = String(snap(h)) + 'px';
    updateBlock(blockId, { __style: { ...style, layout } } as any);
  };

  // ─── Pointer events for drag ───
  const handleBlockPointerDown = (e: React.PointerEvent, blockId: string) => {
    if (spaceHeld) return; // panning mode
    e.stopPropagation();
    e.preventDefault();
    selectBlock(blockId);
    const block = blocks.find(b => b.id === blockId);
    if (!block) return;
    const rect = getBlockRect(block);
    if (rect.locked) return;
    setDragging({ blockId, startX: e.clientX, startY: e.clientY, origX: rect.x, origY: rect.y });
  };

  const handleResizePointerDown = (e: React.PointerEvent, blockId: string, handle: string) => {
    e.stopPropagation();
    e.preventDefault();
    const block = blocks.find(b => b.id === blockId);
    if (!block) return;
    const rect = getBlockRect(block);
    setResizing({ blockId, handle, startX: e.clientX, startY: e.clientY, origX: rect.x, origY: rect.y, origW: rect.w, origH: rect.h });
  };

  // ─── Global pointer move/up ───
  useEffect(() => {
    const handleMove = (e: PointerEvent) => {
      if (isPanning) {
        setPanOffset(p => ({
          x: p.x + (e.clientX - panStart.x),
          y: p.y + (e.clientY - panStart.y),
        }));
        setPanStart({ x: e.clientX, y: e.clientY });
        return;
      }

      if (dragging) {
        const dx = (e.clientX - dragging.startX) / zoom;
        const dy = (e.clientY - dragging.startY) / zoom;
        const nx = Math.max(0, dragging.origX + dx);
        const ny = Math.max(0, dragging.origY + dy);
        const block = blocks.find(b => b.id === dragging.blockId);
        const rect = block ? getBlockRect(block) : { w: 200, h: 100 };
        setGuides(computeGuides(dragging.blockId, nx, ny, rect.w, rect.h));
        setBlockPosition(dragging.blockId, nx, ny);
      }

      if (resizing) {
        const dx = (e.clientX - resizing.startX) / zoom;
        const dy = (e.clientY - resizing.startY) / zoom;
        const h = resizing.handle;
        let nx = resizing.origX, ny = resizing.origY, nw = resizing.origW, nh = resizing.origH;

        if (h.includes('e')) nw = Math.max(40, resizing.origW + dx);
        if (h.includes('s')) nh = Math.max(30, resizing.origH + dy);
        if (h.includes('w')) { nw = Math.max(40, resizing.origW - dx); nx = resizing.origX + dx; }
        if (h.includes('n')) { nh = Math.max(30, resizing.origH - dy); ny = resizing.origY + dy; }

        // Shift = maintain aspect ratio
        if (e.shiftKey && resizing.origW > 0) {
          const ratio = resizing.origW / resizing.origH;
          if (h.includes('e') || h.includes('w')) nh = nw / ratio;
          else nw = nh * ratio;
        }

        setBlockPosition(resizing.blockId, nx, ny, nw, nh);
      }
    };

    const handleUp = () => {
      if (dragging || resizing) setGuides([]);
      setDragging(null);
      setResizing(null);
      setIsPanning(false);
    };

    window.addEventListener('pointermove', handleMove);
    window.addEventListener('pointerup', handleUp);
    return () => { window.removeEventListener('pointermove', handleMove); window.removeEventListener('pointerup', handleUp); };
  }, [dragging, resizing, isPanning, panStart, zoom, blocks, computeGuides]);

  // ─── Keyboard ───
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === ' ') { setSpaceHeld(true); e.preventDefault(); }
      if (!selectedBlockId) return;
      const step = e.shiftKey ? 10 : 1;
      const block = blocks.find(b => b.id === selectedBlockId);
      if (!block) return;
      const rect = getBlockRect(block);

      if (e.key === 'ArrowUp') { e.preventDefault(); setBlockPosition(selectedBlockId, rect.x, rect.y - step); }
      if (e.key === 'ArrowDown') { e.preventDefault(); setBlockPosition(selectedBlockId, rect.x, rect.y + step); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); setBlockPosition(selectedBlockId, rect.x - step, rect.y); }
      if (e.key === 'ArrowRight') { e.preventDefault(); setBlockPosition(selectedBlockId, rect.x + step, rect.y); }
      if (e.key === 'Delete' || e.key === 'Backspace') { if (document.activeElement === document.body) { e.preventDefault(); removeBlock(selectedBlockId); } }
      if (e.key === ';' && e.ctrlKey) { e.preventDefault(); setShowGrid(g => !g); }
      if (e.key === "'" && e.ctrlKey) { e.preventDefault(); setSnapToGrid(s => !s); }
    };
    const handleKeyUp = (e: KeyboardEvent) => {
      if (e.key === ' ') setSpaceHeld(false);
    };
    window.addEventListener('keydown', handleKeyDown);
    window.addEventListener('keyup', handleKeyUp);
    return () => { window.removeEventListener('keydown', handleKeyDown); window.removeEventListener('keyup', handleKeyUp); };
  }, [selectedBlockId, blocks]);

  // ─── Zoom with Ctrl+scroll ───
  useEffect(() => {
    const handleWheel = (e: WheelEvent) => {
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        setZoom(z => Math.min(3, Math.max(0.2, z - e.deltaY * 0.001)));
      }
    };
    const el = viewportRef.current;
    el?.addEventListener('wheel', handleWheel, { passive: false });
    return () => el?.removeEventListener('wheel', handleWheel);
  }, []);

  // ─── Canvas click for pan start ───
  const handleCanvasPointerDown = (e: React.PointerEvent) => {
    if (spaceHeld) {
      setIsPanning(true);
      setPanStart({ x: e.clientX, y: e.clientY });
      e.preventDefault();
      return;
    }
    // Clicked on empty canvas area
    selectBlock(null);
  };

  // ─── Fit to view ───
  const fitToView = () => {
    if (!viewportRef.current) return;
    const vw = viewportRef.current.clientWidth - 80;
    const vh = viewportRef.current.clientHeight - 80;
    const z = Math.min(vw / canvasW, vh / canvasH);
    setZoom(z);
    setPanOffset({ x: 0, y: 0 });
  };

  // ─── Render a block on canvas ───
  const renderCanvasBlock = (block: BlockData) => {
    const registration = blockRegistry.get(block.type);
    if (!registration) return null;
    const { Preview } = registration;
    const rect = getBlockRect(block);
    const isSelected = selectedBlockId === block.id;

    return (
      <div
        key={block.id}
        className={`absolute group ${isSelected ? 'ring-2 ring-primary' : 'hover:ring-1 hover:ring-primary/30'} ${rect.locked ? 'opacity-80' : ''}`}
        style={{
          left: rect.x, top: rect.y,
          width: rect.w, minHeight: rect.h,
          zIndex: rect.z,
          cursor: spaceHeld ? 'grab' : (dragging?.blockId === block.id ? 'grabbing' : (rect.locked ? 'default' : 'move')),
        }}
        onPointerDown={e => handleBlockPointerDown(e, block.id)}
        onClick={e => e.stopPropagation()}
      >
        {/* Block content preview */}
        <div className="w-full h-full overflow-hidden pointer-events-none">
          <Preview block={block} isSelected={isSelected} onUpdate={() => {}} onSelect={() => {}} />
        </div>

        {/* Resize handles */}
        {isSelected && !rect.locked && (
          <>
            {['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].map(h => {
              const isCorner = h.length === 2;
              const size = isCorner ? 8 : 6;
              const style: React.CSSProperties = {
                position: 'absolute', width: size, height: size,
                background: 'oklch(0.62 0.16 270)', border: '1px solid white',
                borderRadius: isCorner ? 2 : 1, zIndex: 9999,
                cursor: `${h}-resize`,
              };
              if (h.includes('n')) style.top = -size / 2;
              if (h.includes('s')) style.bottom = -size / 2;
              if (h.includes('w')) style.left = -size / 2;
              if (h.includes('e')) style.right = -size / 2;
              if (h === 'n' || h === 's') { style.left = '50%'; style.marginLeft = -size / 2; }
              if (h === 'w' || h === 'e') { style.top = '50%'; style.marginTop = -size / 2; }

              return <div key={h} style={style} onPointerDown={e => handleResizePointerDown(e, block.id, h)} />;
            })}
          </>
        )}

        {/* Dimension label */}
        {isSelected && (
          <div className="absolute -bottom-5 left-1/2 -translate-x-1/2 text-[9px] font-mono text-primary bg-base-100/90 px-1.5 py-0.5 rounded whitespace-nowrap">
            {Math.round(rect.w)} × {Math.round(rect.h)}
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="flex-1 flex flex-col overflow-hidden bg-base-200">
      {/* ─── Toolbar ─── */}
      <div className="flex items-center justify-between h-9 px-3 bg-base-100 border-b border-base-300/30 shrink-0">
        <div className="flex items-center gap-1 text-[11px] text-base-content/40">
          <button onClick={fitToView} className="btn btn-ghost btn-xs gap-1" title="Fit to view"><Maximize size={12} /> Fit</button>
          <button onClick={() => setZoom(z => Math.max(0.2, z - 0.1))} className="btn btn-ghost btn-xs btn-square"><ZoomOut size={12} /></button>
          <span className="w-10 text-center font-mono">{Math.round(zoom * 100)}%</span>
          <button onClick={() => setZoom(z => Math.min(3, z + 0.1))} className="btn btn-ghost btn-xs btn-square"><ZoomIn size={12} /></button>
          <div className="w-px h-4 bg-base-300/30 mx-1" />
          <button onClick={() => setShowGrid(g => !g)}
            className={`btn btn-xs gap-1 ${showGrid ? 'btn-primary btn-outline' : 'btn-ghost'}`}>
            <Grid3x3 size={11} /> Grid
          </button>
          <button onClick={() => setSnapToGrid(s => !s)}
            className={`btn btn-xs ${snapToGrid ? 'btn-primary btn-outline' : 'btn-ghost'}`}>
            Snap
          </button>
        </div>
        <div className="text-[10px] text-base-content/25 font-mono">
          {canvasW} × {canvasH}px · {blocks.length} blocks
        </div>
      </div>

      {/* ─── Viewport ─── */}
      <div ref={viewportRef} className="flex-1 overflow-hidden relative"
        style={{ cursor: spaceHeld ? (isPanning ? 'grabbing' : 'grab') : 'default' }}>
        {/* Scaled canvas container */}
        <div
          style={{
            position: 'absolute',
            left: '50%', top: '50%',
            transform: `translate(calc(-50% + ${panOffset.x}px), calc(-50% + ${panOffset.y}px)) scale(${zoom})`,
            transformOrigin: 'center center',
          }}
        >
          {/* Canvas surface */}
          <div
            ref={node => { canvasRef.current = node; setDropRef(node); }}
            className="relative shadow-2xl"
            style={{
              width: canvasW, height: canvasH,
              background: '#ffffff',
            }}
            onPointerDown={handleCanvasPointerDown}
          >
            {/* Grid dots */}
            {showGrid && (
              <svg className="absolute inset-0 pointer-events-none" width={canvasW} height={canvasH}>
                <defs>
                  <pattern id="grid" width={GRID_SIZE} height={GRID_SIZE} patternUnits="userSpaceOnUse">
                    <circle cx={GRID_SIZE / 2} cy={GRID_SIZE / 2} r="0.5" fill="rgba(0,0,0,0.08)" />
                  </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
              </svg>
            )}

            {/* Page center guides */}
            <div className="absolute inset-0 pointer-events-none">
              <div className="absolute left-1/2 top-0 bottom-0 w-px border-l border-dashed border-primary/10" />
              <div className="absolute top-1/2 left-0 right-0 h-px border-t border-dashed border-primary/10" />
            </div>

            {/* Smart alignment guides */}
            {guides.map((g, i) => (
              <div key={i} className="absolute pointer-events-none" style={
                g.type === 'vertical'
                  ? { left: g.position, top: 0, width: 1, height: canvasH, background: 'oklch(0.65 0.25 25)' }
                  : { top: g.position, left: 0, height: 1, width: canvasW, background: 'oklch(0.65 0.25 25)' }
              } />
            ))}

            {/* Blocks */}
            {blocks.map(block => renderCanvasBlock(block))}
          </div>

          {/* Rulers */}
          <div className="absolute -top-5 left-0 h-4 overflow-hidden" style={{ width: canvasW }}>
            <svg width={canvasW} height={16}>
              {Array.from({ length: Math.ceil(canvasW / 100) }, (_, i) => (
                <g key={i}>
                  <line x1={i * 100} y1={12} x2={i * 100} y2={16} stroke="rgba(128,128,128,0.3)" />
                  <text x={i * 100 + 2} y={10} fontSize="8" fill="rgba(128,128,128,0.4)" fontFamily="monospace">{i * 100}</text>
                </g>
              ))}
            </svg>
          </div>
          <div className="absolute -left-5 top-0 w-4 overflow-hidden" style={{ height: canvasH }}>
            <svg width={16} height={canvasH}>
              {Array.from({ length: Math.ceil(canvasH / 100) }, (_, i) => (
                <g key={i}>
                  <line x1={12} y1={i * 100} x2={16} y2={i * 100} stroke="rgba(128,128,128,0.3)" />
                  <text x={0} y={i * 100 + 10} fontSize="8" fill="rgba(128,128,128,0.4)" fontFamily="monospace" transform={`rotate(-90, 8, ${i * 100 + 5})`}>{i * 100}</text>
                </g>
              ))}
            </svg>
          </div>
        </div>
      </div>
    </div>
  );
}
