import type { AdvancedProps } from '@/types/blocks';

interface Props {
  value: AdvancedProps;
  onChange: (v: AdvancedProps) => void;
}

export function AdvancedPanel({ value, onChange }: Props) {
  const update = (key: keyof AdvancedProps, v: string) => onChange({ ...value, [key]: v || undefined });

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[10px] text-base-content/40">CSS class</label>
        <input value={value.customClass || ''} onChange={e => update('customClass', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px] font-mono" placeholder="my-class another-class" />
      </div>
      {/* Custom CSS field removed: collected but never emitted to published
          output (silent no-op — see layer-inspector-gap-analysis.md).
          Reintroduce only together with a sanitized, scoped emitter. */}
      <div>
        <label className="text-[10px] text-base-content/40">HTML ID</label>
        <input value={value.htmlId || ''} onChange={e => update('htmlId', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px] font-mono" placeholder="section-about" />
      </div>
      <div>
        <label className="text-[10px] text-base-content/40">ARIA label</label>
        <input value={value.ariaLabel || ''} onChange={e => update('ariaLabel', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="Accessible label" />
      </div>
    </div>
  );
}
