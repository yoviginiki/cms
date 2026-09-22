import { useState } from 'react';
import {
  ChevronDown, ChevronRight, ChevronsDown, ChevronsUp, ArrowDown, ArrowUp,
  Copy, Lock, Unlock, Trash2, MousePointerClick, X,
} from 'lucide-react';
import { blockRegistry } from '@/components/blocks/registry';
import '@/components/blocks';
import { BlockIcon } from '@/components/editor/BlockIcon';
import { VisualPanel } from '@/components/editor/properties/VisualPanel';
import { SpacingPanel } from '@/components/editor/properties/SpacingPanel';
import { TypographyPanel } from '@/components/editor/properties/TypographyPanel';
import { useCanvasStore } from '@/stores/canvasStore';
import { effectiveLayout, MOBILE_W_MIN, MOBILE_W_MAX, CANVAS_W_MIN, CANVAS_W_MAX } from '@/types/canvas';
import type { BlockData, BlockStyleProps, ResponsiveOverrides } from '@/types/blocks';
import type { CanvasAnim, CanvasFit, CanvasPageType, PinX } from '@/types/canvas';

const ANIMS: CanvasAnim['type'][] = ['none', 'fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'zoom', 'scale-in'];

interface Props {
  // Page-level canvas meta (type / design width / phone width) is persisted on
  // the page, not in the block tree — the editor owns that write.
  persistCanvasMeta: (patch: { page_type?: CanvasPageType; width?: number; mobile_width?: number; fit?: CanvasFit }) => void;
}

function Group({ title, children, defaultOpen = true }: { title: string; children: React.ReactNode; defaultOpen?: boolean }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border-t border-base-300/30">
      <button type="button" onClick={() => setOpen(v => !v)}
        className="w-full flex items-center gap-1.5 py-2 text-[10px] font-medium text-base-content/45 uppercase tracking-wider hover:text-base-content/70">
        {open ? <ChevronDown size={11} /> : <ChevronRight size={11} />}
        {title}
      </button>
      {open && <div className="pb-3 space-y-2">{children}</div>}
    </div>
  );
}

function Num({ label, value, onChange, onFocus, min, max, step = 1, suffix }: {
  label: string; value: number; onChange: (v: number) => void; onFocus?: () => void; min?: number; max?: number; step?: number; suffix?: string;
}) {
  return (
    <label className="flex items-center gap-1 text-[11px] text-base-content/60">
      <span className="w-4 shrink-0 font-medium">{label}</span>
      <input
        type="number"
        className="input input-xs input-bordered w-full min-w-0 text-[11px]"
        value={Number.isFinite(value) ? Math.round(value * 100) / 100 : 0}
        min={min} max={max} step={step}
        onFocus={onFocus}
        onChange={(e) => { const n = Number(e.target.value); if (Number.isFinite(n)) onChange(n); }}
        aria-label={label}
      />
      {suffix && <span className="text-[10px] text-base-content/40">{suffix}</span>}
    </label>
  );
}

/**
 * Right-hand properties panel for the canvas editor. What it shows follows the
 * selection: one element → its content editor + position, layer order, opacity,
 * animation; several → layer/duplicate/delete; nothing → page + section settings.
 * Everything writes straight into the canvas store (same undo + collab paths as
 * the toolbar/keyboard).
 */
