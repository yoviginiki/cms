import type { ShadowCustom } from '@/lib/shadowStyles';
import { buildShadowCss } from '@/lib/shadowStyles';

interface ShadowFieldProps {
  label?: string;
  mode: string;
  preset: string;
  custom: ShadowCustom;
  onChangeMode: (mode: 'preset' | 'custom') => void;
  onChangePreset: (preset: string) => void;
  onChangeCustom: (custom: Partial<ShadowCustom>) => void;
  /** Available presets. Defaults to subtle/medium/large/glow. */
  presets?: { value: string; label: string }[];
}

const DEFAULT_PRESETS = [
  { value: '', label: 'None' },
  { value: 'subtle', label: 'Subtle' },
  { value: 'medium', label: 'Medium' },
  { value: 'large', label: 'Large' },
  { value: 'glow', label: 'Glow' },
];

/**
 * Reusable shadow editor with preset and custom modes.
 * Global/BaseBlock component — not Hero-specific.
 * Does not accept raw box-shadow CSS. All values are structured and validated.
 */
export function ShadowField({
  label = 'Shadow',
  mode,
  preset,
  custom,
  onChangeMode,
  onChangePreset,
  onChangeCustom,
  presets = DEFAULT_PRESETS,
}: ShadowFieldProps) {
  const isCustom = mode === 'custom';

  // Preview the generated shadow
  const previewShadow = buildShadowCss(mode, preset, custom);

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <label className="text-[11px] font-medium text-base-content/50">{label}</label>
        <div className="flex gap-0.5">
          <button
            type="button"
            onClick={() => onChangeMode('preset')}
            className={`px-2 py-0.5 text-[9px] rounded transition-colors ${!isCustom ? 'bg-primary/10 text-primary font-medium' : 'text-base-content/30 hover:text-base-content/50'}`}
          >
            Preset
          </button>
          <button
            type="button"
            onClick={() => onChangeMode('custom')}
            className={`px-2 py-0.5 text-[9px] rounded transition-colors ${isCustom ? 'bg-primary/10 text-primary font-medium' : 'text-base-content/30 hover:text-base-content/50'}`}
          >
            Custom
          </button>
        </div>
      </div>

      {!isCustom ? (
        <select
          value={preset || ''}
          onChange={(e) => onChangePreset(e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]"
        >
          {presets.map((p) => (
            <option key={p.value} value={p.value}>{p.label}</option>
          ))}
        </select>
      ) : (
        <div className="space-y-1.5">
          <div className="grid grid-cols-2 gap-1.5">
            <div>
              <label className="text-[9px] text-base-content/30 block">X offset</label>
              <input
                type="text"
                value={custom.x || ''}
                onChange={(e) => onChangeCustom({ x: e.target.value })}
                className="input input-bordered input-xs w-full text-[10px] font-mono"
                placeholder="0px"
              />
            </div>
            <div>
              <label className="text-[9px] text-base-content/30 block">Y offset</label>
              <input
                type="text"
                value={custom.y || ''}
                onChange={(e) => onChangeCustom({ y: e.target.value })}
                className="input input-bordered input-xs w-full text-[10px] font-mono"
                placeholder="8px"
              />
            </div>
            <div>
              <label className="text-[9px] text-base-content/30 block">Blur</label>
              <input
                type="text"
                value={custom.blur || ''}
                onChange={(e) => onChangeCustom({ blur: e.target.value })}
                className="input input-bordered input-xs w-full text-[10px] font-mono"
                placeholder="24px"
              />
            </div>
            <div>
              <label className="text-[9px] text-base-content/30 block">Spread</label>
              <input
                type="text"
                value={custom.spread || ''}
                onChange={(e) => onChangeCustom({ spread: e.target.value })}
                className="input input-bordered input-xs w-full text-[10px] font-mono"
                placeholder="0px"
              />
            </div>
          </div>
          <div className="flex gap-1.5 items-end">
            <div className="flex-1">
              <label className="text-[9px] text-base-content/30 block">Color</label>
              <div className="flex gap-1">
                <input
                  type="color"
                  value={custom.color || '#000000'}
                  onChange={(e) => onChangeCustom({ color: e.target.value })}
                  className="w-7 h-6 rounded border border-base-300/30 cursor-pointer"
                />
                <input
                  type="text"
                  value={custom.color || ''}
                  onChange={(e) => onChangeCustom({ color: e.target.value })}
                  className="input input-bordered input-xs flex-1 text-[10px] font-mono"
                  placeholder="#000000"
                />
              </div>
            </div>
            <div className="w-16">
              <label className="text-[9px] text-base-content/30 block">Opacity</label>
              <input
                type="number"
                min={0}
                max={100}
                value={custom.opacity ?? 15}
                onChange={(e) => onChangeCustom({ opacity: Number(e.target.value) })}
                className="input input-bordered input-xs w-full text-[10px]"
              />
            </div>
          </div>
          <label className="flex items-center gap-1.5 cursor-pointer">
            <input
              type="checkbox"
              checked={custom.inset || false}
              onChange={(e) => onChangeCustom({ inset: e.target.checked })}
              className="checkbox checkbox-xs"
            />
            <span className="text-[10px] text-base-content/40">Inset shadow</span>
          </label>
          <button
            type="button"
            onClick={() => onChangeMode('preset')}
            className="text-[9px] text-base-content/30 hover:text-primary transition-colors"
          >
            Reset to preset
          </button>
        </div>
      )}

      {/* Shadow preview swatch */}
      {previewShadow && (
        <div
          className="h-6 rounded bg-base-200/50 border border-base-300/20"
          style={{ boxShadow: previewShadow }}
        />
      )}
    </div>
  );
}
