/**
 * M2 DTP Canvas Prototype — Frame Renderer
 *
 * Renders a frame with drag-to-move and resize handles.
 * All pointer math accounts for zoom level.
 */
import { useRef, useCallback } from 'react';
import { ImageIcon, Quote } from 'lucide-react';
import type { DtpFrame } from './mockDocument';

interface Props {
  frame: DtpFrame;
  isSelected: boolean;
  zoom: number;
  onSelect: () => void;
  onUpdate: (updates: Partial<DtpFrame>) => void;
}

const TYPE_COLORS: Record<DtpFrame['type'], { border: string; bg: string; label: string }> = {
  text:       { border: '#3b82f6', bg: 'rgba(59,130,246,0.03)',  label: 'Text' },
  image:      { border: '#10b981', bg: 'rgba(16,185,129,0.05)',  label: 'Image' },
  quote:      { border: '#8b5cf6', bg: 'rgba(139,92,246,0.03)',  label: 'Quote' },
  pageNumber: { border: '#f59e0b', bg: 'rgba(245,158,11,0.03)',  label: 'Page #' },
};

const MIN_SIZE = 20;

// Handle positions: [name, cssLeft, cssTop, cursor]
const HANDLES: [string, string | number, string | number, string][] = [
  ['nw', -4, -4, 'nw-resize'],
  ['n',  '50%', -4, 'n-resize'],
  ['ne', 'calc(100% - 4px)', -4, 'ne-resize'],
  ['e',  'calc(100% - 4px)', '50%', 'e-resize'],
  ['se', 'calc(100% - 4px)', 'calc(100% - 4px)', 'se-resize'],
  ['s',  '50%', 'calc(100% - 4px)', 's-resize'],
  ['sw', -4, 'calc(100% - 4px)', 'sw-resize'],
  ['w',  -4, '50%', 'w-resize'],
];

