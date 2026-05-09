import React, { useState } from 'react';
import { ChevronDown, ChevronUp, X } from 'lucide-react';
import { AssetField } from '@/components/ui/AssetPicker';

export interface BackgroundData {
  bg_type?: 'none' | 'color' | 'gradient' | 'image';
  bg_color?: string;
  bg_gradient_type?: 'linear' | 'radial';
  bg_gradient_angle?: number;
  bg_gradient_stops?: Array<{ color: string; position: number }>;
  bg_image?: string;
  bg_image_size?: 'cover' | 'contain' | 'auto' | 'custom';
  bg_image_position?: string;
  bg_image_repeat?: 'no-repeat' | 'repeat' | 'repeat-x' | 'repeat-y';
  bg_overlay_color?: string;
  bg_overlay_opacity?: number;
  bg_scroll_effect?: 'none' | 'fixed' | 'parallax' | 'zoom';
  bg_parallax_speed?: number;
}

const DEFAULTS: BackgroundData = {
  bg_type: 'none',
  bg_color: '',
  bg_gradient_type: 'linear',
  bg_gradient_angle: 180,
  bg_gradient_stops: [
    { color: '#3b82f6', position: 0 },
    { color: '#8b5cf6', position: 100 },
  ],
  bg_image: '',
  bg_image_size: 'cover',
  bg_image_position: 'center center',
  bg_image_repeat: 'no-repeat',
  bg_overlay_color: '#000000',
  bg_overlay_opacity: 0,
  bg_scroll_effect: 'none',
  bg_parallax_speed: 0.5,
};

interface Props {
  data: Record<string, unknown>;
  onChange: (updates: Record<string, unknown>) => void;
}