export function CanvasInspector({ persistCanvasMeta }: Props) {
  const sections = useCanvasStore(s => s.sections);
  const selectedIds = useCanvasStore(s => s.selectedIds);
  const activeSectionId = useCanvasStore(s => s.activeSectionId);
  const bp = useCanvasStore(s => s.activeBreakpoint);
  const pageType = useCanvasStore(s => s.pageType);
  const fit = useCanvasStore(s => s.fit);
  const width = useCanvasStore(s => s.width);
  const mobileWidth = useCanvasStore(s => s.mobileWidth);
  const {
    updateElementData, updateElementLayout, updateElement, pushSnapshot,
    deleteElements, duplicateElements,
    setElementPin, setElementAnim, clearMobileOverride, clearSelection,
    updateSectionSettings, setPageType, setFit, setWidth, setMobileWidth,
  } = useCanvasStore.getState();

  const section = selectedIds.length
    ? sections.find(s => s.elements.some(e => e.id === selectedIds[0]))
    : sections.find(s => s.id === activeSectionId);
  const el = selectedIds.length === 1 ? section?.elements.find(e => e.id === selectedIds[0]) : undefined;

  // ── several elements ───────────────────────────────────────────────────────
  if (selectedIds.length > 1) {
    return (
      <aside className="w-72 shrink-0 border-l border-base-200 bg-base-100 flex flex-col overflow-y-auto" data-testid="canvas-inspector">
        <div className="flex items-center justify-between p-3 border-b border-base-300/20">
          <h3 className="text-[12px] font-medium text-base-content/80">{selectedIds.length} elements</h3>
          <button className="btn btn-ghost btn-xs btn-square" onClick={clearSelection} aria-label="Deselect"><X size={13} /></button>
        </div>
        <div className="p-3">
          <LayerButtons ids={selectedIds} />
          <div className="flex gap-1 mt-3">
            <button className="btn btn-xs btn-ghost gap-1 flex-1" onClick={() => duplicateElements(selectedIds)}><Copy size={12} /> Duplicate</button>
            <button className="btn btn-xs btn-ghost text-error gap-1 flex-1" onClick={() => deleteElements(selectedIds)}><Trash2 size={12} /> Delete</button>
          </div>
        </div>
      </aside>
    );
  }

  // ── one element ────────────────────────────────────────────────────────────
  if (el && section) {
    const reg = blockRegistry.get(el.blockType);
    const L = effectiveLayout(el, bp);
    const block: BlockData = {
      id: el.id, type: el.blockType, data: el.data, children: [], order: 0, style: el.style,
      ...(el.animation ? { animation: el.animation } : {}),
      ...(el.responsive ? { responsive: el.responsive } : {}),
      ...(el.advanced ? { advanced: el.advanced } : {}),
    } as BlockData;
    const setL = (patch: Parameters<typeof updateElementLayout>[1]) => updateElementLayout(el.id, patch, bp);
    // Shared block style (published by BlockStyleResolver, previewed via buildBlockWrapperStyle).
    const style = (el.style ?? {}) as BlockStyleProps;
    const setStyle = (section: keyof BlockStyleProps, value: unknown) => updateElementData(el.id, { __style: { [section]: value } });
    const responsive = el.responsive as ResponsiveOverrides | undefined;
    const setResponsive = (v: ResponsiveOverrides) => updateElementData(el.id, { __responsive: v });
    const hasMobileOverride = !!el.bp?.mobile && Object.keys(el.bp.mobile).length > 0;
    const hiddenOnPhone = !!el.bp?.mobile?.hidden;

    return (
      <aside className="w-72 shrink-0 border-l border-base-200 bg-base-100 flex flex-col overflow-hidden" data-testid="canvas-inspector">
        <div className="flex items-center justify-between p-3 border-b border-base-300/20">
          <div className="flex items-center gap-2 min-w-0">
            <BlockIcon icon={reg?.definition.icon ?? 'Box'} size={14} className="text-primary/60 shrink-0" />
            <h3 className="text-[12px] font-medium text-base-content/80 truncate">{reg?.definition.label ?? el.blockType}</h3>
          </div>
          <div className="flex items-center gap-0.5">
            <button className="btn btn-ghost btn-xs btn-square" title={el.locked ? 'Unlock' : 'Lock position'} aria-label={el.locked ? 'Unlock' : 'Lock position'}
              onClick={() => { pushSnapshot(); updateElement(el.id, { locked: !el.locked }); }}>
              {el.locked ? <Lock size={13} /> : <Unlock size={13} />}
            </button>
            <button className="btn btn-ghost btn-xs btn-square" title="Duplicate (Ctrl+D)" aria-label="Duplicate" onClick={() => duplicateElements([el.id])}><Copy size={13} /></button>
            <button className="btn btn-ghost btn-xs btn-square text-error" title="Delete (Del)" aria-label="Delete" onClick={() => deleteElements([el.id])}><Trash2 size={13} /></button>
            <button className="btn btn-ghost btn-xs btn-square" onClick={clearSelection} aria-label="Deselect"><X size={13} /></button>
          </div>
        </div>

        <div className="flex-1 overflow-y-auto px-3 pb-6">
          <Group title="Content">
            {reg ? (
              <reg.Editor block={block} isSelected onUpdate={(data) => updateElementData(el.id, data)} onSelect={() => {}} />
            ) : (
              <p className="text-[11px] text-base-content/40">Unknown block type: {el.blockType}</p>
            )}
          </Group>

          <Group title="Layer & opacity">
            <LayerButtons ids={[el.id]} />
            <label className="flex items-center gap-2 text-[11px] text-base-content/60 pt-1">
              <span className="w-12 shrink-0">Opacity</span>
              <input
                type="range" min={0} max={100} step={1}
                className="range range-xs flex-1"
                value={Math.round(L.opacity * 100)}
                onPointerDown={pushSnapshot}
                onKeyDown={(e) => { if (e.key.startsWith('Arrow')) pushSnapshot(); }}
                onChange={(e) => setL({ opacity: Number(e.target.value) / 100 })}
                aria-label="Opacity"
              />
              <span className="w-9 text-right tabular-nums">{Math.round(L.opacity * 100)}%</span>
            </label>
          </Group>

          <Group title="Background, border & shadow" defaultOpen={false}>
            <VisualPanel value={style.visual || {}} onChange={(v) => setStyle('visual', v)} hideOpacity />
          </Group>

          <Group title="Spacing" defaultOpen={false}>
            <SpacingPanel value={style.spacing || {}} onChange={(v) => setStyle('spacing', v)} style={style} responsive={responsive} onResponsiveChange={setResponsive} />
          </Group>

          <Group title="Typography" defaultOpen={false}>
            <TypographyPanel value={style.typography || {}} onChange={(v) => setStyle('typography', v)} style={style} responsive={responsive} onResponsiveChange={setResponsive} />
          </Group>

          <Group title="Position & size">
            <div className="grid grid-cols-2 gap-2">
              <Num label="X" value={L.x} onFocus={pushSnapshot} onChange={(v) => setL({ x: v })} />
              <Num label="Y" value={L.y} onFocus={pushSnapshot} onChange={(v) => setL({ y: v })} />
              <Num label="W" value={L.width} min={20} onFocus={pushSnapshot} onChange={(v) => setL({ width: Math.max(20, v) })} />
              <Num label="H" value={L.height} min={20} onFocus={pushSnapshot} onChange={(v) => setL({ height: Math.max(20, v) })} />
              <Num label="↻" value={L.rotation} min={-360} max={360} suffix="°" onFocus={pushSnapshot} onChange={(v) => setL({ rotation: v })} />
            </div>
            {section.settings.fluid && (
              <div className="flex items-center gap-1 text-[11px] text-base-content/60 pt-1">
                <span className="w-12 shrink-0" title="Which edge the element holds as the section flexes">Pin</span>
                {([['left', 'L'], ['center', 'C'], ['right', 'R'], ['stretch', '↔']] as [PinX, string][]).map(([p, label]) => (
                  <button key={p} className={`btn btn-xs ${(el.pinX ?? 'left') === p ? 'btn-primary' : 'btn-ghost'}`} title={`Pin ${p}`} onClick={() => setElementPin(el.id, p)}>{label}</button>
                ))}
              </div>
            )}
          </Group>

          <Group title="Phone">
            <label className="flex items-center gap-2 text-[11px] text-base-content/60">
              <input type="checkbox" className="checkbox checkbox-xs" checked={hiddenOnPhone}
                onChange={(e) => { pushSnapshot(); updateElementLayout(el.id, { hidden: e.target.checked }, 'mobile'); }} />
              Hide on phones
            </label>
            {bp === 'mobile' ? (
              <p className="text-[10px] text-base-content/40">You are editing the phone layout. Moves and resizes here only affect phones.</p>
            ) : hasMobileOverride ? (
              <p className="text-[10px] text-base-content/40">Has its own phone position (switch to the phone view to adjust).</p>
            ) : (
              <p className="text-[10px] text-base-content/40">Uses the desktop position on phones.</p>
            )}
            {hasMobileOverride && (
              <button className="btn btn-xs btn-ghost" onClick={() => clearMobileOverride(el.id)}>Reset phone layout to desktop</button>
            )}
          </Group>

          <Group title="Animation" defaultOpen={false}>
            <label className="flex items-center gap-2 text-[11px] text-base-content/60">
              <span className="w-12 shrink-0">On scroll</span>
              <select className="select select-xs select-bordered flex-1" value={el.anim?.type ?? 'none'}
                onChange={(e) => setElementAnim(el.id, { ...el.anim, type: e.target.value as CanvasAnim['type'] })} aria-label="Scroll-in animation">
                {ANIMS.map(t => <option key={t} value={t}>{t}</option>)}
              </select>
            </label>
            {el.anim && el.anim.type !== 'none' && (
              <div className="grid grid-cols-2 gap-2">
                <label className="flex items-center gap-1 text-[11px] text-base-content/60">
                  <span className="shrink-0">Delay</span>
                  <input type="number" className="input input-xs input-bordered w-full min-w-0" min={0} max={5000} step={50} value={el.anim.delay ?? 0}
                    onChange={(e) => setElementAnim(el.id, { ...el.anim!, delay: Number(e.target.value) })} aria-label="Animation delay (ms)" />
                </label>
                <label className="flex items-center gap-1 text-[11px] text-base-content/60">
                  <span className="shrink-0">Time</span>
                  <input type="number" className="input input-xs input-bordered w-full min-w-0" min={50} max={3000} step={50} value={el.anim.duration ?? 600}
                    onChange={(e) => setElementAnim(el.id, { ...el.anim!, duration: Number(e.target.value) })} aria-label="Animation duration (ms)" />
                </label>
              </div>
            )}
          </Group>
        </div>
      </aside>
    );
  }

  // ── nothing selected: page + active section ────────────────────────────────
  return (
    <aside className="w-72 shrink-0 border-l border-base-200 bg-base-100 flex flex-col overflow-y-auto" data-testid="canvas-inspector">
      <div className="flex flex-col items-center text-center px-6 pt-6 pb-4 gap-1.5">
        <MousePointerClick size={24} className="text-base-content/15" />
        <p className="text-[12px] text-base-content/50 font-medium">Click a block to edit it</p>
        <p className="text-[10px] text-base-content/30">Drag to move · click again to type · double-click for images</p>
      </div>
      <div className="px-3 pb-6">
        <Group title="Page">
          <label className="flex items-center gap-2 text-[11px] text-base-content/60">
            <span className="w-14 shrink-0">Type</span>
            <select className="select select-xs select-bordered flex-1" value={pageType}
              onChange={(e) => { const t = e.target.value as CanvasPageType; setPageType(t); persistCanvasMeta({ page_type: t }); }} aria-label="Page type">
              <option value="website">Website (sections scroll)</option>
              <option value="single">Single (one canvas)</option>
            </select>
          </label>
          <label className="flex items-center gap-2 text-[11px] text-base-content/60" title="How the canvas meets the visitor's screen">
            <span className="w-14 shrink-0">Screen</span>
            <select className="select select-xs select-bordered flex-1" value={fit}
              onChange={(e) => { const f = e.target.value as CanvasFit; setFit(f); persistCanvasMeta({ fit: f }); }} aria-label="Screen fit">
              <option value="scale">Scale to screen width</option>
              <option value="center">Centred column (fixed width)</option>
            </select>
          </label>
          <label className="flex items-center gap-2 text-[11px] text-base-content/60">
            <span className="w-14 shrink-0">Width</span>
            <input type="number" className="input input-xs input-bordered flex-1 min-w-0" value={width} min={CANVAS_W_MIN} max={CANVAS_W_MAX}
              onChange={(e) => setWidth(Number(e.target.value))} onBlur={(e) => persistCanvasMeta({ width: Number(e.target.value) })} aria-label="Design width (px)" />
            <span className="text-[10px] text-base-content/40">px</span>
          </label>
          <label className="flex items-center gap-2 text-[11px] text-base-content/60">
            <span className="w-14 shrink-0">Phone</span>
            <input type="number" className="input input-xs input-bordered flex-1 min-w-0" value={mobileWidth} min={MOBILE_W_MIN} max={MOBILE_W_MAX}
              onChange={(e) => setMobileWidth(Number(e.target.value))} onBlur={(e) => persistCanvasMeta({ mobile_width: Number(e.target.value) })} aria-label="Phone canvas width (px)" />
            <span className="text-[10px] text-base-content/40">px</span>
          </label>
        </Group>

        {section && (
          <Group title="Section">
            <label className="flex items-center gap-2 text-[11px] text-base-content/60">
              <span className="w-14 shrink-0">Height</span>
              <input type="number" className="input input-xs input-bordered flex-1 min-w-0" placeholder="auto"
                value={section.settings.height === 'auto' ? '' : section.settings.height}
                onChange={(e) => updateSectionSettings(section.id, { height: e.target.value === '' ? 'auto' : Number(e.target.value) })} aria-label="Section height" />
              <button className={`btn btn-xs ${section.settings.height === 'auto' ? 'btn-primary' : 'btn-ghost'}`}
                onClick={() => updateSectionSettings(section.id, { height: section.settings.height === 'auto' ? 480 : 'auto' })}>auto</button>
            </label>
            <label className="flex items-center gap-2 text-[11px] text-base-content/60">
              <span className="w-14 shrink-0">Background</span>
              <input type="color" className="w-7 h-5 rounded border-0 bg-transparent p-0" value={section.settings.background || '#ffffff'}
                onChange={(e) => updateSectionSettings(section.id, { background: e.target.value })} aria-label="Section background" />
              {section.settings.background && (
                <button className="btn btn-xs btn-ghost" onClick={() => updateSectionSettings(section.id, { background: '' })}>clear</button>
              )}
            </label>
            <label className="flex items-center gap-2 text-[11px] text-base-content/60">
              <input type="checkbox" className="checkbox checkbox-xs" checked={section.settings.bleed} onChange={(e) => updateSectionSettings(section.id, { bleed: e.target.checked })} />
              Full width background (bleed)
            </label>
            <label className="flex items-center gap-2 text-[11px] text-base-content/60" title="Flex with the viewport (elements hold their pin anchors) instead of stacking">
              <input type="checkbox" className="checkbox checkbox-xs" checked={!!section.settings.fluid} onChange={(e) => updateSectionSettings(section.id, { fluid: e.target.checked })} />
              Fluid (stretch with the window)
            </label>
          </Group>
        )}
      </div>
    </aside>
  );
}

