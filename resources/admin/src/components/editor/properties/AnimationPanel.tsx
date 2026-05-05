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
          <option value="slide-left">Slide from left</option>
          <option value="slide-right">Slide from right</option>
          <option value="zoom">Zoom in</option>
        </select>
      </div>

      {value.entrance && value.entrance !== 'none' && (
        <>
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label className="text-[10px] text-base-content/40">Duration (ms)</label>
              <input type="number" min={100} max={2000} step={50} value={value.duration ?? 400}
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
            <label className="text-[10px] text-base-content/40">Trigger</label>
            <select value={value.trigger || 'on-scroll'} onChange={e => update('trigger', e.target.value)}
              className="select select-bordered select-xs w-full text-[11px]">
              <option value="on-load">On page load</option>
              <option value="on-scroll">On scroll into view</option>
            </select>
          </div>
        </>
      )}

      <div>
        <label className="text-[10px] text-base-content/40">Hover effect</label>
        <select value={value.hoverEffect || 'none'} onChange={e => update('hoverEffect', e.target.value === 'none' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="none">None</option>
          <option value="opacity">Opacity fade</option>
          <option value="lift">Lift (shadow + translate)</option>
          <option value="glow">Glow</option>
        </select>
      </div>
    </div>
  );
}
