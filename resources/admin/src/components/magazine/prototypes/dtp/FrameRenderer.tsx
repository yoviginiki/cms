/**
 * M1 DTP Canvas Prototype — Frame Renderer
 *
 * Renders a single frame as a positioned rectangle with type-specific content.
 */
import { ImageIcon, Quote } from 'lucide-react';
import type { DtpFrame } from './mockDocument';

interface Props {
  frame: DtpFrame;
  isSelected: boolean;
  onSelect: () => void;
}

const TYPE_COLORS: Record<DtpFrame['type'], { border: string; bg: string; label: string }> = {
  text:       { border: '#3b82f6', bg: 'rgba(59,130,246,0.03)',  label: 'Text' },
  image:      { border: '#10b981', bg: 'rgba(16,185,129,0.05)',  label: 'Image' },
  quote:      { border: '#8b5cf6', bg: 'rgba(139,92,246,0.03)',  label: 'Quote' },
  pageNumber: { border: '#f59e0b', bg: 'rgba(245,158,11,0.03)',  label: 'Page #' },
};

export function FrameRenderer({ frame, isSelected, onSelect }: Props) {
  const colors = TYPE_COLORS[frame.type];

  return (
    <div
      className="absolute cursor-pointer group"
      style={{
        left: frame.x,
        top: frame.y,
        width: frame.width,
        height: frame.height,
        transform: frame.rotation ? `rotate(${frame.rotation}deg)` : undefined,
        zIndex: frame.zIndex + 10,
      }}
      onClick={(e) => { e.stopPropagation(); onSelect(); }}
    >
      {/* Frame background */}
      <div className="absolute inset-0 transition-colors" style={{
        backgroundColor: frame.type === 'image' ? '#e5e7eb' : colors.bg,
        border: isSelected ? `2px solid ${colors.border}` : `1px solid transparent`,
        borderColor: isSelected ? colors.border : undefined,
      }} />

      {/* Hover border */}
      {!isSelected && (
        <div className="absolute inset-0 border border-transparent group-hover:border-blue-400/40 transition-colors pointer-events-none" />
      )}

      {/* Selection handles */}
      {isSelected && (
        <>
          {/* 8 resize handles */}
          {[
            { left: -3, top: -3 },
            { left: '50%', top: -3, transform: 'translateX(-50%)' },
            { right: -3, top: -3 },
            { right: -3, top: '50%', transform: 'translateY(-50%)' },
            { right: -3, bottom: -3 },
            { left: '50%', bottom: -3, transform: 'translateX(-50%)' },
            { left: -3, bottom: -3 },
            { left: -3, top: '50%', transform: 'translateY(-50%)' },
          ].map((pos, i) => (
            <div key={i} className="absolute" style={{
              ...pos,
              width: 6,
              height: 6,
              backgroundColor: colors.border,
              border: '1px solid white',
              zIndex: 100,
            }} />
          ))}
        </>
      )}

      {/* Frame type badge */}
      <div className="absolute -top-4 left-0 flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition-opacity"
        style={{ opacity: isSelected ? 1 : undefined }}>
        <span className="text-[8px] font-medium px-1 py-0.5 rounded"
          style={{ backgroundColor: colors.border, color: 'white' }}>
          {frame.label || colors.label}
        </span>
      </div>

      {/* Content rendering per type */}
      {frame.type === 'text' && (
        <div className="absolute inset-0 p-2 overflow-hidden">
          <p className="text-[10px] leading-tight text-neutral-700 select-none"
            style={{ fontSize: frame.height > 60 ? '11px' : '9px' }}>
            {frame.content}
          </p>
        </div>
      )}

      {frame.type === 'image' && (
        <div className="absolute inset-0 flex flex-col items-center justify-center bg-neutral-200">
          <ImageIcon size={24} className="text-neutral-400 mb-1" />
          <span className="text-[9px] text-neutral-400">{frame.label || 'Image'}</span>
          <span className="text-[8px] text-neutral-300 mt-0.5">{frame.width}x{frame.height}</span>
        </div>
      )}

      {frame.type === 'quote' && (
        <div className="absolute inset-0 p-3 flex items-center" style={{ borderLeft: `3px solid ${colors.border}` }}>
          <div className="flex gap-2 items-start">
            <Quote size={14} className="text-purple-400 shrink-0 mt-0.5" />
            <p className="text-[10px] leading-relaxed text-neutral-600 italic select-none">
              {frame.content}
            </p>
          </div>
        </div>
      )}

      {frame.type === 'pageNumber' && (
        <div className="absolute inset-0 flex items-center justify-center">
          <span className="text-[11px] font-mono text-neutral-400 select-none">{frame.content}</span>
        </div>
      )}
    </div>
  );
}