function LayerButtons({ ids }: { ids: string[] }) {
  const { bringToFront, sendToBack, bringForward, sendBackward } = useCanvasStore.getState();
  return (
    <div className="grid grid-cols-4 gap-1" role="group" aria-label="Layer order">
      <button className="btn btn-xs btn-ghost flex-col h-auto py-1 gap-0.5" title="Send to back (Ctrl+Shift+[)" onClick={() => sendToBack(ids)}>
        <ChevronsDown size={13} /><span className="text-[9px] leading-none">Back</span>
      </button>
      <button className="btn btn-xs btn-ghost flex-col h-auto py-1 gap-0.5" title="Send backward (Ctrl+[)" onClick={() => sendBackward(ids)}>
        <ArrowDown size={13} /><span className="text-[9px] leading-none">Down</span>
      </button>
      <button className="btn btn-xs btn-ghost flex-col h-auto py-1 gap-0.5" title="Bring forward (Ctrl+])" onClick={() => bringForward(ids)}>
        <ArrowUp size={13} /><span className="text-[9px] leading-none">Up</span>
      </button>
      <button className="btn btn-xs btn-ghost flex-col h-auto py-1 gap-0.5" title="Bring to front (Ctrl+Shift+])" onClick={() => bringToFront(ids)}>
        <ChevronsUp size={13} /><span className="text-[9px] leading-none">Front</span>
      </button>
    </div>
  );
}
