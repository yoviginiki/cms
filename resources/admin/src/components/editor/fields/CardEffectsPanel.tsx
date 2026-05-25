/**
 * BLOCK-EFFECTS-1 — Reusable Card/Image Effects Panel.
 * Drop into any block editor that renders cards or images.
 */
import type { CardEffects, HoverPreset, FilterPreset, RevealMode } from '@/lib/blockEffects';
import { HOVER_PRESETS, FILTER_PRESETS } from '@/lib/blockEffects';

interface Props {
  value: CardEffects;
  onChange: (v: CardEffects) => void;
  showHover?: boolean;
  showFilter?: boolean;
  showOverlay?: boolean;
  showReveal?: boolean;
}

const HOVER_OPTIONS: Array<{ value: HoverPreset; label: string; desc: string }> = [
  { value: 'none', label: 'None', desc: 'No hover effect' },
  { value: 'lift', label: 'Lift', desc: 'Card moves up with shadow' },
  { value: 'scale', label: 'Scale', desc: 'Card grows slightly' },
  { value: 'lift-scale', label: 'Lift + Scale', desc: 'Lift and grow' },
  { value: 'soft-pop', label: 'Soft Pop', desc: 'Subtle lift with gentle shadow' },
  { value: 'strong-pop', label: 'Strong Pop', desc: 'Dramatic lift and scale' },
];

const FILTER_OPTIONS: Array<{ value: FilterPreset; label: string }> = [
  { value: 'none', label: 'None' },
  { value: 'grayscale', label: 'Black & White' },
  { value: 'sepia', label: 'Sepia' },
  { value: 'muted', label: 'Muted' },
  { value: 'high-contrast', label: 'High Contrast' },
  { value: 'custom', label: 'Custom' },
];

const REVEAL_OPTIONS: Array<{ value: RevealMode; label: string }> = [
  { value: 'none', label: 'None' },
  { value: 'fade', label: 'Fade to original' },
  // Directional reveal modes planned for future slice:
  // reveal-left, reveal-right, reveal-top, reveal-bottom, circle, diagonal
];

