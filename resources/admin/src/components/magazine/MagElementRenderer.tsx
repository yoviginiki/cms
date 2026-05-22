import React, { useRef, useCallback, useEffect, useState } from 'react';
import DOMPurify from 'dompurify';
import type { MagElement } from '@/types/magazine';
import { ImageIcon, Film, Lock } from 'lucide-react';

const SAFE_HTML_CONFIG = { ALLOWED_TAGS: ['p', 'br', 'b', 'i', 'u', 'em', 'strong', 'span', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'sub', 'sup', 'hr', 'div'], ALLOWED_ATTR: ['href', 'target', 'rel', 'class', 'style'], ALLOW_DATA_ATTR: false };

const TEXT_FRAME_TYPES = ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame'];
const IMAGE_FRAME_TYPES = ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image'];

interface Props {
  element: MagElement;
  isSelected: boolean;
  isHovered: boolean;
  zoom: number;
  isEditing?: boolean;
  threadedContent?: string;  // Pre-computed content from threading engine
  onPointerDown: (e: React.PointerEvent, id: string) => void;
  onDoubleClick: (e: React.MouseEvent, id: string) => void;
  onContentChange?: (id: string, html: string) => void;
  onContinueText?: (id: string) => void;
  onStartEditing?: (id: string) => void;
  onStopEditing?: () => void;
  onToggleFixed?: (id: string, mode: 'free' | 'fixed') => void;
  onToggleSpan?: (id: string, mode: 'page' | 'spread') => void;
  allPages?: Array<{ pageNumber: number; elements: MagElement[] }>;
}

