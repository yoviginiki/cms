import React, { useRef, useCallback, useEffect, useState } from 'react';
import type { MagElement } from '@/types/magazine';
import { ImageIcon, Film, Lock } from 'lucide-react';

const TEXT_FRAME_TYPES = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'];
const IMAGE_FRAME_TYPES = ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image'];

interface Props {
  element: MagElement;
  isSelected: boolean;
  isHovered: boolean;
  zoom: number;
  isEditing?: boolean;
  onPointerDown: (e: React.PointerEvent, id: string) => void;
  onDoubleClick: (e: React.MouseEvent, id: string) => void;
  onContentChange?: (id: string, html: string) => void;
}

export function MagElementRenderer({ element: el, isSelected, isHovered, isEditing, onPointerDown, onDoubleClick, onContentChange }: Props) {
  const editRef = useRef<HTMLDivElement>(null);
  const textRef = useRef<HTMLDivElement>(null);
  const [isOverflowing, setIsOverflowing] = useState(false);

  // Detect actual text overflow (scrollHeight > clientHeight)
  useEffect(() => {
    if (!TEXT_FRAME_TYPES.includes(el.type)) return;
    const check = () => {
      const node = textRef.current || editRef.current;
      if (node) setIsOverflowing(node.scrollHeight > node.clientHeight + 2);
    };
    check();
    // Re-check after fonts load
    const timer = setTimeout(check, 500);
    return () => clearTimeout(timer);
  }, [el.type, el.width, el.height, (el.data as any)?.content, isEditing]);

  // When entering edit mode, focus the contentEditable
  useEffect(() => {
    if (isEditing && editRef.current) {
      editRef.current.focus();
      // Place cursor at end
      const sel = window.getSelection();
      if (sel) {
        sel.selectAllChildren(editRef.current);
        sel.collapseToEnd();
      }
    }
  }, [isEditing]);

  const handleBlur = useCallback(() => {
    if (editRef.current && onContentChange) {
      onContentChange(el.id, editRef.current.innerHTML);
    }
  }, [el.id, onContentChange]);
  // Build inline styles from element properties
  const containerStyle: React.CSSProperties = {
    position: 'absolute',
    left: el.x,
    top: el.y,
    width: el.width,
    height: el.height,
    transform: el.rotation ? `rotate(${el.rotation}deg)` : undefined,
    opacity: el.visible ? (el.style?.opacity ?? 1) : 0.3,
    zIndex: el.zIndex,
    cursor: el.locked ? 'default' : 'move',
    pointerEvents: el.locked ? 'none' : 'auto',
  };

  // Apply fill
  if (el.style?.fill?.color) containerStyle.backgroundColor = el.style.fill.color;
  if (el.style?.fill?.gradient) {
    const g = el.style.fill.gradient;
    const stops = g.stops.map(s => `${s.color} ${s.offset * 100}%`).join(', ');
    containerStyle.background = g.type === 'linear' ? `linear-gradient(${g.angle}deg, ${stops})` : `radial-gradient(${stops})`;
  }

  // Apply stroke
  if (el.style?.stroke?.width && el.style.stroke.width > 0) {
    containerStyle.border = `${el.style.stroke.width}px ${Array.isArray(el.style.stroke.style) ? 'dashed' : el.style.stroke.style} ${el.style.stroke.color}`;
  }

  // Apply corner radius
  if (el.style?.cornerRadius) {
    const cr = el.style.cornerRadius;
    containerStyle.borderRadius = `${cr.tl}px ${cr.tr}px ${cr.br}px ${cr.bl}px`;
  }

  // Apply shadow
  if (el.style?.shadow) {
    const s = el.style.shadow;
    containerStyle.boxShadow = `${s.x}px ${s.y}px ${s.blur}px ${s.spread}px ${s.color}`;
  }

  // Apply blend mode
  if (el.style?.blendMode && el.style.blendMode !== 'normal') {
    containerStyle.mixBlendMode = el.style.blendMode as any;
  }

  // Type-specific content rendering
  const renderContent = () => {
    const data = el.data as Record<string, any>;

    if (TEXT_FRAME_TYPES.includes(el.type)) {
      const typo = el.typography;
      const textStyle: React.CSSProperties = {
        fontFamily: typo?.fontFamily || 'Inter',
        fontSize: typo?.fontSize || 14,
        fontWeight: typo?.fontWeight || 400,
        fontStyle: typo?.fontStyle || 'normal',
        lineHeight: typo?.lineHeight || 1.5,
        letterSpacing: typo?.letterSpacing ? `${typo.letterSpacing}em` : undefined,
        textAlign: (typo?.textAlign || 'left') as any,
        color: typo?.textColor || '#1a1a1a',
        textTransform: (typo as any)?.textTransform || undefined,
        padding: data.textInset ? `${data.textInset.top}px ${data.textInset.right}px ${data.textInset.bottom}px ${data.textInset.left}px` : '8px',
        columnCount: data.columnsInFrame || 1,
        columnGap: data.columnGap || 12,
        columnFill: data.columnFill === 'balance' ? 'balance' : 'auto',
        overflow: isEditing ? 'auto' : 'hidden',
        width: '100%',
        height: '100%',
        outline: isEditing ? '2px solid #3b82f6' : undefined,
        cursor: isEditing ? 'text' : undefined,
      };

      if (isEditing) {
        return (
          <div
            ref={editRef}
            data-editing-id={el.id}
            style={textStyle}
            contentEditable
            suppressContentEditableWarning
            onBlur={handleBlur}
            onKeyDown={(e) => e.stopPropagation()}
            onPointerDown={(e) => e.stopPropagation()}
            dangerouslySetInnerHTML={{ __html: data.content || '<p>Text frame</p>' }}
          />
        );
      }

      return <div ref={textRef} style={textStyle} dangerouslySetInnerHTML={{ __html: data.content || '<p>Text frame</p>' }} />;
    }

    if (IMAGE_FRAME_TYPES.includes(el.type)) {
      if (!data.assetId && !data.src) {
        return (
          <div className="w-full h-full bg-base-300/10 flex flex-col items-center justify-center border border-dashed border-base-300/30" style={el.type === 'circular_image' ? { borderRadius: '50%' } : undefined}>
            <ImageIcon size={20} className="text-base-content/15 mb-1" />
            <span className="text-[9px] text-base-content/20">Image frame</span>
            <span className="text-[8px] text-base-content/15 mt-0.5">Set URL in Properties</span>
          </div>
        );
      }
      const imgStyle: React.CSSProperties = {
        width: '100%', height: '100%',
        objectFit: (data.fit || 'cover') as any,
        objectPosition: data.focalPoint ? `${data.focalPoint.x * 100}% ${data.focalPoint.y * 100}%` : 'center',
      };
      if (el.type === 'circular_image') containerStyle.borderRadius = '50%';
      return <img src={data.src || `/api/v1/assets/${data.assetId}/serve`} alt={data.alt || ''} style={imgStyle} />;
    }

    if (el.type === 'rectangle') {
      const bg = data.fillColor || el.style?.fill?.color || '#e5e7eb';
      return <div className="w-full h-full" style={{ backgroundColor: bg, borderRadius: data.cornerRadius ? `${data.cornerRadius.tl || 0}px ${data.cornerRadius.tr || 0}px ${data.cornerRadius.br || 0}px ${data.cornerRadius.bl || 0}px` : undefined }} />;
    }

    if (el.type === 'ellipse') {
      containerStyle.borderRadius = '50%';
      const bg = data.fillColor || el.style?.fill?.color || '#e5e7eb';
      return <div className="w-full h-full" style={{ backgroundColor: bg, borderRadius: '50%' }} />;
    }

    if (el.type === 'line') {
      return (
        <svg width="100%" height="100%" style={{ overflow: 'visible' }}>
          <line x1={0} y1={el.height / 2} x2={el.width} y2={el.height / 2}
            stroke={data.strokeColor || '#1a1a1a'} strokeWidth={data.strokeWidth || 2}
            strokeDasharray={data.strokeDash === 'dashed' ? '8 4' : data.strokeDash === 'dotted' ? '2 2' : undefined} />
        </svg>
      );
    }

    if (el.type === 'gradient_overlay') {
      const grad = el.style?.fill?.gradient || data.fillGradient;
      if (grad) {
        const stops = (grad.stops || []).map((s: any) => `${s.color} ${s.offset * 100}%`).join(', ');
        return <div className="w-full h-full" style={{ background: grad.type === 'linear' ? `linear-gradient(${grad.angle || 180}deg, ${stops})` : `radial-gradient(${stops})` }} />;
      }
      return <div className="w-full h-full bg-gradient-to-b from-black/50 to-transparent" />;
    }

    if (el.type === 'decorative_rule') {
      return (
        <div className="w-full h-full flex items-center">
          <hr className="w-full border-t-2 border-current opacity-30" />
        </div>
      );
    }

    if (el.type === 'video_frame') {
      return (
        <div className="w-full h-full bg-neutral/5 flex flex-col items-center justify-center border border-dashed border-base-300/30">
          <Film size={20} className="text-base-content/15 mb-1" />
          <span className="text-[9px] text-base-content/20">Video</span>
          {data.url && <span className="text-[8px] text-base-content/15 mt-0.5 truncate max-w-full px-2">{data.url}</span>}
        </div>
      );
    }

    if (el.type === 'audio_player') {
      return (
        <div className="w-full h-full bg-base-200/50 flex items-center gap-2 px-3 border border-base-300/20 rounded">
          <div className="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center"><span className="text-primary text-xs">▶</span></div>
          <div className="flex-1 min-w-0">
            <div className="text-[11px] font-medium text-base-content/60 truncate">{data.title || 'Audio'}</div>
            {data.artist && <div className="text-[9px] text-base-content/30">{data.artist}</div>}
          </div>
        </div>
      );
    }

    if (el.type === 'button') {
      const variant = data.variant || 'solid';
      const btnStyle = variant === 'solid' ? 'bg-base-content text-base-100' : variant === 'outline' ? 'border border-base-content/30 text-base-content/70' : 'text-base-content/70';
      return (
        <div className={`w-full h-full flex items-center justify-center rounded ${btnStyle}`}>
          <span className="text-[12px] font-medium">{data.text || 'Button'}</span>
        </div>
      );
    }

    if (el.type === 'hotspot') {
      return (
        <div className="w-full h-full border-2 border-dashed border-primary/30 bg-primary/5 flex items-center justify-center rounded">
          <span className="text-[9px] text-primary/40">Hotspot</span>
        </div>
      );
    }

    if (el.type === 'tooltip_trigger') {
      return (
        <div className="w-full h-full border border-dashed border-info/30 bg-info/5 flex items-center justify-center rounded">
          <span className="text-[9px] text-info/50">Tooltip</span>
        </div>
      );
    }

    if (el.type === 'table_frame') {
      const headers = data.headers || ['Col 1', 'Col 2'];
      return (
        <div className="w-full h-full overflow-hidden text-[8px]">
          <table className="w-full border-collapse">
            <thead><tr>{(headers as string[]).map((h: string, i: number) => <th key={i} className="border border-base-300/20 bg-base-200/50 px-1 py-0.5 text-left text-base-content/50">{h}</th>)}</tr></thead>
            <tbody>{((data.rows || [['...', '...']]) as string[][]).slice(0, 3).map((row: string[], ri: number) => <tr key={ri}>{row.map((c: string, ci: number) => <td key={ci} className="border border-base-300/10 px-1 py-0.5 text-base-content/30">{c}</td>)}</tr>)}</tbody>
          </table>
        </div>
      );
    }

    if (el.type === 'chart_frame') {
      const items = (data.data || []) as Array<{ label: string; value: number }>;
      const max = Math.max(...items.map(i => i.value), 1);
      return (
        <div className="w-full h-full flex items-end gap-1 p-2">
          {items.map((item, i) => (
            <div key={i} className="flex-1 flex flex-col items-center gap-0.5">
              <div className="w-full bg-primary/30 rounded-t" style={{ height: `${(item.value / max) * 80}%` }} />
              <span className="text-[7px] text-base-content/30 truncate w-full text-center">{item.label}</span>
            </div>
          ))}
        </div>
      );
    }

    if (el.type === 'infographic_number') {
      return (
        <div className="w-full h-full flex flex-col items-center justify-center">
          <span className="text-[28px] font-bold text-base-content/80">{data.prefix || ''}{data.value || '0'}{data.suffix || ''}</span>
          <span className="text-[10px] text-base-content/40 mt-1">{data.label || 'Label'}</span>
        </div>
      );
    }

    if (el.type === 'progress_indicator') {
      return (
        <div className="w-full h-full flex items-center px-2">
          <div className="w-full h-2 bg-base-300/20 rounded-full overflow-hidden">
            <div className="h-full bg-primary/50 rounded-full" style={{ width: '60%' }} />
          </div>
        </div>
      );
    }

    if (el.type === 'page_number') {
      return <div className="w-full h-full flex items-center justify-center"><span style={{ fontFamily: el.typography?.fontFamily || 'Inter', fontSize: el.typography?.fontSize || 10, color: el.typography?.textColor || '#888' }}>{data.prefix || ''}{data.startAt || 1}{data.suffix || ''}</span></div>;
    }

    if (el.type === 'running_header') {
      return <div className="w-full h-full flex items-center"><span className="text-[10px] text-base-content/30 tracking-wider uppercase">{data.customText || 'Running header'}</span></div>;
    }

    if (el.type === 'svg_icon') {
      return (
        <div className="w-full h-full flex items-center justify-center" style={{ color: data.color || '#1a1a1a' }}>
          <svg viewBox="0 0 24 24" fill="currentColor" width="60%" height="60%"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        </div>
      );
    }

    if (el.type === 'embed_frame') {
      return (
        <div className="w-full h-full bg-base-200/30 flex flex-col items-center justify-center border border-dashed border-base-300/30">
          <span className="text-[10px] text-base-content/20">Embed</span>
          <span className="text-[8px] text-base-content/15 mt-0.5">HTML / iframe</span>
        </div>
      );
    }

    if (el.type === 'group' || el.type === 'clipping_group' || el.type === 'component_instance') {
      return (
        <div className="w-full h-full border border-dashed border-base-content/10 flex items-center justify-center">
          <span className="text-[9px] text-base-content/15">{el.type.replace(/_/g, ' ')}</span>
        </div>
      );
    }

    // Fallback for any unknown type
    return (
      <div className="w-full h-full bg-base-200/20 flex flex-col items-center justify-center border border-dashed border-base-300/20">
        <span className="text-[10px] text-base-content/25">{el.type.replace(/_/g, ' ')}</span>
      </div>
    );
  };

  return (
    <div
      style={containerStyle}
      className={`magazine-element ${isSelected ? 'ring-2 ring-blue-500' : ''} ${isHovered && !isSelected ? 'ring-1 ring-blue-300/50' : ''}`}
      onPointerDown={e => onPointerDown(e, el.id)}
      onDoubleClick={e => onDoubleClick(e, el.id)}
    >
      {renderContent()}

      {/* Lock indicator */}
      {el.locked && <div className="absolute top-1 right-1 bg-warning/80 rounded p-0.5"><Lock size={8} className="text-warning-content" /></div>}

      {/* Overflow indicator — shows when text actually overflows the frame */}
      {TEXT_FRAME_TYPES.includes(el.type) && isOverflowing && !isEditing && (
        <div className="absolute bottom-0 right-0 w-5 h-5 bg-error text-error-content flex items-center justify-center text-[9px] font-bold rounded-tl cursor-help"
          title="Text overflows this frame. Increase frame height, reduce font size, or link to another text frame.">+</div>
      )}

      {/* Thread indicators */}
      {el.threadId && (
        <div className="absolute -bottom-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border border-white" title={`Thread ${el.threadOrder}`} />
      )}

      {/* Resize handles when selected */}
      {isSelected && !el.locked && (
        <>
          {['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].map(h => {
            const isCorner = h.length === 2;
            const sz = isCorner ? 8 : 6;
            const s: React.CSSProperties = {
              position: 'absolute', width: sz, height: sz,
              background: '#3b82f6', border: '1px solid white',
              borderRadius: isCorner ? 2 : 1, zIndex: 9999,
              cursor: `${h}-resize`, pointerEvents: 'auto',
            };
            if (h.includes('n')) s.top = -sz / 2;
            if (h.includes('s')) s.bottom = -sz / 2;
            if (h.includes('w')) s.left = -sz / 2;
            if (h.includes('e')) s.right = -sz / 2;
            if (h === 'n' || h === 's') { s.left = '50%'; s.marginLeft = -sz / 2; }
            if (h === 'w' || h === 'e') { s.top = '50%'; s.marginTop = -sz / 2; }
            return <div key={h} style={s} data-handle={h} />;
          })}
          {/* Rotation handle */}
          <div style={{ position: 'absolute', top: -24, left: '50%', marginLeft: -5, width: 10, height: 10, borderRadius: '50%', background: '#3b82f6', border: '2px solid white', cursor: 'crosshair', zIndex: 9999 }} data-handle="rotate" />
        </>
      )}
    </div>
  );
}