export function FrameRenderer({ frame, isSelected, zoom, onSelect, onUpdate }: Props) {
  const colors = TYPE_COLORS[frame.type];
  const dragRef = useRef<{ startX: number; startY: number; origX: number; origY: number } | null>(null);
  const resizeRef = useRef<{ handle: string; startX: number; startY: number; orig: { x: number; y: number; w: number; h: number } } | null>(null);

  // ─── Drag to move ───
  const handlePointerDown = useCallback((e: React.PointerEvent) => {
    if (resizeRef.current) return; // Don't start drag if resizing
    e.stopPropagation();
    e.preventDefault();
    onSelect();
    dragRef.current = { startX: e.clientX, startY: e.clientY, origX: frame.x, origY: frame.y };
    (e.target as HTMLElement).setPointerCapture(e.pointerId);

    const handleMove = (ev: PointerEvent) => {
      if (!dragRef.current) return;
      const dx = (ev.clientX - dragRef.current.startX) / zoom;
      const dy = (ev.clientY - dragRef.current.startY) / zoom;
      onUpdate({ x: Math.round(dragRef.current.origX + dx), y: Math.round(dragRef.current.origY + dy) });
    };
    const handleUp = () => {
      dragRef.current = null;
      window.removeEventListener('pointermove', handleMove);
      window.removeEventListener('pointerup', handleUp);
    };
    window.addEventListener('pointermove', handleMove);
    window.addEventListener('pointerup', handleUp);
  }, [frame.x, frame.y, zoom, onSelect, onUpdate]);

  // ─── Resize handles ───
  const handleResizeDown = useCallback((handle: string, e: React.PointerEvent) => {
    e.stopPropagation();
    e.preventDefault();
    resizeRef.current = {
      handle,
      startX: e.clientX,
      startY: e.clientY,
      orig: { x: frame.x, y: frame.y, w: frame.width, h: frame.height },
    };

    const handleMove = (ev: PointerEvent) => {
      if (!resizeRef.current) return;
      const { handle: hName, startX, startY, orig } = resizeRef.current;
      const dx = (ev.clientX - startX) / zoom;
      const dy = (ev.clientY - startY) / zoom;

      let nx = orig.x, ny = orig.y, nw = orig.w, nh = orig.h;

      // Horizontal
      if (hName.includes('w')) { nx = orig.x + dx; nw = orig.w - dx; }
      if (hName.includes('e')) { nw = orig.w + dx; }
      // Vertical
      if (hName.includes('n')) { ny = orig.y + dy; nh = orig.h - dy; }
      if (hName.includes('s')) { nh = orig.h + dy; }

      // Enforce minimum size
      if (nw < MIN_SIZE) { if (hName.includes('w')) nx = orig.x + orig.w - MIN_SIZE; nw = MIN_SIZE; }
      if (nh < MIN_SIZE) { if (hName.includes('n')) ny = orig.y + orig.h - MIN_SIZE; nh = MIN_SIZE; }

      onUpdate({ x: Math.round(nx), y: Math.round(ny), width: Math.round(nw), height: Math.round(nh) });
    };
    const handleUp = () => {
      resizeRef.current = null;
      window.removeEventListener('pointermove', handleMove);
      window.removeEventListener('pointerup', handleUp);
    };
    window.addEventListener('pointermove', handleMove);
    window.addEventListener('pointerup', handleUp);
  }, [frame.x, frame.y, frame.width, frame.height, zoom, onUpdate]);

  return (
    <div
      className="absolute group"
      style={{
        left: frame.x, top: frame.y, width: frame.width, height: frame.height,
        transform: frame.rotation ? `rotate(${frame.rotation}deg)` : undefined,
        zIndex: frame.zIndex + 10,
        cursor: isSelected ? 'move' : 'pointer',
        userSelect: 'none',
      }}
      onPointerDown={handlePointerDown}
      onClick={(e) => { e.stopPropagation(); onSelect(); }}
    >
      {/* Frame background */}
      <div className="absolute inset-0 transition-colors" style={{
        backgroundColor: frame.type === 'image' ? '#e5e7eb' : colors.bg,
        border: isSelected ? `2px solid ${colors.border}` : '1px solid transparent',
        borderColor: isSelected ? colors.border : undefined,
      }} />

      {/* Hover border */}
      {!isSelected && (
        <div className="absolute inset-0 border border-transparent group-hover:border-blue-400/40 transition-colors pointer-events-none" />
      )}

      {/* Resize handles (selected only) */}
      {isSelected && HANDLES.map(([name, left, top, cursor]) => (
        <div key={name}
          className="absolute"
          style={{
            left, top, width: 8, height: 8,
            backgroundColor: colors.border, border: '1px solid white',
            cursor, zIndex: 200, transform: 'translate(-50%, -50%)',
          }}
          onPointerDown={(e) => handleResizeDown(name, e)}
        />
      ))}

      {/* Type badge */}
      <div className="absolute -top-4 left-0 flex items-center gap-0.5 pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity"
        style={{ opacity: isSelected ? 1 : undefined }}>
        <span className="text-[8px] font-medium px-1 py-0.5 rounded"
          style={{ backgroundColor: colors.border, color: 'white' }}>
          {frame.label || colors.label}
        </span>
      </div>

      {/* Content */}
      <div className="absolute inset-0 pointer-events-none overflow-hidden">
        {frame.type === 'text' && (
          <div className="p-2">
            <p className="text-[10px] leading-tight text-neutral-700 select-none"
              style={{ fontSize: frame.height > 60 ? '11px' : '9px' }}>
              {frame.content}
            </p>
          </div>
        )}

        {frame.type === 'image' && (
          <div className="flex flex-col items-center justify-center h-full bg-neutral-200">
            <ImageIcon size={24} className="text-neutral-400 mb-1" />
            <span className="text-[9px] text-neutral-400">{frame.label || 'Image'}</span>
            <span className="text-[8px] text-neutral-300 mt-0.5">{frame.width}x{frame.height}</span>
          </div>
        )}

        {frame.type === 'quote' && (
          <div className="p-3 flex items-center h-full" style={{ borderLeft: `3px solid ${colors.border}` }}>
            <div className="flex gap-2 items-start">
              <Quote size={14} className="text-purple-400 shrink-0 mt-0.5" />
              <p className="text-[10px] leading-relaxed text-neutral-600 italic select-none">
                {frame.content}
              </p>
            </div>
          </div>
        )}

        {frame.type === 'pageNumber' && (
          <div className="flex items-center justify-center h-full">
            <span className="text-[11px] font-mono text-neutral-400 select-none">{frame.content}</span>
          </div>
        )}
      </div>
    </div>
  );
}
