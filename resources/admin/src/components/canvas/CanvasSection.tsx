import { useState } from 'react';
import { ChevronUp, ChevronDown, Trash2, Plus } from 'lucide-react';
import type { CanvasSection as Section } from '@/types/canvas';
import { effectiveLayout } from '@/types/canvas';
import { useCanvasStore } from '@/stores/canvasStore';
import { useCanvasSelection } from './useCanvasSelection';
import { CanvasElement } from './CanvasElement';
import { CanvasPalette } from './CanvasPalette';

interface Props {
  section: Section;
  width: number;      // design width (px)
  zoom: number;
  isActive: boolean;
  canMoveUp: boolean;
  canMoveDown: boolean;
  singleMode: boolean;
}

export function CanvasSection({ section, width, zoom, isActive, canMoveUp, canMoveDown, singleMode }: Props) {
  const selectedIds = useCanvasStore(s => s.selectedIds);
  const bp = useCanvasStore(s => s.activeBreakpoint);
  const mobileWidth = useCanvasStore(s => s.mobileWidth);
  const { updateSectionSettings, deleteSection, moveSection, addElement, clearSelection, setActiveSection } = useCanvasStore();
  const [paletteOpen, setPaletteOpen] = useState(false);

  const effWidth = bp === 'mobile' ? mobileWidth : width;
  const laid = section.elements.map(el => ({ el, eff: effectiveLayout(el, bp) })).filter(x => !x.eff.hidden);
  const maxBottom = laid.reduce((m, { eff }) => Math.max(m, eff.y + eff.height), 0);
  const displayHeight = section.settings.height === 'auto' ? Math.max(200, maxBottom) : section.settings.height;

  const { guides, onElementPointerDown, onResizePointerDown, onRotatePointerDown } =
    useCanvasSelection(section.id, effWidth, displayHeight);

  const addBlock = (blockType: string) => {
    // drop near the top-left, offset so successive adds don't fully overlap
    const n = section.elements.length;
    addElement(section.id, blockType, 40 + (n % 5) * 24, 40 + (n % 5) * 24, 260, 120);
  };

  const sorted = [...laid].sort((a, b) => a.eff.zIndex - b.eff.zIndex);

  return (
    <div className={`cv-section-wrap border-b border-base-200 ${isActive ? 'ring-1 ring-primary/40' : ''}`}>
      {/* controls bar */}
      <div className="flex items-center gap-2 px-3 py-1.5 bg-base-200/60 text-xs">
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
            <button className="btn btn-xs btn-ghost" disabled={!canMoveUp} onClick={() => moveSection(section.id, 'up')} title="Move up"><ChevronUp size={14} /></button>
            <button className="btn btn-xs btn-ghost" disabled={!canMoveDown} onClick={() => moveSection(section.id, 'down')} title="Move down"><ChevronDown size={14} /></button>
            <button className="btn btn-xs btn-ghost text-error" onClick={() => deleteSection(section.id)} title="Delete section"><Trash2 size={14} /></button>
          </>
        )}
      </div>

      {/* the canvas */}
      <div className="flex justify-center bg-base-300/30 py-4 overflow-hidden">
        <div style={{ width: effWidth * zoom, height: displayHeight * zoom }}>
          <div
            className="cv-canvas relative shadow-sm"
            onPointerDown={() => { clearSelection(); setActiveSection(section.id); }}
            style={{
              width: effWidth, height: displayHeight,
              transform: `scale(${zoom})`, transformOrigin: 'top left',
              background: section.settings.background || '#ffffff',
              backgroundImage: 'linear-gradient(rgba(0,0,0,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(0,0,0,0.04) 1px, transparent 1px)',
              backgroundSize: `${effWidth / 12}px ${effWidth / 12}px`,
              outline: bp === 'mobile' ? '2px solid rgba(37,99,235,0.35)' : undefined,
            }}
          >
            {sorted.map(({ el, eff }) => (
              <CanvasElement
                key={el.id}
                el={el}
                eff={eff}
                selected={selectedIds.includes(el.id)}
                zoom={zoom}
                onPointerDown={onElementPointerDown}
                onResizeDown={onResizePointerDown}
                onRotateDown={onRotatePointerDown}
              />
            ))}
            {/* smart guides */}
            {guides.map((g, i) => (
              <div
                key={i}
                style={g.type === 'vertical'
                  ? { position: 'absolute', left: g.position, top: 0, bottom: 0, width: 1, background: '#ec4899', pointerEvents: 'none' }
                  : { position: 'absolute', top: g.position, left: 0, right: 0, height: 1, background: '#ec4899', pointerEvents: 'none' }}
              />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
