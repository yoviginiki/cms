import { memo, useMemo, useRef, useState } from 'react';
import { ChevronUp, ChevronDown, Trash2, Plus } from 'lucide-react';
import type { CanvasSection as Section } from '@/types/canvas';
import { effectiveLayout } from '@/types/canvas';
import { colorForId } from '@/lib/collabColor';
import { useCanvasStore } from '@/stores/canvasStore';
import { useCanvasSelection } from './useCanvasSelection';
import { CanvasElement } from './CanvasElement';
import { CanvasPalette } from './CanvasPalette';
import { CHROME, CHROME_Z } from './chrome';
import { CANVAS_DRAG_MIME, defaultSize, dropPosition } from '@/lib/canvasBlocks';
import type { PeerCursor, PresenceMember } from './useCanvasCollab';

const AUTO_MIN_H = 480;   // an auto section never shows smaller than this in the editor (room to work)
const AUTO_PAD = 120;     // auto: breathing room below the lowest element so you can drop under it
const OVER_PAD = 40;      // fixed: how much of the overflow area to reveal below the section edge
const MIN_SECTION_H = 100;

interface Props {
  section: Section;
  width: number;      // design width (px)
  zoom: number;
  isActive: boolean;
  canMoveUp: boolean;
  canMoveDown: boolean;
  singleMode: boolean;
  peerCursors?: PeerCursor[];
  members?: PresenceMember[];
  onCursorMove?: (sectionId: string, x: number, y: number) => void;
  lockedIds?: Set<string>;
}