export function CardEffectsPanel({ value, onChange, showHover = true, showFilter = true, showOverlay = true, showReveal = true }: Props) {
  const update = (patch: Partial<CardEffects>) => onChange({ ...value, ...patch });
  const updateHover = (patch: Partial<NonNullable<CardEffects['hover']>>) =>
    update({ hover: { ...value.hover, ...patch } });
  const updateFilter = (patch: Partial<NonNullable<CardEffects['imageFilter']>>) =>
    update({ imageFilter: { ...value.imageFilter, ...patch } });
  const updateOverlay = (patch: Partial<NonNullable<CardEffects['overlay']>>) =>
    update({ overlay: { ...value.overlay, ...patch } });
  const updateReveal = (patch: Partial<NonNullable<CardEffects['imageHoverReveal']>>) =>
    update({ imageHoverReveal: { ...value.imageHoverReveal, ...patch } });

  return (
    <div className="space-y-3">
      {/* Master toggle */}
      <label className="flex items-center justify-between cursor-pointer">
        <span className="text-[11px] text-base-content/50 font-medium">Enable Card Effects</span>
        <input type="checkbox" className="toggle toggle-xs toggle-primary"
          checked={!!value.enabled} onChange={(e) => update({ enabled: e.target.checked })} />
      </label>

      {!value.enabled && (
        <p className="text-[9px] text-base-content/25">Enable to add hover animations, image filters, and overlays to cards.</p>
      )}

      {value.enabled && (
        <>
          {/* ─── Hover Effect ─── */}
          {showHover && (
            <div className="border-t border-base-300/20 pt-3">
              <label className="flex items-center justify-between cursor-pointer mb-2">
                <span className="text-[10px] text-base-content/40 uppercase tracking-wider font-medium">Hover Effect</span>
                <input type="checkbox" className="toggle toggle-xs toggle-primary"
                  checked={!!value.hover?.enabled} onChange={(e) => updateHover({ enabled: e.target.checked })} />
              </label>

              {value.hover?.enabled && (
                <div className="space-y-2">
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-1 block">Preset</label>
                    <select className="select select-bordered select-xs w-full"
                      value={value.hover.preset || 'soft-pop'}
                      onChange={(e) => {
                        const preset = e.target.value as HoverPreset;
                        const defaults = HOVER_PRESETS[preset];
                        updateHover({ preset, scale: defaults.scale, translateY: defaults.translateY, shadow: defaults.shadow as any });
                      }}>
                      {HOVER_OPTIONS.map(o => (
                        <option key={o.value} value={o.value}>{o.label} — {o.desc}</option>
                      ))}
                    </select>
                  </div>

                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="text-[10px] text-base-content/40 mb-0.5 block">Scale</label>
                      <input type="range" className="range range-xs range-primary w-full" min={1} max={1.15} step={0.01}
                        value={value.hover.scale ?? HOVER_PRESETS[value.hover.preset || 'soft-pop'].scale}
                        onChange={(e) => updateHover({ scale: Number(e.target.value) })} />
                      <span className="text-[9px] text-base-content/30">{(value.hover.scale ?? 1).toFixed(2)}x</span>
                    </div>
                    <div>
                      <label className="text-[10px] text-base-content/40 mb-0.5 block">Lift</label>
                      <input type="range" className="range range-xs range-primary w-full" min={-20} max={0} step={1}
                        value={value.hover.translateY ?? HOVER_PRESETS[value.hover.preset || 'soft-pop'].translateY}
                        onChange={(e) => updateHover({ translateY: Number(e.target.value) })} />
                      <span className="text-[9px] text-base-content/30">{value.hover.translateY ?? 0}px</span>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="text-[10px] text-base-content/40 mb-0.5 block">Shadow</label>
                      <select className="select select-bordered select-xs w-full"
                        value={value.hover.shadow || 'soft'}
                        onChange={(e) => updateHover({ shadow: e.target.value as any })}>
                        <option value="none">None</option>
                        <option value="soft">Soft</option>
                        <option value="medium">Medium</option>
                        <option value="strong">Strong</option>
                      </select>
                    </div>
                    <div>
                      <label className="text-[10px] text-base-content/40 mb-0.5 block">Speed</label>
                      <select className="select select-bordered select-xs w-full"
                        value={value.hover.duration ?? 300}
                        onChange={(e) => updateHover({ duration: Number(e.target.value) })}>
                        <option value={150}>Fast (150ms)</option>
                        <option value={300}>Normal (300ms)</option>
                        <option value={500}>Slow (500ms)</option>
                      </select>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ─── Image Filter ─── */}
          {showFilter && (
            <div className="border-t border-base-300/20 pt-3">
              <label className="flex items-center justify-between cursor-pointer mb-2">
                <span className="text-[10px] text-base-content/40 uppercase tracking-wider font-medium">Image Filter</span>
                <input type="checkbox" className="toggle toggle-xs toggle-primary"
                  checked={!!value.imageFilter?.enabled} onChange={(e) => updateFilter({ enabled: e.target.checked })} />
              </label>

              {value.imageFilter?.enabled && (
                <div className="space-y-2">
                  <select className="select select-bordered select-xs w-full"
                    value={value.imageFilter.preset || 'none'}
                    onChange={(e) => {
                      const preset = e.target.value as FilterPreset;
                      const defaults = FILTER_PRESETS[preset];
                      updateFilter({ preset, ...defaults });
                    }}>
                    {FILTER_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                  </select>

                  {value.imageFilter.preset === 'custom' && (
                    <div className="space-y-1.5 pl-1">
                      <FilterSlider label="Grayscale" value={value.imageFilter.grayscale ?? 0} min={0} max={100} onChange={(v) => updateFilter({ grayscale: v })} />
                      <FilterSlider label="Sepia" value={value.imageFilter.sepia ?? 0} min={0} max={100} onChange={(v) => updateFilter({ sepia: v })} />
                      <FilterSlider label="Brightness" value={value.imageFilter.brightness ?? 100} min={50} max={200} onChange={(v) => updateFilter({ brightness: v })} />
                      <FilterSlider label="Contrast" value={value.imageFilter.contrast ?? 100} min={50} max={200} onChange={(v) => updateFilter({ contrast: v })} />
                      <FilterSlider label="Saturation" value={value.imageFilter.saturation ?? 100} min={0} max={200} onChange={(v) => updateFilter({ saturation: v })} />
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* ─── Image Hover Reveal ─── */}
          {showReveal && value.imageFilter?.enabled && (
            <div className="border-t border-base-300/20 pt-3">
              <label className="flex items-center justify-between cursor-pointer mb-2">
                <span className="text-[10px] text-base-content/40 uppercase tracking-wider font-medium">Image Hover Reveal</span>
                <input type="checkbox" className="toggle toggle-xs toggle-primary"
                  checked={!!value.imageHoverReveal?.enabled} onChange={(e) => updateReveal({ enabled: e.target.checked })} />
              </label>

              {value.imageHoverReveal?.enabled && (
                <div className="space-y-2">
                  <p className="text-[9px] text-base-content/25">Shows the filtered image normally and reveals the original image on hover.</p>
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-1 block">Reveal Mode</label>
                    <select className="select select-bordered select-xs w-full"
                      value={value.imageHoverReveal.mode || 'fade'}
                      onChange={(e) => updateReveal({ mode: e.target.value as RevealMode })}>
                      {REVEAL_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                    </select>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="text-[10px] text-base-content/40 mb-0.5 block">Duration</label>
                      <input type="range" className="range range-xs range-primary w-full" min={150} max={1500} step={50}
                        value={value.imageHoverReveal.duration ?? 500}
                        onChange={(e) => updateReveal({ duration: Number(e.target.value) })} />
                      <span className="text-[9px] text-base-content/30">{value.imageHoverReveal.duration ?? 500}ms</span>
                    </div>
                    <div>
                      <label className="text-[10px] text-base-content/40 mb-0.5 block">Easing</label>
                      <select className="select select-bordered select-xs w-full"
                        value={value.imageHoverReveal.easing || 'ease-out'}
                        onChange={(e) => updateReveal({ easing: e.target.value as any })}>
                        <option value="ease">Ease</option>
                        <option value="ease-out">Ease out</option>
                        <option value="ease-in-out">Ease in-out</option>
                      </select>
                    </div>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* ─── Color Overlay ─── */}
          {showOverlay && (
            <div className="border-t border-base-300/20 pt-3">
              <label className="flex items-center justify-between cursor-pointer mb-2">
                <span className="text-[10px] text-base-content/40 uppercase tracking-wider font-medium">Color Overlay</span>
                <input type="checkbox" className="toggle toggle-xs toggle-primary"
                  checked={!!value.overlay?.enabled} onChange={(e) => updateOverlay({ enabled: e.target.checked })} />
              </label>

              {value.overlay?.enabled && (
                <div className="space-y-2">
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-0.5 block">Color</label>
                    <div className="flex gap-1">
                      <input type="color" className="w-7 h-7 rounded cursor-pointer border border-base-300/30"
                        value={value.overlay.color || '#000000'}
                        onChange={(e) => updateOverlay({ color: e.target.value })} />
                      <input type="text" className="input input-bordered input-xs flex-1 font-mono text-[10px]"
                        value={value.overlay.color || '#000000'}
                        onChange={(e) => updateOverlay({ color: e.target.value })} />
                    </div>
                  </div>
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-0.5 block">Opacity ({value.overlay.opacity ?? 30}%)</label>
                    <input type="range" className="range range-xs w-full" min={0} max={100} step={5}
                      value={value.overlay.opacity ?? 30}
                      onChange={(e) => updateOverlay({ opacity: Number(e.target.value) })} />
                  </div>
                  <div>
                    <label className="text-[10px] text-base-content/40 mb-0.5 block">Blend Mode</label>
                    <select className="select select-bordered select-xs w-full"
                      value={value.overlay.blendMode || 'normal'}
                      onChange={(e) => updateOverlay({ blendMode: e.target.value as any })}>
                      <option value="normal">Normal</option>
                      <option value="multiply">Multiply</option>
                      <option value="screen">Screen</option>
                      <option value="overlay">Overlay</option>
                      <option value="soft-light">Soft Light</option>
                    </select>
                  </div>
                </div>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}

function FilterSlider({ label, value, min, max, onChange }: { label: string; value: number; min: number; max: number; onChange: (v: number) => void }) {
  return (
    <div className="flex items-center gap-2">
      <label className="text-[9px] text-base-content/30 w-16 shrink-0">{label}</label>
      <input type="range" className="range range-xs flex-1" min={min} max={max} step={1} value={value} onChange={(e) => onChange(Number(e.target.value))} />
      <span className="text-[9px] text-base-content/30 w-8 text-right">{value}</span>
    </div>
  );
}
