import type { TypographyProps } from '@/types/blocks';

interface Props {
  value: TypographyProps;
  onChange: (v: TypographyProps) => void;
}

const FONT_SIZES = ['12px', '14px', '16px', '18px', '20px', '24px', '28px', '32px', '40px', '48px', '56px', '72px'];
const FONT_FAMILIES = [
  { value: '', label: 'Theme default' },
  { value: "'Inter', system-ui, sans-serif", label: 'Inter' },
  { value: "'Instrument Serif', Georgia, serif", label: 'Instrument Serif' },
  { value: "Georgia, 'Times New Roman', serif", label: 'Georgia' },
  { value: "system-ui, sans-serif", label: 'System' },
  { value: "'SF Mono', 'Fira Code', monospace", label: 'Monospace' },
];

export function TypographyPanel({ value, onChange }: Props) {
  const update = (key: keyof TypographyProps, v: unknown) => onChange({ ...value, [key]: v || undefined });

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[10px] text-base-content/40">Font family</label>
        <select value={value.fontFamily || ''} onChange={e => update('fontFamily', e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          {FONT_FAMILIES.map(f => <option key={f.value} value={f.value}>{f.label}</option>)}
        </select>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40">Size</label>
          <select value={value.fontSize || ''} onChange={e => update('fontSize', e.target.value)}
            className="select select-bordered select-xs w-full text-[11px]">
            <option value="">Default</option>
            {FONT_SIZES.map(s => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Weight</label>
          <select value={String(value.fontWeight || '')} onChange={e => update('fontWeight', e.target.value ? Number(e.target.value) : undefined)}
            className="select select-bordered select-xs w-full text-[11px]">
            <option value="">Default</option>
            <option value="400">Regular (400)</option>
            <option value="500">Medium (500)</option>
          </select>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40">Line height</label>
          <input value={value.lineHeight || ''} onChange={e => update('lineHeight', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="1.6" />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Letter spacing</label>
          <input value={value.letterSpacing || ''} onChange={e => update('letterSpacing', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="-0.02em" />
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40 mb-1 block">Alignment</label>
        <div className="flex gap-0.5">
          {(['left', 'center', 'right', 'justify'] as const).map(a => (
            <button key={a} onClick={() => update('textAlign', value.textAlign === a ? undefined : a)}
              className={`btn btn-xs flex-1 text-[10px] ${value.textAlign === a ? 'btn-primary' : 'btn-ghost'}`}>
              {a[0].toUpperCase() + a.slice(1)}
            </button>
          ))}
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Transform</label>
        <select value={value.textTransform || 'none'} onChange={e => update('textTransform', e.target.value === 'none' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="none">None</option>
          <option value="uppercase">Uppercase</option>
          <option value="lowercase">Lowercase</option>
          <option value="capitalize">Capitalize</option>
        </select>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Text color</label>
        <div className="flex gap-2">
          <input type="color" value={value.textColor || '#000000'} onChange={e => update('textColor', e.target.value)}
            className="w-8 h-7 rounded cursor-pointer border border-base-300/30" />
          <input value={value.textColor || ''} onChange={e => update('textColor', e.target.value)}
            className="input input-bordered input-xs flex-1 text-[11px]" placeholder="inherit" />
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Paragraph spacing after</label>
        <input value={value.paragraphSpacingAfter || ''} onChange={e => update('paragraphSpacingAfter', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="1.2em" />
      </div>
    </div>
  );
}
