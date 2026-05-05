import type { SpacingProps } from '@/types/blocks';

interface Props {
  value: SpacingProps;
  onChange: (v: SpacingProps) => void;
}

const PRESETS = [
  { label: 'None', m: '0', p: '0' },
  { label: 'S', m: '8px', p: '12px' },
  { label: 'M', m: '16px', p: '24px' },
  { label: 'L', m: '32px', p: '40px' },
  { label: 'XL', m: '48px', p: '64px' },
];

export function SpacingPanel({ value, onChange }: Props) {
  const update = (key: keyof SpacingProps, v: string) => onChange({ ...value, [key]: v || undefined });

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-1 mb-2">
        {PRESETS.map(p => (
          <button key={p.label} onClick={() => onChange({
            marginTop: p.m, marginBottom: p.m,
            paddingTop: p.p, paddingRight: p.p, paddingBottom: p.p, paddingLeft: p.p,
          })} className="px-2 py-0.5 text-[10px] rounded border border-base-300/30 text-base-content/50 hover:bg-base-300/20">
            {p.label}
          </button>
        ))}
      </div>

      <div className="text-[10px] text-base-content/30 uppercase tracking-wider">Margin</div>
      <div className="grid grid-cols-4 gap-1">
        {(['marginTop', 'marginRight', 'marginBottom', 'marginLeft'] as const).map(k => (
          <div key={k}>
            <label className="block text-[9px] text-base-content/30 text-center mb-0.5">{k.replace('margin', '')[0]}</label>
            <input value={value[k] || ''} onChange={e => update(k, e.target.value)}
              className="input input-bordered input-xs w-full text-[10px] text-center" placeholder="0" />
          </div>
        ))}
      </div>

      <div className="text-[10px] text-base-content/30 uppercase tracking-wider">Padding</div>
      <div className="grid grid-cols-4 gap-1">
        {(['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft'] as const).map(k => (
          <div key={k}>
            <label className="block text-[9px] text-base-content/30 text-center mb-0.5">{k.replace('padding', '')[0]}</label>
            <input value={value[k] || ''} onChange={e => update(k, e.target.value)}
              className="input input-bordered input-xs w-full text-[10px] text-center" placeholder="0" />
          </div>
        ))}
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Gap (flex/grid children)</label>
        <input value={value.gap || ''} onChange={e => update('gap', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="16px" />
      </div>
    </div>
  );
}
