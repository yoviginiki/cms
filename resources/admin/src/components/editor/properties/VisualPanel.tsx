import type { VisualProps } from '@/types/blocks';

interface Props {
  value: VisualProps;
  onChange: (v: VisualProps) => void;
}

const SHADOW_PRESETS = [
  { label: 'None', value: 'none' },
  { label: 'SM', value: 'sm' },
  { label: 'MD', value: 'md' },
  { label: 'LG', value: 'lg' },
];

export function VisualPanel({ value, onChange }: Props) {
  const update = (key: keyof VisualProps, v: unknown) => onChange({ ...value, [key]: v || undefined });

  return (
    <div className="space-y-3">
      <p className="text-[9px] text-base-content/25 italic">
        Background color/image/gradient below are for generic blocks. Hero and other blocks with their own background section use those instead.
      </p>
      <div>
        <label className="text-[10px] text-base-content/40">Background color</label>
        <div className="flex gap-2">
          <input type="color" value={value.backgroundColor || '#ffffff'} onChange={e => update('backgroundColor', e.target.value)}
            className="w-8 h-7 rounded cursor-pointer border border-base-300/30" />
          <input value={value.backgroundColor || ''} onChange={e => update('backgroundColor', e.target.value)}
            className="input input-bordered input-xs flex-1 text-[11px]" placeholder="transparent" />
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Background image</label>
        <input value={value.backgroundImage || ''} onChange={e => update('backgroundImage', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="url(https://...)" />
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Gradient</label>
        <input value={value.backgroundGradient || ''} onChange={e => update('backgroundGradient', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="linear-gradient(135deg, #667eea, #764ba2)" />
      </div>

      <div className="grid grid-cols-3 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40">Border width</label>
          <input value={value.borderWidth || ''} onChange={e => update('borderWidth', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="0" />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Color</label>
          <input value={value.borderColor || ''} onChange={e => update('borderColor', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="#e5e7eb" />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Style</label>
          <select value={value.borderStyle || 'none'} onChange={e => update('borderStyle', e.target.value)}
            className="select select-bordered select-xs w-full text-[11px]">
            <option value="none">None</option>
            <option value="solid">Solid</option>
            <option value="dashed">Dashed</option>
            <option value="dotted">Dotted</option>
          </select>
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Border radius</label>
        <input value={value.borderRadius || ''} onChange={e => update('borderRadius', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="0, 8px, 50%" />
      </div>

      <div>
        <label className="text-[10px] text-base-content/40 mb-1 block">Shadow</label>
        <div className="flex gap-1">
          {SHADOW_PRESETS.map(s => (
            <button key={s.value} onClick={() => update('boxShadow', s.value === 'none' ? undefined : s.value)}
              className={`btn btn-xs flex-1 text-[10px] ${(value.boxShadow || 'none') === s.value ? 'btn-primary' : 'btn-ghost'}`}>
              {s.label}
            </button>
          ))}
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Block Opacity</label>
        <input type="range" min={0} max={100} value={(value.opacity ?? 1) * 100}
          onChange={e => update('opacity', Number(e.target.value) / 100)}
          className="range range-xs w-full" />
        <span className="text-[10px] text-base-content/30">{Math.round((value.opacity ?? 1) * 100)}%</span>
        <p className="text-[9px] text-warning/60 mt-0.5">Affects entire block including text. For background-only opacity, use the block&apos;s own overlay controls.</p>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Overflow</label>
        <select value={value.overflow || 'visible'} onChange={e => update('overflow', e.target.value === 'visible' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="visible">Visible</option>
          <option value="hidden">Hidden</option>
          <option value="scroll">Scroll</option>
        </select>
      </div>
    </div>
  );
}
