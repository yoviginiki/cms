import React, { useEffect, useRef } from 'react';
import type { BlockComponentProps } from '@/types/blocks';
import { compose } from './composition';
import { resolveOptions, SHADOW_CSS, ARROW_PX } from './options';

/**
 * Canvas preview with the same composition, hover, arrows and mouse-drag as
 * the published block (no lightbox, no inertia — this is for layout work).
 */
export const LinearGalleryPreview: React.FC<BlockComponentProps> = ({ block }) => {
  const o = resolveOptions(block.data as Record<string, unknown>);
  const items = compose(o.images, o.sizeVariation, o.scatter);
  const trackRef = useRef<HTMLDivElement>(null);
  const prevRef = useRef<HTMLButtonElement>(null);
  const nextRef = useRef<HTMLButtonElement>(null);

  // Preview canvas is narrower than a real page — keep the strip readable.
  const h = Math.min(o.height, 280);
  const strip = o.stripHeight > 0 ? Math.min(o.stripHeight, 420) : undefined;
  const hoverBorder = o.hoverBorderColor || '#ffffff';
  const arrowPx = ARROW_PX[o.arrowsSize];

  useEffect(() => {
    const track = trackRef.current; if (!track) return;
    const prev = prevRef.current, next = nextRef.current;
    const update = () => {
      const max = track.scrollWidth - track.clientWidth - 1;
      if (prev) prev.disabled = track.scrollLeft <= 1;
      if (next) next.disabled = track.scrollLeft >= max;
    };
    let drag: { x: number; left: number; id: number } | null = null; let moved = 0;
    const down = (e: PointerEvent) => { if (!o.drag || e.pointerType === 'touch' || e.button !== 0) return; drag = { x: e.clientX, left: track.scrollLeft, id: e.pointerId }; moved = 0; };
    const move = (e: PointerEvent) => {
      if (!drag) return; const dx = e.clientX - drag.x;
      if (Math.abs(dx) > 4 && !track.classList.contains('is-dragging')) { track.classList.add('is-dragging'); try { track.setPointerCapture(drag.id); } catch { /* noop */ } }
      moved = Math.max(moved, Math.abs(dx)); track.scrollLeft = drag.left - dx;
    };
    const up = () => { if (!drag) return; drag = null; setTimeout(() => track.classList.remove('is-dragging'), 0); };
    const click = (e: MouseEvent) => { if (moved > 6) { e.preventDefault(); e.stopPropagation(); moved = 0; } };
    const page = (dir: number) => track.scrollBy({ left: dir * Math.max(200, track.clientWidth * 0.6), behavior: 'smooth' });
    const onPrev = () => page(-1), onNext = () => page(1);
    track.addEventListener('pointerdown', down); track.addEventListener('pointermove', move);
    track.addEventListener('pointerup', up); track.addEventListener('pointercancel', up); track.addEventListener('lostpointercapture', up);
    track.addEventListener('click', click, true); track.addEventListener('scroll', update, { passive: true });
    prev?.addEventListener('click', onPrev); next?.addEventListener('click', onNext);
    if (o.align === 'center' && track.scrollWidth > track.clientWidth) track.scrollLeft = (track.scrollWidth - track.clientWidth) / 2;
    update();
    const ro = new ResizeObserver(update); ro.observe(track);
    return () => {
      track.removeEventListener('pointerdown', down); track.removeEventListener('pointermove', move);
      track.removeEventListener('pointerup', up); track.removeEventListener('pointercancel', up); track.removeEventListener('lostpointercapture', up);
      track.removeEventListener('click', click, true); track.removeEventListener('scroll', update);
      prev?.removeEventListener('click', onPrev); next?.removeEventListener('click', onNext); ro.disconnect();
    };
  }, [o.drag, o.align, items.length]);

  if (items.length === 0) {
    return (
      <div className="bg-gray-100 border-2 border-dashed border-gray-300 rounded-lg p-12 text-center text-gray-400">
        <p className="text-sm">Linear Gallery — no images</p>
        <p className="text-xs mt-1">Add images from the settings panel</p>
      </div>
    );
  }

  const vars = {
    '--lgp-h': `${h}px`, '--lgp-overlap': o.overlap / 100, '--lgp-opacity': o.opacity / 100, '--lgp-blend': o.blend,
    '--lgp-radius': `${o.radius}px`, '--lgp-border': o.borderColor || 'transparent', '--lgp-bw': `${o.borderWidth}px`,
    '--lgp-shadow': SHADOW_CSS[o.shadow], '--lgp-hborder': hoverBorder, '--lgp-hbw': `${o.hoverBorderWidth}px`,
    '--lgp-hshadow': SHADOW_CSS[o.hoverShadow], '--lgp-hglow': o.hoverGlow ? `0 0 22px 2px color-mix(in srgb, ${hoverBorder} 60%, transparent)` : '0 0 0 0 transparent',
    '--lgp-arrow': `${arrowPx}px`, '--lgp-arrow-c': o.arrowsColor || '#111', '--lgp-arrow-bg': o.arrowsBg || 'rgba(255,255,255,.85)',
  } as React.CSSProperties;

  const arrowPos = (side: 'prev' | 'next'): React.CSSProperties => {
    const p = o.arrowsPosition;
    if (p === 'sides') return side === 'prev' ? { left: 12, top: '50%', transform: 'translateY(-50%)' } : { right: 12, top: '50%', transform: 'translateY(-50%)' };
    if (p === 'bottom-right') return side === 'prev' ? { bottom: 12, right: arrowPx + 20 } : { bottom: 12, right: 12 };
    if (p === 'bottom-center') return side === 'prev' ? { bottom: 12, left: `calc(50% - ${arrowPx + 6}px)` } : { bottom: 12, left: 'calc(50% + 6px)' };
    return side === 'prev' ? { top: 12, right: arrowPx + 20 } : { top: 12, right: 12 };
  };

  return (
    <div className={`lgp relative w-full min-w-0 group/lgp ${o.width === 'full' ? 'lgp--full' : ''}`} style={{ ...vars, background: o.background || 'transparent', contain: 'inline-size' }}>
      {o.arrows && items.length > 1 && (['prev', 'next'] as const).map(side => (
        <button key={side} ref={side === 'prev' ? prevRef : nextRef} type="button"
          className={`lgp-arrow absolute z-[70] flex items-center justify-center rounded-full border cursor-pointer transition-opacity ${o.arrowsShow === 'hover' ? 'opacity-0 group-hover/lgp:opacity-100' : ''} disabled:opacity-25`}
          style={{ ...arrowPos(side), width: arrowPx, height: arrowPx, color: 'var(--lgp-arrow-c)', background: 'var(--lgp-arrow-bg)', borderColor: 'rgba(0,0,0,.15)' }}
          onClick={e => e.stopPropagation()} title={side === 'prev' ? 'Scroll left' : 'Scroll right'}>
          <svg viewBox="0 0 24 24" style={{ width: '45%', height: '45%' }} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            {side === 'prev' ? <path d="m15 18-6-6 6-6" /> : <path d="m9 18 6-6-6-6" />}
          </svg>
        </button>
      ))}
      <div ref={trackRef} className={`lgp-track flex items-center w-full overflow-x-auto ${o.drag ? 'cursor-grab' : ''}`} style={{ overflowY: 'clip',
        height: strip, minHeight: h + o.padding,
        padding: `${o.padding * 0.5 + h * 0.35}px ${o.offsetEnd}px ${o.padding * 0.5 + h * 0.35}px ${o.offsetStart}px`,
        justifyContent: o.align === 'center' ? 'safe center' : 'flex-start', isolation: 'isolate',
        scrollbarWidth: o.scrollbar ? 'thin' : 'none', userSelect: 'none',
      }}>
        {items.map((it, i) => (
          <figure key={(it.id || it.src) + i} className="lgp-item shrink-0 m-0 relative" style={{
            '--dy': `${h * it.dy}px`, height: h * it.scale, transform: `translateY(${h * it.dy}px)`,
            marginLeft: i === 0 ? 0 : -(h * o.overlap / 100), zIndex: it.z,
          } as React.CSSProperties}>
            <span className="block h-full relative" style={{ borderRadius: 'var(--lgp-radius)', boxShadow: '0 0 0 var(--lgp-bw) var(--lgp-border), var(--lgp-shadow)', transition: 'box-shadow .3s ease' }}>
              <img src={it.src} alt={it.alt || ''} draggable={false} className="block h-full w-auto max-w-none object-cover pointer-events-none" style={{ borderRadius: 'var(--lgp-radius)' }} />
              {it.caption && <figcaption className="lgp-cap absolute inset-x-0 bottom-0 px-2 py-1 text-[10px] text-white" style={{ background: 'linear-gradient(to top, rgba(0,0,0,.65), transparent)', borderRadius: '0 0 var(--lgp-radius) var(--lgp-radius)' }}>{it.caption}</figcaption>}
            </span>
          </figure>
        ))}
        <span className="shrink-0" style={{ flexBasis: o.offsetEnd }} aria-hidden="true" />
      </div>
      <style>{`
        .lgp-track::-webkit-scrollbar { display: ${o.scrollbar ? 'block' : 'none'}; height: 6px; }
        .lgp-track.is-dragging { cursor: grabbing; }
        .lgp-track.is-dragging .lgp-item { pointer-events: none; }
        .lgp-item { mix-blend-mode: var(--lgp-blend); opacity: var(--lgp-opacity); transition: transform .3s ease, opacity .3s ease; }
        .lgp-item .lgp-cap { opacity: 0; transition: opacity .3s ease; }
        .lgp-item:hover { z-index: 60 !important; mix-blend-mode: normal; opacity: 1; ${o.hoverLift ? 'transform: translateY(calc(var(--dy, 0px) - 6px)) scale(1.03) !important;' : ''} }
        .lgp-item:hover .lgp-cap { opacity: 1; }
        .lgp-item:hover > span { box-shadow: 0 0 0 var(--lgp-hbw) var(--lgp-hborder), var(--lgp-hglow), var(--lgp-hshadow) !important; }
      `}</style>
      <p className="absolute left-1/2 -translate-x-1/2 bottom-1 text-[10px] uppercase tracking-widest text-gray-400 pointer-events-none">
        {o.width === 'full' ? 'full width on the site · ' : ''}preview
      </p>
    </div>
  );
};
