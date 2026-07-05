
import type { MagElementStyle } from '@/types/magazine';

interface EffectsPanelProps {
  style: MagElementStyle;
  onChange: (v: Partial<MagElementStyle>) => void;
}

const BLEND_MODES: MagElementStyle['blendMode'][] = [
  'normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'soft-light',
];

export default function EffectsPanel({ style, onChange }: EffectsPanelProps) {
  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Effects</h3>

      {/* Opacity */}
      <div>
        <label htmlFor="effectspanel-opacity-1" className="text-[10px] text-base-content/40 mb-0.5 block">Opacity</label>
        <input id="effectspanel-opacity-1"
          type="range"
          min={0}
          max={100}
          value={Math.round(style.opacity * 100)}
          onChange={(e) => onChange({ opacity: Number(e.target.value) / 100 })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{Math.round(style.opacity * 100)}%</span>
      </div>

      {/* Shadow */}
      <div>
        <label className="flex items-center gap-1.5 cursor-pointer mb-1">
          <input
            type="checkbox"
            checked={!!style.shadow}
            onChange={(e) => {
              if (e.target.checked) {
                onChange({ shadow: { x: 2, y: 2, blur: 4, spread: 0, color: 'rgba(0,0,0,0.25)' } });
              } else {
                onChange({ shadow: null });
              }
            }}
            className="checkbox checkbox-xs"
          />
          <span className="text-[10px] text-base-content/40">Shadow</span>
        </label>
        {style.shadow && (
          <div className="space-y-2 pl-4">
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label htmlFor="effectspanel-x-2" className="text-[10px] text-base-content/40 mb-0.5 block">X</label>
                <input id="effectspanel-x-2"
                  type="number"
                  value={style.shadow.x}
                  onChange={(e) => onChange({ shadow: { ...style.shadow!, x: Number(e.target.value) } })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
              <div>
                <label htmlFor="effectspanel-y-3" className="text-[10px] text-base-content/40 mb-0.5 block">Y</label>
                <input id="effectspanel-y-3"
                  type="number"
                  value={style.shadow.y}
                  onChange={(e) => onChange({ shadow: { ...style.shadow!, y: Number(e.target.value) } })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label htmlFor="effectspanel-blur-4" className="text-[10px] text-base-content/40 mb-0.5 block">Blur</label>
                <input id="effectspanel-blur-4"
                  type="number"
                  value={style.shadow.blur}
                  onChange={(e) => onChange({ shadow: { ...style.shadow!, blur: Number(e.target.value) } })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
              <div>
                <label htmlFor="effectspanel-spread-5" className="text-[10px] text-base-content/40 mb-0.5 block">Spread</label>
                <input id="effectspanel-spread-5"
                  type="number"
                  value={style.shadow.spread}
                  onChange={(e) => onChange({ shadow: { ...style.shadow!, spread: Number(e.target.value) } })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
            </div>
            <div>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">Color</label>
              <div className="flex gap-1">
                <input
                  type="color"
                  value={style.shadow.color.startsWith('rgba') ? '#000000' : style.shadow.color}
                  onChange={(e) => onChange({ shadow: { ...style.shadow!, color: e.target.value } })}
                  className="w-8 h-6 cursor-pointer rounded border border-base-300"
                />
                <input
                  type="text"
                  value={style.shadow.color}
                  onChange={(e) => onChange({ shadow: { ...style.shadow!, color: e.target.value } })}
                  className="input input-bordered input-xs flex-1"
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Inner shadow */}
      <div>
        <label className="flex items-center gap-1.5 cursor-pointer mb-1">
          <input
            type="checkbox"
            checked={!!style.innerShadow}
            onChange={(e) => {
              if (e.target.checked) {
                onChange({ innerShadow: { x: 0, y: 2, blur: 4, color: 'rgba(0,0,0,0.15)' } });
              } else {
                onChange({ innerShadow: null });
              }
            }}
            className="checkbox checkbox-xs"
          />
          <span className="text-[10px] text-base-content/40">Inner shadow</span>
        </label>
        {style.innerShadow && (
          <div className="space-y-2 pl-4">
            <div className="grid grid-cols-3 gap-1">
              <div>
                <label htmlFor="effectspanel-x-6" className="text-[10px] text-base-content/40 mb-0.5 block">X</label>
                <input id="effectspanel-x-6"
                  type="number"
                  value={style.innerShadow.x}
                  onChange={(e) => onChange({ innerShadow: { ...style.innerShadow!, x: Number(e.target.value) } })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
              <div>
                <label htmlFor="effectspanel-y-7" className="text-[10px] text-base-content/40 mb-0.5 block">Y</label>
                <input id="effectspanel-y-7"
                  type="number"
                  value={style.innerShadow.y}
                  onChange={(e) => onChange({ innerShadow: { ...style.innerShadow!, y: Number(e.target.value) } })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
              <div>
                <label htmlFor="effectspanel-blur-8" className="text-[10px] text-base-content/40 mb-0.5 block">Blur</label>
                <input id="effectspanel-blur-8"
                  type="number"
                  value={style.innerShadow.blur}
                  onChange={(e) => onChange({ innerShadow: { ...style.innerShadow!, blur: Number(e.target.value) } })}
                  className="input input-bordered input-xs w-full"
                />
              </div>
            </div>
            <div>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">Color</label>
              <div className="flex gap-1">
                <input
                  type="color"
                  value={style.innerShadow.color.startsWith('rgba') ? '#000000' : style.innerShadow.color}
                  onChange={(e) => onChange({ innerShadow: { ...style.innerShadow!, color: e.target.value } })}
                  className="w-8 h-6 cursor-pointer rounded border border-base-300"
                />
                <input
                  type="text"
                  value={style.innerShadow.color}
                  onChange={(e) => onChange({ innerShadow: { ...style.innerShadow!, color: e.target.value } })}
                  className="input input-bordered input-xs flex-1"
                />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Blend mode */}
      <div>
        <label htmlFor="effectspanel-blend-mode-9" className="text-[10px] text-base-content/40 mb-0.5 block">Blend mode</label>
        <select id="effectspanel-blend-mode-9"
          value={style.blendMode}
          onChange={(e) => onChange({ blendMode: e.target.value as MagElementStyle['blendMode'] })}
          className="select select-bordered select-xs w-full"
        >
          {BLEND_MODES.map((mode) => (
            <option key={mode} value={mode}>
              {mode.charAt(0).toUpperCase() + mode.slice(1).replace('-', ' ')}
            </option>
          ))}
        </select>
      </div>

      {/* Blur */}
      <div>
        <label htmlFor="effectspanel-blur-10" className="text-[10px] text-base-content/40 mb-0.5 block">Blur</label>
        <input id="effectspanel-blur-10"
          type="range"
          min={0}
          max={50}
          value={style.blur}
          onChange={(e) => onChange({ blur: Number(e.target.value) })}
          className="range range-xs w-full"
        />
        <span className="text-[10px] text-base-content/40">{style.blur}px</span>
      </div>
    </div>
  );
}