function CanvasSectionInner({ section, width, zoom, isActive, canMoveUp, canMoveDown, singleMode, peerCursors = [], members = [], onCursorMove, lockedIds }: Props) {
  const selectedIds = useCanvasStore(s => s.selectedIds);
  const editingId = useCanvasStore(s => s.editingId);
  const bp = useCanvasStore(s => s.activeBreakpoint);
  const mobileWidth = useCanvasStore(s => s.mobileWidth);
  // Actions are stable refs — read once (getState) rather than subscribing the
  // whole store, which re-rendered every section on every op.
  const { updateSectionSettings, deleteSection, moveSection, addElement, clearSelection, setActiveSection, pushSnapshot } = useCanvasStore.getState();
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [dropHover, setDropHover] = useState(false);
  const canvasRef = useRef<HTMLDivElement>(null);

  // Palette drag-and-drop: a block tile dropped here lands centred under the pointer.
  const isBlockDrag = (e: React.DragEvent) => Array.from(e.dataTransfer.types).includes(CANVAS_DRAG_MIME);
  const onDragOver = (e: React.DragEvent) => {
    if (!isBlockDrag(e)) return;
    e.preventDefault();
    e.dataTransfer.dropEffect = 'copy';
    if (!dropHover) setDropHover(true);
  };
  const onDrop = (e: React.DragEvent) => {
    if (!isBlockDrag(e) || !canvasRef.current) return;
    e.preventDefault();
    setDropHover(false);
    const blockType = e.dataTransfer.getData(CANVAS_DRAG_MIME);
    if (!blockType) return;
    const size = defaultSize(blockType);
    const { x, y } = dropPosition(e.clientX, e.clientY, canvasRef.current.getBoundingClientRect(), zoom, size);
    addElement(section.id, blockType, x, y, size.width, size.height);
  };

  const effWidth = bp === 'mobile' ? mobileWidth : width;
  // Effective layout / height / z-sort recompute only when this section's
  // elements, height, or the active breakpoint change (not on every parent render).
  // The canvas always shows ALL content: an auto section grows with its lowest
  // element (plus room to drop below it); a fixed section reveals anything that
  // overflows its edge as a marked "outside the section" strip instead of clipping.
  const { displayHeight, fixedHeight, sorted } = useMemo(() => {
    const laid = section.elements.map(el => ({ el, eff: effectiveLayout(el, bp) })).filter(x => !x.eff.hidden);
    const maxBottom = laid.reduce((m, { eff }) => Math.max(m, eff.y + eff.height), 0);
    const fixedHeight = section.settings.height === 'auto' ? null : section.settings.height;
    const displayHeight = fixedHeight === null
      ? Math.max(AUTO_MIN_H, maxBottom + AUTO_PAD)
      : Math.max(fixedHeight, maxBottom > fixedHeight ? maxBottom + OVER_PAD : 0);
    const sorted = [...laid].sort((a, b) => a.eff.zIndex - b.eff.zIndex);
    return { displayHeight, fixedHeight, sorted };
  }, [section.elements, section.settings.height, bp]);

  // Bottom edge handle: drag to set the section height (an auto section becomes fixed).
  const onHeightHandleDown = (e: React.PointerEvent) => {
    e.stopPropagation();
    e.preventDefault();
    pushSnapshot();
    const startY = e.clientY;
    const startH = fixedHeight ?? displayHeight;
    const z = zoom || 1;
    const move = (ev: PointerEvent) => {
      updateSectionSettings(section.id, { height: Math.max(MIN_SECTION_H, Math.round(startH + (ev.clientY - startY) / z)) });
    };
    const up = () => { window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };
  const edgeY = fixedHeight ?? displayHeight;   // where the section really ends

  const { guides, onElementPointerDown, onResizePointerDown, onRotatePointerDown } =
    useCanvasSelection(section.id, effWidth, displayHeight);

  const addBlock = (blockType: string) => {
    // drop near the top-left, offset so successive adds don't fully overlap
    const n = section.elements.length;
    const { width, height } = defaultSize(blockType);
    addElement(section.id, blockType, 40 + (n % 5) * 24, 40 + (n % 5) * 24, width, height);
  };

  return (
    <div className={`cv-section-wrap border-b border-base-200 ${isActive ? 'ring-1 ring-primary/40' : ''}`}>
      {/* controls bar — its inputs must not clear the canvas selection */}
      <div className="flex items-center gap-2 px-3 py-1.5 bg-base-200/60 text-xs" onPointerDown={(e) => e.stopPropagation()}>
        <span className="font-medium text-base-content/60">Section</span>
        <label className="flex items-center gap-1">
          H
          <input
            type="number"
            className="input input-xs input-bordered w-20"
            value={section.settings.height === 'auto' ? '' : section.settings.height}
            placeholder="auto"
            onChange={(e) => updateSectionSettings(section.id, { height: e.target.value === '' ? 'auto' : Number(e.target.value) })}
          />
        </label>
        <button
          className={`btn btn-xs ${section.settings.height === 'auto' ? 'btn-primary' : 'btn-ghost'}`}
          onClick={() => updateSectionSettings(section.id, { height: section.settings.height === 'auto' ? 480 : 'auto' })}
        >auto</button>
        <label className="flex items-center gap-1">
          <input type="checkbox" className="checkbox checkbox-xs" checked={section.settings.bleed} onChange={(e) => updateSectionSettings(section.id, { bleed: e.target.checked })} />
          bleed
        </label>
        <label className="flex items-center gap-1" title="Flex with the viewport (elements hold their pin anchors) instead of stacking">
          <input type="checkbox" className="checkbox checkbox-xs" checked={!!section.settings.fluid} onChange={(e) => updateSectionSettings(section.id, { fluid: e.target.checked })} />
          fluid
        </label>
        <label className="flex items-center gap-1">
          bg
          <input type="color" className="w-6 h-5 rounded border-0 bg-transparent p-0" value={section.settings.background || '#ffffff'} onChange={(e) => updateSectionSettings(section.id, { background: e.target.value })} />
        </label>
        <div className="relative">
          <button className="btn btn-xs btn-primary gap-1" onClick={() => setPaletteOpen(v => !v)}><Plus size={12} /> Block</button>
          {paletteOpen && <CanvasPalette onPick={addBlock} onClose={() => setPaletteOpen(false)} />}
        </div>
        <div className="flex-1" />
        {!singleMode && (
          <>
            <button className="btn btn-xs btn-ghost" disabled={!canMoveUp} onClick={() => moveSection(section.id, 'up')} title="Move up" aria-label="Move section up"><ChevronUp size={14} /></button>
            <button className="btn btn-xs btn-ghost" disabled={!canMoveDown} onClick={() => moveSection(section.id, 'down')} title="Move down" aria-label="Move section down"><ChevronDown size={14} /></button>
            <button className="btn btn-xs btn-ghost text-error" onClick={() => deleteSection(section.id)} title="Delete section" aria-label="Delete section"><Trash2 size={14} /></button>
          </>
        )}
      </div>

      {/* the canvas */}
      <div className="flex justify-center bg-base-300/30 py-4 overflow-hidden">
        <div style={{ width: effWidth * zoom, height: displayHeight * zoom }}>
          <div
            ref={canvasRef}
            className="cv-canvas relative shadow-sm"
            data-testid="canvas-drop-target"
            onDragOver={onDragOver}
            onDragEnter={onDragOver}
            onDragLeave={(e) => { if (!canvasRef.current?.contains(e.relatedTarget as Node)) setDropHover(false); }}
            onDrop={onDrop}
            onPointerDown={() => { clearSelection(); setActiveSection(section.id); }}
            onPointerMove={(e) => {
              if (!onCursorMove || !canvasRef.current) return;
              const r = canvasRef.current.getBoundingClientRect();
              onCursorMove(section.id, (e.clientX - r.left) / (zoom || 1), (e.clientY - r.top) / (zoom || 1));
            }}
            style={{
              width: effWidth, height: displayHeight,
              transform: `scale(${zoom})`, transformOrigin: 'top left',
              background: section.settings.background || '#ffffff',
              // faint dot grid — orientation only; movement is free-flow (no grid snap)
              backgroundImage: 'radial-gradient(rgba(0,0,0,0.12) 1px, transparent 1px)',
              backgroundSize: '20px 20px',
              outline: dropHover ? `2px dashed ${CHROME.selection}` : bp === 'mobile' ? `2px solid ${CHROME.mobileOutline}` : undefined,
              outlineOffset: dropHover ? -2 : undefined,
            }}
          >
            {sorted.map(({ el, eff }) => (
              <CanvasElement
                key={el.id}
                el={el}
                eff={eff}
                selected={selectedIds.includes(el.id)}
                editing={editingId === el.id}
                peerLocked={lockedIds?.has(el.id)}
                zoom={zoom}
                onPointerDown={onElementPointerDown}
                onResizeDown={onResizePointerDown}
                onRotateDown={onRotatePointerDown}
              />
            ))}
            {/* overflow strip: content below a fixed section's edge is outside the section */}
            {fixedHeight !== null && displayHeight > fixedHeight && (
              <div
                aria-hidden
                style={{
                  position: 'absolute', left: 0, right: 0, top: fixedHeight, bottom: 0, pointerEvents: 'none',
                  backgroundImage: 'repeating-linear-gradient(135deg, rgba(220,38,38,0.08) 0 6px, transparent 6px 14px)',
                  zIndex: CHROME_Z.peerCursor - 1,
                }}
              >
                <span style={{ position: 'absolute', left: 8, top: 4, fontSize: 10 / (zoom || 1), color: '#b91c1c', background: 'rgba(255,255,255,0.85)', padding: '1px 6px', borderRadius: 4 }}>
                  Outside the section — drag the bar down or set height to auto
                </span>
              </div>
            )}
            {/* section height handle (bottom edge) */}
            <div
              role="separator"
              aria-label="Section height — drag to resize"
              title={fixedHeight === null ? 'Auto height — drag to set a fixed height' : `Height ${fixedHeight}px — drag to resize`}
              data-testid="section-height-handle"
              onPointerDown={onHeightHandleDown}
              style={{
                position: 'absolute', left: 0, right: 0, top: edgeY - 5 / (zoom || 1), height: 10 / (zoom || 1),
                cursor: 'ns-resize', zIndex: CHROME_Z.peerCursor, display: 'flex', alignItems: 'center', justifyContent: 'center',
                borderTop: `${1 / (zoom || 1)}px ${fixedHeight === null ? 'dashed' : 'solid'} ${CHROME.selection}`,
              }}
            >
              <span style={{ width: 44 / (zoom || 1), height: 6 / (zoom || 1), borderRadius: 3, background: CHROME.selection, boxShadow: '0 0 0 1px #fff' }} />
            </div>
            {/* smart guides */}
            {guides.map((g, i) => (
              <div
                key={i}
                style={g.type === 'vertical'
                  ? { position: 'absolute', left: g.position, top: 0, bottom: 0, width: 1, background: CHROME.guide, pointerEvents: 'none' }
                  : { position: 'absolute', top: g.position, left: 0, right: 0, height: 1, background: CHROME.guide, pointerEvents: 'none' }}
              />
            ))}
            {/* live peer cursors (Phase 2) — counter-scaled to stay constant size */}
            {peerCursors.map((c) => {
              const color = colorForId(c.id);
              const name = members.find((m) => m.id === c.id)?.name ?? '';
              return (
                <div key={c.id} style={{ position: 'absolute', left: c.x, top: c.y, transform: `scale(${1 / (zoom || 1)})`, transformOrigin: 'top left', pointerEvents: 'none', zIndex: CHROME_Z.peerCursor, willChange: 'left, top' }}>
                  <svg width="18" height="18" viewBox="0 0 24 24" style={{ display: 'block', filter: 'drop-shadow(0 1px 1px rgba(0,0,0,0.35))' }}>
                    <path d="M4 2l7 18 2.5-7.5L21 10z" fill={color} stroke="#fff" strokeWidth="1.5" strokeLinejoin="round" />
                  </svg>
                  {name && (
                    <span style={{ display: 'inline-block', marginLeft: 10, marginTop: -4, background: color, color: '#fff', fontSize: 10, fontWeight: 600, padding: '1px 5px', borderRadius: 4, whiteSpace: 'nowrap' }}>{name}</span>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}

// Memoized: with the store's structural sharing (untouched sections keep their
// ref) + stable peerCursors, a single-element drag re-renders only its section.
export const CanvasSection = memo(CanvasSectionInner);
