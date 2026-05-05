
import type { TextFrameData } from '@/types/magazine';

interface TextFramePanelProps {
  data: TextFrameData;
  onChange: (v: Partial<TextFrameData>) => void;
  threadInfo?: { position: number; total: number };
}

const VALIGN_OPTIONS: { value: TextFrameData['verticalAlign']; label: string }[] = [
  { value: 'top', label: 'Top' },
  { value: 'center', label: 'Center' },
  { value: 'bottom', label: 'Bottom' },
];

export default function TextFramePanel({ data, onChange, threadInfo }: TextFramePanelProps) {
  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Text Frame</h3>

      {/* Overflow */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Overflow</label>
        <select
          value={data.overflow}
          onChange={(e) => onChange({ overflow: e.target.value as TextFrameData['overflow'] })}
          className="select select-bordered select-xs w-full"
        >
          <option value="visible">Visible</option>
          <option value="hidden">Hidden</option>
          <option value="threaded">Threaded</option>
        </select>
      </div>

      {/* Auto-size */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Auto-size</label>
        <select
          value={data.autoSize}
          onChange={(e) => onChange({ autoSize: e.target.value as TextFrameData['autoSize'] })}
          className="select select-bordered select-xs w-full"
        >
          <option value="none">None</option>
          <option value="grow-height">Grow height</option>
          <option value="shrink-text">Shrink text</option>
        </select>
      </div>

      {/* Columns */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Columns</label>
          <input
            type="number"
            min={1}
            max={4}
            value={data.columnsInFrame}
            onChange={(e) => onChange({ columnsInFrame: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40 mb-0.5 block">Column gap</label>
          <input
            type="number"
            value={data.columnGap}
            onChange={(e) => onChange({ columnGap: Number(e.target.value) })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Column rule */}
      <label className="flex items-center gap-1.5 cursor-pointer">
        <input
          type="checkbox"
          checked={data.columnRule}
          onChange={(e) => onChange({ columnRule: e.target.checked })}
          className="checkbox checkbox-xs"
        />
        <span className="text-[10px] text-base-content/40">Column rule</span>
      </label>

      {/* Text inset */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Text inset</label>
        <div className="grid grid-cols-4 gap-1">
          {(['top', 'right', 'bottom', 'left'] as const).map((side) => (
            <div key={side}>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">{side.charAt(0).toUpperCase()}</label>
              <input
                type="number"
                value={data.textInset[side]}
                onChange={(e) => onChange({ textInset: { ...data.textInset, [side]: Number(e.target.value) } })}
                className="input input-bordered input-xs w-full"
              />
            </div>
          ))}
        </div>
      </div>

      {/* Vertical align */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Vertical align</label>
        <div className="flex gap-1">
          {VALIGN_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              type="button"
              onClick={() => onChange({ verticalAlign: opt.value })}
              className={`btn btn-xs flex-1 ${data.verticalAlign === opt.value ? 'btn-primary' : 'btn-ghost'}`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      {/* Thread status */}
      {threadInfo && (
        <div className="border-t border-base-300 pt-2">
          <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Threading</h3>
          <p className="text-[10px] text-base-content/40 mb-1">
            Frame {threadInfo.position} of {threadInfo.total}
          </p>
          <button
            type="button"
            className="btn btn-xs btn-ghost btn-outline w-full"
          >
            Unthread
          </button>
        </div>
      )}
    </div>
  );
}