export default function BackgroundEditor({ data, onChange }: Props) {
  const bg: BackgroundData = { ...DEFAULTS, ...(data as any) };
  const [expanded, setExpanded] = useState(bg.bg_type !== 'none');

  const update = (field: string, value: unknown) => {
    onChange({ ...data, [field]: value });
  };

  const gradientCss = buildGradientCss(bg);

  return (
    <div className="border border-base-300/30 rounded-lg overflow-hidden">
      {/* Header with preview */}
      <button onClick={() => setExpanded(!expanded)}
        className="flex items-center gap-2 w-full px-3 py-2 text-left hover:bg-base-200/30 transition-colors">
        <div className="w-6 h-6 rounded border border-base-300/30 shrink-0" style={buildPreviewStyle(bg)} />
        <span className="text-[11px] font-medium text-base-content/60 flex-1">Background</span>
        <span className="text-[10px] text-base-content/30">{bg.bg_type === 'none' ? 'None' : bg.bg_type}</span>
        {expanded ? <ChevronUp size={12} className="text-base-content/30" /> : <ChevronDown size={12} className="text-base-content/30" />}
      </button>

      {expanded && (
        <div className="px-3 pb-3 space-y-3 border-t border-base-300/20 pt-3">
          {/* Type selector */}
          <div className="flex rounded-lg overflow-hidden border border-base-300/30">
            {(['none', 'color', 'gradient', 'image'] as const).map(t => (
              <button key={t} onClick={() => update('bg_type', t)}
                className={`flex-1 px-2 py-1.5 text-[10px] font-medium transition-colors ${
                  bg.bg_type === t ? 'bg-primary text-primary-content' : 'text-base-content/40 hover:text-base-content/60'
                }`}>
                {t === 'none' ? 'None' : t === 'color' ? 'Color' : t === 'gradient' ? 'Gradient' : 'Image'}
              </button>
            ))}
          </div>

          {/* ─── Color picker ─── */}
          {bg.bg_type === 'color' && (
            <div>
              <label className="text-[10px] text-base-content/40 mb-1 block">Background Color</label>
              <div className="flex gap-2">
                <input type="color" value={bg.bg_color || '#ffffff'}
                  onChange={e => update('bg_color', e.target.value)}
                  className="w-10 h-8 rounded border border-base-300/30 cursor-pointer" />
                <input type="text" value={bg.bg_color || ''}
                  onChange={e => update('bg_color', e.target.value)}
                  className="input input-bordered input-xs flex-1 font-mono text-[10px]" placeholder="#hex" />
              </div>
            </div>
          )}

          {/* ─── Gradient builder ─── */}
          {bg.bg_type === 'gradient' && (
            <div className="space-y-2">
              {/* Preview bar */}
              <div className="h-8 rounded-lg border border-base-300/30" style={{ background: gradientCss }} />

              {/* Type + angle */}
              <div className="flex gap-2">
                <div className="flex-1">
                  <label className="text-[10px] text-base-content/40 mb-0.5 block">Type</label>
                  <select value={bg.bg_gradient_type} onChange={e => update('bg_gradient_type', e.target.value)}
                    className="select select-bordered select-xs w-full text-[10px]">
                    <option value="linear">Linear</option>
                    <option value="radial">Radial</option>
                  </select>
                </div>
                {bg.bg_gradient_type === 'linear' && (
                  <div className="flex-1">
                    <label className="text-[10px] text-base-content/40 mb-0.5 block">Angle: {bg.bg_gradient_angle}°</label>
                    <input type="range" min={0} max={360} value={bg.bg_gradient_angle}
                      onChange={e => update('bg_gradient_angle', Number(e.target.value))}
                      className="range range-xs range-primary w-full" />
                  </div>
                )}
              </div>

              {/* Color stops */}
              <div className="space-y-1.5">
                <label className="text-[10px] text-base-content/40 block">Color Stops</label>
                {(bg.bg_gradient_stops || []).map((stop, i) => (
                  <div key={i} className="flex items-center gap-1.5">
                    <input type="color" value={stop.color}
                      onChange={e => {
                        const stops = [...(bg.bg_gradient_stops || [])];
                        stops[i] = { ...stops[i], color: e.target.value };
                        update('bg_gradient_stops', stops);
                      }}
                      className="w-7 h-6 rounded border border-base-300/30 cursor-pointer" />
                    <input type="text" value={stop.color}
                      onChange={e => {
                        const stops = [...(bg.bg_gradient_stops || [])];
                        stops[i] = { ...stops[i], color: e.target.value };
                        update('bg_gradient_stops', stops);
                      }}
                      className="input input-bordered input-xs flex-1 font-mono text-[10px]" />
                    <input type="number" min={0} max={100} value={stop.position}
                      onChange={e => {
                        const stops = [...(bg.bg_gradient_stops || [])];
                        stops[i] = { ...stops[i], position: Number(e.target.value) };
                        update('bg_gradient_stops', stops);
                      }}
                      className="input input-bordered input-xs w-14 text-[10px]" />
                    <span className="text-[9px] text-base-content/30">%</span>
                    {(bg.bg_gradient_stops || []).length > 2 && (
                      <button onClick={() => {
                        const stops = (bg.bg_gradient_stops || []).filter((_, j) => j !== i);
                        update('bg_gradient_stops', stops);
                      }} className="btn btn-ghost btn-xs btn-square"><X size={10} /></button>
                    )}
                  </div>
                ))}
                <button onClick={() => {
                  const stops = [...(bg.bg_gradient_stops || []), { color: '#10b981', position: 50 }];
                  update('bg_gradient_stops', stops);
                }} className="btn btn-ghost btn-xs text-[10px] w-full border border-dashed border-base-300/30">
                  + Add Stop
                </button>
              </div>
            </div>
          )}

          {/* ─── Image ─── */}
          {bg.bg_type === 'image' && (
            <div className="space-y-2">
              <AssetField label="Background Image" value={bg.bg_image || ''} accept="image"
                onChange={(url) => update('bg_image', url)} />

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="text-[10px] text-base-content/40 mb-0.5 block">Size</label>
                  <select value={bg.bg_image_size} onChange={e => update('bg_image_size', e.target.value)}
                    className="select select-bordered select-xs w-full text-[10px]">
                    <option value="cover">Cover (fill)</option>
                    <option value="contain">Contain (fit)</option>
                    <option value="auto">Original</option>
                  </select>
                </div>
                <div>
                  <label className="text-[10px] text-base-content/40 mb-0.5 block">Position</label>
                  <select value={bg.bg_image_position} onChange={e => update('bg_image_position', e.target.value)}
                    className="select select-bordered select-xs w-full text-[10px]">
                    <option value="center center">Center</option>
                    <option value="top center">Top</option>
                    <option value="bottom center">Bottom</option>
                    <option value="left center">Left</option>
                    <option value="right center">Right</option>
                  </select>
                </div>
              </div>

              {/* Overlay */}
              <div>
                <label className="text-[10px] text-base-content/40 mb-0.5 block">
                  Color Overlay: {Math.round((bg.bg_overlay_opacity || 0) * 100)}%
                </label>
                <div className="flex gap-2">
                  <input type="color" value={bg.bg_overlay_color || '#000000'}
                    onChange={e => update('bg_overlay_color', e.target.value)}
                    className="w-7 h-6 rounded border border-base-300/30 cursor-pointer" />
                  <input type="range" min={0} max={1} step={0.05} value={bg.bg_overlay_opacity || 0}
                    onChange={e => update('bg_overlay_opacity', Number(e.target.value))}
                    className="range range-xs range-primary flex-1" />
                </div>
              </div>

              {/* Scroll effects */}
              <div>
                <label className="text-[10px] text-base-content/40 mb-1 block">Scroll Effect</label>
                <div className="grid grid-cols-2 gap-1">
                  {([
                    { value: 'none', label: 'None', desc: 'Normal scroll' },
                    { value: 'fixed', label: 'Fixed', desc: 'Image stays, content scrolls over' },
                    { value: 'parallax', label: 'Parallax', desc: 'Image scrolls slower than content' },
                    { value: 'zoom', label: 'Zoom', desc: 'Image zooms as you scroll' },
                  ] as const).map(fx => (
                    <button key={fx.value} onClick={() => update('bg_scroll_effect', fx.value)}
                      className={`px-2 py-1.5 rounded text-left transition-colors ${
                        bg.bg_scroll_effect === fx.value ? 'bg-primary/10 border border-primary/30' : 'border border-base-300/20 hover:bg-base-200/30'
                      }`}>
                      <span className="text-[10px] font-medium block">{fx.label}</span>
                      <span className="text-[9px] text-base-content/30">{fx.desc}</span>
                    </button>
                  ))}
                </div>
                {bg.bg_scroll_effect === 'parallax' && (
                  <div className="mt-2">
                    <label className="text-[10px] text-base-content/40 mb-0.5 block">Speed: {bg.bg_parallax_speed}</label>
                    <input type="range" min={0.1} max={1} step={0.1} value={bg.bg_parallax_speed || 0.5}
                      onChange={e => update('bg_parallax_speed', Number(e.target.value))}
                      className="range range-xs range-primary w-full" />
                    <div className="flex justify-between text-[9px] text-base-content/20"><span>Slow</span><span>Fast</span></div>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

/** Build the CSS background for preview */
export function buildBackgroundStyle(data: Record<string, unknown>): React.CSSProperties {
  const bg = { ...DEFAULTS, ...(data as any) } as BackgroundData;
  const style: React.CSSProperties = {};

  if (bg.bg_type === 'color' && bg.bg_color) {
    style.backgroundColor = bg.bg_color;
  }

  if (bg.bg_type === 'gradient') {
    style.background = buildGradientCss(bg);
  }

  if (bg.bg_type === 'image' && bg.bg_image) {
    style.backgroundImage = `url(${bg.bg_image})`;
    style.backgroundSize = bg.bg_image_size || 'cover';
    style.backgroundPosition = bg.bg_image_position || 'center center';
    style.backgroundRepeat = bg.bg_image_repeat || 'no-repeat';

    if (bg.bg_scroll_effect === 'fixed') {
      style.backgroundAttachment = 'fixed';
    }
  }

  return style;
}

/** Build overlay div style (for image backgrounds with color overlay) */
export function buildOverlayStyle(data: Record<string, unknown>): React.CSSProperties | null {
  const bg = { ...DEFAULTS, ...(data as any) } as BackgroundData;
  if (bg.bg_type !== 'image' || !bg.bg_overlay_opacity) return null;
  return {
    position: 'absolute',
    inset: 0,
    backgroundColor: bg.bg_overlay_color || '#000',
    opacity: bg.bg_overlay_opacity,
    pointerEvents: 'none' as const,
  };
}

function buildGradientCss(bg: BackgroundData): string {
  const stops = (bg.bg_gradient_stops || []).map(s => `${s.color} ${s.position}%`).join(', ');
  if (bg.bg_gradient_type === 'radial') return `radial-gradient(circle, ${stops})`;
  return `linear-gradient(${bg.bg_gradient_angle || 180}deg, ${stops})`;
}

function buildPreviewStyle(bg: BackgroundData): React.CSSProperties {
  if (bg.bg_type === 'color') return { backgroundColor: bg.bg_color || '#eee' };
  if (bg.bg_type === 'gradient') return { background: buildGradientCss(bg) };
  if (bg.bg_type === 'image') return { backgroundColor: '#666', backgroundImage: bg.bg_image ? `url(${bg.bg_image})` : 'none', backgroundSize: 'cover' };
  return { backgroundColor: 'transparent', border: '1px dashed #ccc' };
}
