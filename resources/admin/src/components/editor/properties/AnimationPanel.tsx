import type { AnimationProps } from '@/types/blocks';

interface Props {
  value: AnimationProps;
  onChange: (v: AnimationProps) => void;
}

export function AnimationPanel({ value, onChange }: Props) {
  const update = (key: keyof AnimationProps, v: unknown) => onChange({ ...value, [key]: v || undefined });

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[10px] text-base-content/40">Entrance animation</label>
        <select value={value.entrance || 'none'} onChange={e => update('entrance', e.target.value === 'none' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="none">None</option>
          <option value="fade">Fade in</option>
          <option value="slide-up">Slide up</option>
          <option value="slide-down">Slide down</option>
          <option value="slide-left">Slide from left</option>
          <option value="slide-right">Slide from right</option>
          <option value="zoom">Zoom in</option>
          <option value="scale-in">Scale in</option>
        </select>
      </div>

      {value.entrance && value.entrance !== 'none' && (
        <>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-[10px] text-base-content/40">Duration (ms)</label>
              <input type="number" min={100} max={2000} step={50} value={value.duration ?? 600}
                onChange={e => update('duration', Number(e.target.value))}
                className="input input-bordered input-xs w-full text-[11px]" />
            </div>
            <div>
              <label className="text-[10px] text-base-content/40">Delay (ms)</label>
              <input type="number" min={0} max={2000} step={50} value={value.delay ?? 0}
                onChange={e => update('delay', Number(e.target.value))}
                className="input input-bordered input-xs w-full text-[11px]" />
            </div>
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Easing</label>
            <select value={value.easing || 'ease-out'} onChange={e => update('easing', e.target.value)}
              className="select select-bordered select-xs w-full text-[11px]">
              <option value="ease-out">Ease out (default)</option>
              <option value="ease-in">Ease in</option>
              <option value="ease-in-out">Ease in-out</option>
              <option value="ease">Ease</option>
              <option value="linear">Linear</option>
            </select>
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Trigger</label>
            <select value="on-load" disabled
              className="select select-bordered select-xs w-full text-[11px] opacity-50">
              <option value="on-load">On page load</option>
            </select>
            <p className="text-[9px] text-base-content/25 mt-0.5">Scroll-triggered animations coming soon</p>
          </div>
        </>
      )}
    </div>
  );
}