export function MagElementRenderer({ element: el, isSelected, isHovered, isEditing, threadedContent, onPointerDown, onDoubleClick, onContentChange, onContinueText, onStartEditing: _onStartEditing, onStopEditing: _onStopEditing, onToggleFixed: _onToggleFixed, onToggleSpan: _onToggleSpan, allPages }: Props) {
  const editRef = useRef<HTMLDivElement>(null);
  const textRef = useRef<HTMLDivElement>(null);
  const [isOverflowing, setIsOverflowing] = useState(false);

  // Detect actual text overflow (scrollHeight > clientHeight)
  useEffect(() => {
    if (!TEXT_FRAME_TYPES.includes(el.type)) return;
    const check = () => {
      // Use the non-editing text ref for overflow check (stable, not affected by scroll)
      // Fall back to frame dimensions vs content
      const node = textRef.current;
      if (node) {
        setIsOverflowing(node.scrollHeight > node.clientHeight + 2);
      } else {
        // During editing, compare content scroll height vs frame height
        const editNode = editRef.current;
        if (editNode) {
          setIsOverflowing(editNode.scrollHeight > el.height - 4);
        }
      }
    };
    check();
    const timer = setTimeout(check, 500);
    return () => clearTimeout(timer);
  }, [el.type, el.width, el.height, (el.data as any)?.content, isEditing]);

  // When entering edit mode, set initial content ONCE and focus
  const editInitializedRef = useRef(false);
  useEffect(() => {
    if (isEditing && editRef.current && !editInitializedRef.current) {
      editInitializedRef.current = true;
      // Set content from store ONCE — after this, contentEditable manages its own DOM
      const data = el.data as Record<string, any>;
      const rawContent = threadedContent !== undefined ? threadedContent : (data?.content || '');
      const safeContent = DOMPurify.sanitize(rawContent, SAFE_HTML_CONFIG);
      editRef.current.innerHTML = safeContent;
      lastSavedRef.current = safeContent;
      editRef.current.focus();
      // Place cursor at end
      const sel = window.getSelection();
      if (sel) {
        sel.selectAllChildren(editRef.current);
        sel.collapseToEnd();
      }
    }
  }, [isEditing]);

  // Reset init flag when exiting edit mode
  useEffect(() => {
    if (!isEditing) editInitializedRef.current = false;
  }, [isEditing]);

  const lastSavedRef = useRef<string>('');
  const handleBlur = useCallback((_e: React.FocusEvent) => {
    // Delay blur handling — scrollbar clicks fire blur but focus returns
    setTimeout(() => {
      // If focus returned to our contentEditable (scrollbar click), skip
      if (document.activeElement === editRef.current) return;

      if (editRef.current && onContentChange) {
        const current = editRef.current.innerHTML;
        if (current !== lastSavedRef.current) {
          lastSavedRef.current = current;
          onContentChange(el.id, current);
        }
      }
    }, 100);
  }, [el.id, onContentChange]);
  // Build inline styles from element properties
  const isTextType = TEXT_FRAME_TYPES.includes(el.type);
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
    // hidden: clips text + enables overflow detection + shows Continue button
    // auto when editing: allows vertical scroll inside frame
    overflow: isTextType ? (isEditing ? 'auto' : 'hidden') : undefined,
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
      const cols = data.columnsInFrame || 1;
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
        padding: data.textInset && typeof data.textInset === 'object' ? `${data.textInset.top ?? 8}px ${data.textInset.right ?? 8}px ${data.textInset.bottom ?? 8}px ${data.textInset.left ?? 8}px` : '8px',
        wordBreak: 'break-word' as any,
        width: '100%',
        outline: isEditing ? '2px solid #3b82f6' : undefined,
        cursor: isEditing ? 'text' : undefined,
      };

      // Columns
      if (cols > 1) {
        textStyle.columnCount = cols;
        textStyle.columnGap = data.columnGap || 12;
        textStyle.columnFill = data.columnFill === 'balance' ? 'balance' : 'auto';
      }

      // Height and scroll — text div fills frame and scrolls vertically
      textStyle.height = '100%';
      textStyle.overflowX = 'hidden';
      textStyle.overflowY = isEditing ? 'auto' : 'hidden';

      // Use threaded content if available (from threading engine), otherwise use frame's own content
      const rawContent = threadedContent !== undefined ? threadedContent : (data.content || '<p>Text frame</p>');
      const safeContent = DOMPurify.sanitize(rawContent, SAFE_HTML_CONFIG);

      if (isEditing) {
        return (
          <div
            ref={editRef}
            data-editing-id={el.id}
            style={textStyle}
            contentEditable
            suppressContentEditableWarning
            onBlur={handleBlur}
            onKeyDown={(e) => {
              // Let Escape propagate to exit editing, stop everything else
              if (e.key !== 'Escape') e.stopPropagation();
            }}
            onKeyUp={() => {
              try {
                const sel = window.getSelection();
                if (sel && sel.rangeCount > 0) {
                  (window as any).__dtpSavedSelection = sel.getRangeAt(0).cloneRange();
                }
              } catch (_) {}
            }}
            onMouseUp={() => {
              try {
                const sel = window.getSelection();
                if (sel && sel.rangeCount > 0) {
                  (window as any).__dtpSavedSelection = sel.getRangeAt(0).cloneRange();
                }
              } catch (_) {}
            }}
            onPointerDown={(e) => e.stopPropagation()}
            onWheel={(e) => e.stopPropagation()}
          />
          /* Content set via useEffect editInitializedRef — NOT dangerouslySetInnerHTML
             This prevents React re-renders from overwriting user's typed content */
        );
      }

      return <div ref={textRef} style={textStyle} dangerouslySetInnerHTML={{ __html: safeContent }} />;
    }

    if (IMAGE_FRAME_TYPES.includes(el.type)) {
      // Gallery renders differently — grid of images
      if (el.type === 'gallery_frame') {
        const images = (data.images || []) as Array<{ assetId?: string; alt?: string; caption?: string }>;
        if (images.length === 0) {
          return (
            <div className="w-full h-full bg-base-300/10 flex flex-col items-center justify-center border border-dashed border-base-300/30">
              <ImageIcon size={20} className="text-base-content/15 mb-1" />
              <span className="text-[9px] text-base-content/20">Gallery</span>
              <span className="text-[8px] text-base-content/15 mt-0.5">Add images in Properties</span>
            </div>
          );
        }
        return (
          <div className="w-full h-full grid gap-1 overflow-hidden" style={{ gridTemplateColumns: `repeat(${data.columns || 2}, 1fr)` }}>
            {images.slice(0, 6).map((img: any, i: number) => (
              <div key={i} className="bg-base-300/10 overflow-hidden">
                {img.assetId || img.src ? <img src={img.src || `/api/v1/assets/${img.assetId}/serve`} alt={img.alt || ''} className="w-full h-full object-cover" /> : <div className="w-full h-full flex items-center justify-center"><ImageIcon size={12} className="text-base-content/10" /></div>}
              </div>
            ))}
          </div>
        );
      }

      // Clip shape for circular/polygon
      const isCircular = el.type === 'circular_image';
      const isPolygon = el.type === 'polygon_image';
      const clipStyle: React.CSSProperties = {
        borderRadius: isCircular ? '50%' : undefined,
        clipPath: isPolygon ? 'polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%)' : undefined,
        overflow: 'hidden',
      };

      // Apply clip to container
      if (isCircular) { containerStyle.borderRadius = '50%'; containerStyle.overflow = 'hidden'; }
      if (isPolygon) { containerStyle.clipPath = 'polygon(50% 0%, 100% 38%, 82% 100%, 18% 100%, 0% 38%)'; containerStyle.overflow = 'hidden'; }
      if (el.type === 'background_image') { containerStyle.zIndex = -1; }

      // P15: Apply image-specific styling
      const imgBorderRadius = data.borderRadius ? `${data.borderRadius}px` : undefined;
      // Whitelist shadow CSS to prevent injection from tampered documents
      const SAFE_SHADOWS = new Set([
        '0 1px 3px rgba(0,0,0,0.12)',
        '0 4px 12px rgba(0,0,0,0.15)',
        '0 8px 24px rgba(0,0,0,0.2)',
        '0 12px 40px rgba(0,0,0,0.25)',
      ]);
      const rawShadow = data.shadowCss as string | null;
      const imgShadowCss = rawShadow && SAFE_SHADOWS.has(rawShadow) ? rawShadow : null;
      const imgBgColor = data.backgroundColor as string | undefined;
      const imgOpacity = Math.min(100, Math.max(0, Number(data.opacity) || 100)) / 100;
      if (imgBorderRadius && !isCircular) { containerStyle.borderRadius = imgBorderRadius; containerStyle.overflow = 'hidden'; }
      if (imgShadowCss) containerStyle.boxShadow = imgShadowCss;
      if (imgBgColor) containerStyle.backgroundColor = imgBgColor;

      if (!data.assetId && !data.src) {
        return (
          <div className="w-full h-full bg-base-300/10 flex flex-col items-center justify-center border border-dashed border-base-300/30" style={clipStyle}>
            <ImageIcon size={20} className="text-base-content/15 mb-1" />
            <span className="text-[9px] text-base-content/20">{el.type === 'fullbleed_image' ? 'Full-bleed' : el.type === 'background_image' ? 'Background' : 'Image'}</span>
            <span className="text-[8px] text-base-content/15 mt-0.5">Set image in Properties</span>
          </div>
        );
      }
      const imgStyle: React.CSSProperties = {
        width: '100%', height: data.showCaption && data.caption ? 'calc(100% - 20px)' : '100%',
        objectFit: (data.fit || 'cover') as any,
        objectPosition: data.focalPoint ? `${(data.focalPoint.x ?? 0.5) * 100}% ${(data.focalPoint.y ?? 0.5) * 100}%` : 'center',
        opacity: imgOpacity,
      };
      // Apply image filters
      const filters: string[] = [];
      if (data.filters?.brightness != null && data.filters.brightness !== 100) filters.push(`brightness(${data.filters.brightness}%)`);
      if (data.filters?.contrast != null && data.filters.contrast !== 100) filters.push(`contrast(${data.filters.contrast}%)`);
      if (data.filters?.saturation != null && data.filters.saturation !== 100) filters.push(`saturate(${data.filters.saturation}%)`);
      if (data.filters?.grayscale) filters.push('grayscale(1)');
      if (filters.length) imgStyle.filter = filters.join(' ');

      const caption = data.caption as string | undefined;
      const showCaption = (data.showCaption ?? true) && !!caption;

      return (
        <>
          <img src={data.src || `/api/v1/assets/${data.assetId}/serve`} alt={data.alt || ''} style={imgStyle} />
          {showCaption && (
            <div className="absolute bottom-0 left-0 right-0 bg-base-100/80 px-2 py-1 text-[9px] text-base-content/60 truncate" style={{ height: 20 }}>
              {caption}
            </div>
          )}
        </>
      );
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

    if (el.type === 'polygon') {
      const sides = data.sides || 6;
      const bg = data.fillColor || el.style?.fill?.color || '#e5e7eb';
      const points = Array.from({ length: sides }, (_, i) => {
        const angle = (Math.PI * 2 * i) / sides - Math.PI / 2;
        return `${50 + 50 * Math.cos(angle)}% ${50 + 50 * Math.sin(angle)}%`;
      }).join(', ');
      return <div className="w-full h-full" style={{ backgroundColor: bg, clipPath: `polygon(${points})` }} />;
    }

    if (el.type === 'freeform_path') {
      return (
        <svg width="100%" height="100%" viewBox={`0 0 ${el.width} ${el.height}`} style={{ overflow: 'visible' }}>
          {data.path ? <path d={data.path as string} fill={data.fillColor as string || 'none'} stroke={data.strokeColor as string || '#1a1a1a'} strokeWidth={data.strokeWidth as number || 2} /> :
            <path d={`M 10,${el.height - 10} Q ${el.width / 2},10 ${el.width - 10},${el.height - 10}`} fill="none" stroke="#1a1a1a" strokeWidth={2} />}
        </svg>
      );
    }

    if (el.type === 'decorative_rule') {
      const style = data.strokeStyle || 'solid';
      return (
        <div className="w-full h-full flex items-center">
          <hr className="w-full" style={{ border: 'none', borderTop: `${data.strokeWidth || 2}px ${style} ${data.strokeColor || '#999'}` }} />
        </div>
      );
    }

    if (el.type === 'video_frame') {
      const url = (data.url || '') as string;
      // Try to extract embed URL for YouTube/Vimeo
      const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/);
      const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
      if (youtubeMatch) {
        return <iframe src={`https://www.youtube-nocookie.com/embed/${youtubeMatch[1]}`} className="w-full h-full border-0" allow="accelerometer; autoplay; encrypted-media; gyroscope" allowFullScreen />;
      }
      if (vimeoMatch) {
        return <iframe src={`https://player.vimeo.com/video/${vimeoMatch[1]}`} className="w-full h-full border-0" allow="autoplay; fullscreen" allowFullScreen />;
      }
      return (
        <div className="w-full h-full bg-neutral/5 flex flex-col items-center justify-center border border-dashed border-base-300/30">
          <Film size={20} className="text-base-content/15 mb-1" />
          <span className="text-[9px] text-base-content/20">Video</span>
          <span className="text-[8px] text-base-content/15 mt-0.5">{url ? 'Unsupported URL' : 'Set URL in Properties'}</span>
        </div>
      );
    }

    if (el.type === 'audio_player') {
      const url = (data.url || '') as string;
      return (
        <div className="w-full h-full bg-base-200/50 flex flex-col justify-center gap-1 px-3 border border-base-300/20 rounded">
          <div className="flex items-center gap-2">
            <div className="w-6 h-6 rounded-full bg-primary/20 flex items-center justify-center shrink-0"><span className="text-primary text-[10px]">▶</span></div>
            <div className="flex-1 min-w-0">
              <div className="text-[11px] font-medium text-base-content/60 truncate">{data.title || 'Audio'}</div>
              {data.artist && <div className="text-[9px] text-base-content/30">{data.artist}</div>}
            </div>
          </div>
          {url && <audio src={url} controls className="w-full h-6" style={{ minHeight: 24 }} />}
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
          <span className="text-[9px] text-info/50">{data.tooltipContent || 'Tooltip'}</span>
        </div>
      );
    }

    if (el.type === 'accordion_frame') {
      const sections = (data.sections || [{ title: 'Section', content: 'Content' }]) as Array<{ title: string; content: string }>;
      return (
        <div className="w-full h-full overflow-hidden text-[10px]">
          {sections.map((s, i) => (
            <div key={i} className="border-b border-base-300/20">
              <div className="flex items-center justify-between px-2 py-1.5 bg-base-200/30 font-medium text-base-content/60">
                <span>{s.title}</span>
                <span className="text-[8px]">{i === 0 ? '▼' : '▶'}</span>
              </div>
              {i === 0 && <div className="px-2 py-1 text-base-content/40">{s.content}</div>}
            </div>
          ))}
        </div>
      );
    }

    if (el.type === 'slidein_panel') {
      const dir = (data.direction || 'right') as string;
      const borderSide = dir === 'left' ? 'border-l-4' : dir === 'top' ? 'border-t-4' : dir === 'bottom' ? 'border-b-4' : 'border-r-4';
      return (
        <div className={`w-full h-full ${borderSide} border-secondary/40 bg-secondary/5 flex flex-col p-2 rounded overflow-hidden`}>
          <div className="flex items-center gap-1 mb-1">
            <span className="text-[9px] font-medium text-secondary/60">{data.triggerLabel || 'Panel'}</span>
            <span className="text-[7px] text-secondary/30">→ {dir}</span>
          </div>
          <div className="flex-1 text-[8px] text-base-content/30 overflow-hidden">{(data.content as string)?.replace(/<[^>]+>/g, '') || 'Panel content'}</div>
        </div>
      );
    }

    if (el.type === 'column_guides') {
      const cols = (data.columns || 3) as number;
      const gutter = (data.gutter || 12) as number;
      return (
        <div className="w-full h-full flex pointer-events-none" style={{ gap: gutter }}>
          {Array.from({ length: cols }, (_, i) => (
            <div key={i} className="flex-1 h-full border-x border-primary/10 bg-primary/[0.02]" />
          ))}
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
      const items = (data.data || []) as Array<{ label: string; value: number; color: string | null }>;
      const max = Math.max(...items.map(i => i.value), 1);
      const chartType = (data.chartType || 'bar') as string;

      if (chartType === 'pie' || chartType === 'donut') {
        const total = items.reduce((s, i) => s + i.value, 0) || 1;
        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
        let cumAngle = 0;
        const slices = items.map((item, i) => {
          const angle = (item.value / total) * 360;
          const start = cumAngle;
          cumAngle += angle;
          return { ...item, start, angle, color: item.color || colors[i % colors.length] };
        });
        const r = 40; const cx = 50; const cy = 50;
        return (
          <div className="w-full h-full flex items-center justify-center p-2">
            <svg viewBox="0 0 100 100" width="80%" height="80%">
              {slices.map((s, i) => {
                // Single item = full circle (SVG arc with identical endpoints renders nothing)
                if (s.angle >= 359.99) {
                  return <circle key={i} cx={cx} cy={cy} r={r} fill={s.color} />;
                }
                const startRad = (s.start - 90) * Math.PI / 180;
                const endRad = (s.start + s.angle - 90) * Math.PI / 180;
                const largeArc = s.angle > 180 ? 1 : 0;
                const x1 = cx + r * Math.cos(startRad);
                const y1 = cy + r * Math.sin(startRad);
                const x2 = cx + r * Math.cos(endRad);
                const y2 = cy + r * Math.sin(endRad);
                return <path key={i} d={`M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2} Z`} fill={s.color} />;
              })}
              {chartType === 'donut' && <circle cx={cx} cy={cy} r={20} fill="var(--b1, white)" />}
            </svg>
          </div>
        );
      }

      if (chartType === 'line') {
        const w = el.width - 20; const h = el.height - 30;
        const points = items.map((item, i) => `${10 + (i / Math.max(items.length - 1, 1)) * w},${10 + h - (item.value / max) * h}`).join(' ');
        return (
          <div className="w-full h-full p-1">
            <svg width="100%" height="100%" viewBox={`0 0 ${el.width} ${el.height}`}>
              <polyline points={points} fill="none" stroke="#3b82f6" strokeWidth={2} />
              {items.map((item, i) => {
                const x = 10 + (i / Math.max(items.length - 1, 1)) * w;
                const y = 10 + h - (item.value / max) * h;
                return <circle key={i} cx={x} cy={y} r={3} fill="#3b82f6" />;
              })}
            </svg>
          </div>
        );
      }

      // Default: bar chart
      return (
        <div className="w-full h-full flex items-end gap-1 p-2">
          {items.map((item, i) => (
            <div key={i} className="flex-1 flex flex-col items-center gap-0.5">
              <div className="w-full rounded-t" style={{ height: `${(item.value / max) * 80}%`, backgroundColor: item.color || '#3b82f6' }} />
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
      const max = Math.min(100, Math.max(1, Number(data.max) || 100));
      const value = Math.min(max, Math.max(0, Number(data.value) || 0));
      const pct = Math.round((value / max) * 100);
      return (
        <div className="w-full h-full flex flex-col justify-center px-2 gap-1">
          {data.showLabel && <span className="text-[9px] text-base-content/40">{data.label || 'Progress'}: {pct}%</span>}
          <div className="w-full h-2 bg-base-300/20 rounded-full overflow-hidden">
            <div className="h-full rounded-full transition-all" style={{ width: `${pct}%`, backgroundColor: (data.color as string) || 'oklch(var(--p))' }} />
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
      const iconName = (data.name || 'star') as string;
      const color = (data.color || '#1a1a1a') as string;
      const customSvg = data.customSvg as string | null;
      const ICON_PATHS: Record<string, string> = {
        star: 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
        heart: 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',
        check: 'M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z',
        arrow: 'M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8-8-8z',
        circle: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z',
        quote: 'M6 17h3l2-4V7H5v6h3L6 17zm8 0h3l2-4V7h-6v6h3l-2 4z',
        mail: 'M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z',
        phone: 'M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z',
        pin: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
      };
      if (customSvg) {
        return <div className="w-full h-full flex items-center justify-center" style={{ color }} dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(customSvg, { USE_PROFILES: { svg: true } }) }} />;
      }
      const path = ICON_PATHS[iconName] || ICON_PATHS.star;
      return (
        <div className="w-full h-full flex items-center justify-center" style={{ color }}>
          <svg viewBox="0 0 24 24" fill="currentColor" width="60%" height="60%"><path d={path} /></svg>
        </div>
      );
    }

    if (el.type === 'embed_frame') {
      return (
        <div className="w-full h-full bg-base-200/30 flex flex-col items-center justify-center border border-dashed border-base-300/30">
          <span className="text-[10px] text-base-content/20">Embed</span>
          <span className="text-[8px] text-base-content/15 mt-0.5">{data.html ? 'HTML content set' : 'Set HTML in Properties'}</span>
        </div>
      );
    }

    if (el.type === 'group') {
      return (
        <div className="w-full h-full border-2 border-dashed border-base-content/10 rounded">
          <div className="absolute top-0 left-0 bg-base-content/5 px-1 py-0.5 text-[7px] text-base-content/30 rounded-br">Group</div>
        </div>
      );
    }

    if (el.type === 'clipping_group') {
      return (
        <div className="w-full h-full border-2 border-dashed border-accent/20 rounded overflow-hidden">
          <div className="absolute top-0 left-0 bg-accent/5 px-1 py-0.5 text-[7px] text-accent/40 rounded-br">Clip</div>
        </div>
      );
    }

    if (el.type === 'component_instance') {
      return (
        <div className="w-full h-full border-2 border-dashed border-secondary/20 rounded">
          <div className="absolute top-0 left-0 bg-secondary/5 px-1 py-0.5 text-[7px] text-secondary/40 rounded-br">Component</div>
          {data.componentId && <span className="absolute bottom-1 left-1 text-[7px] text-base-content/20 font-mono">{(data.componentId as string).slice(0, 8)}</span>}
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

      {/* Overflow indicator + Continue button */}
      {TEXT_FRAME_TYPES.includes(el.type) && isOverflowing && !isEditing && (() => {
        // Show Continue button if this is the last frame in the thread (or no thread)
        const isLastInThread = !el.threadId || (() => {
          if (!allPages) return true;
          const allEls = allPages.flatMap(p => p.elements || []);
          const threadFrames = allEls.filter(e => e.threadId === el.threadId);
          const maxOrder = Math.max(...threadFrames.map(e => e.threadOrder ?? 0));
          return (el.threadOrder ?? 0) >= maxOrder;
        })();
        return isLastInThread ? (
          <button
            className="absolute bottom-0 right-0 h-6 px-1.5 bg-error hover:bg-error/80 text-error-content flex items-center gap-0.5 text-[8px] font-bold rounded-tl cursor-pointer z-[9998]"
            title="Continue text to next page"
            onClick={(e) => { e.stopPropagation(); onContinueText?.(el.id); }}
            onPointerDown={(e) => e.stopPropagation()}
          >+ Continue</button>
        ) : (
          <div className="absolute bottom-0 right-0 w-5 h-5 bg-warning text-warning-content flex items-center justify-center text-[9px] font-bold rounded-tl cursor-help"
            title="Text overflows — linked to continuation frame">...</div>
        );
      })()}

      {/* Thread / linked frame indicators */}
      {el.threadId && (() => {
        const allEls = allPages?.flatMap(p => p.elements) || [];
        const threadFrames = allEls.filter(e => e.threadId === el.threadId).sort((a, b) => (a.threadOrder ?? 0) - (b.threadOrder ?? 0));
        const myIndex = threadFrames.findIndex(f => f.id === el.id);
        const isFirst = myIndex === 0;
        const isLast = myIndex === threadFrames.length - 1;
        const nextFrame = !isLast ? threadFrames[myIndex + 1] : null;
        const prevFrame = !isFirst ? threadFrames[myIndex - 1] : null;
        return (
          <>
            {prevFrame && (
              <div className="absolute -top-5 left-0 bg-blue-500/80 text-white text-[7px] px-1.5 py-0.5 rounded-b font-medium pointer-events-none whitespace-nowrap">
                ← Continued from p.{prevFrame.pageNumber}
              </div>
            )}
            {nextFrame && (
              <div className="absolute -bottom-5 right-0 bg-blue-500/80 text-white text-[7px] px-1.5 py-0.5 rounded-t font-medium pointer-events-none whitespace-nowrap">
                Continues → p.{nextFrame.pageNumber}
              </div>
            )}
            <div className="absolute -bottom-1 -right-1 w-3 h-3 bg-blue-500 rounded-full border border-white pointer-events-none" title={`Thread ${(el.threadOrder ?? 0) + 1} of ${threadFrames.length}`} />
          </>
        );
      })()}

      {/* Edit/Done button is in the properties panel — not on the frame
           (overflow:hidden clips anything above the frame boundary) */}

      {/* Fixed badge */}
      {el.positionMode === 'fixed' && (
        <div className="absolute top-1 left-1 bg-amber-500/80 text-white text-[7px] px-1.5 py-0.5 rounded font-bold pointer-events-none z-[9998]">
          FIXED
        </div>
      )}

      {/* Spread badge */}
      {el.spanMode === 'spread' && (
        <div className="absolute top-1 right-8 bg-purple-500/80 text-white text-[7px] px-1.5 py-0.5 rounded font-bold pointer-events-none z-[9998]">
          SPREAD
        </div>
      )}

      {/* Fix/Unfix and Spread/Single controls moved to right panel (DtpEditorBeta properties tab) */}

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
