
import type { MagTextWrap } from '@/types/magazine';

interface TextWrapPanelProps {
  value: MagTextWrap;
  onChange: (v: Partial<MagTextWrap>) => void;
}

export default function TextWrapPanel({ value, onChange }: TextWrapPanelProps) {
  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Text Wrap</h3>

      {/* Type */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Type</label>
        <select
          value={value.type}
          onChange={(e) => onChange({ type: e.target.value as MagTextWrap['type'] })}
          className="select select-bordered select-xs w-full"
        >
          <option value="none">None</option>
          <option value="bounding-box">Bounding box</option>
          <option value="object-shape">Object shape</option>
          <option value="jump">Jump</option>
        </select>
      </div>

      {/* Offset */}
      <div className="grid grid-cols-4 gap-1">
        {(['top', 'right', 'bottom', 'left'] as const).map((side) => (
          <div key={side}>
            <label className="text-[10px] text-base-content/40 mb-0.5 block">{side.charAt(0).toUpperCase() + side.slice(1)}</label>
            <input
              type="number"
              value={value.offset[side]}
              onChange={(e) => onChange({ offset: { ...value.offset, [side]: Number(e.target.value) } })}
              className="input input-bordered input-xs w-full"
            />
          </div>
        ))}
      </div>

      {/* Side */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Side</label>
        <select
          value={value.side}
          onChange={(e) => onChange({ side: e.target.value as MagTextWrap['side'] })}
          className="select select-bordered select-xs w-full"
        >
          <option value="both">Both</option>
          <option value="left">Left</option>
          <option value="right">Right</option>
          <option value="largest">Largest</option>
        </select>
      </div>

      {/* Invert */}
      <label className="flex items-center gap-1.5 cursor-pointer">
        <input
          type="checkbox"
          checked={value.invert}
          onChange={(e) => onChange({ invert: e.target.checked })}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Invert</span>
      </label>
    </div>
  );
}
