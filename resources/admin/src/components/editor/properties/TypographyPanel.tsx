import { useState } from 'react';
import type { TypographyProps } from '@/types/blocks';
import { FontPicker } from '@/components/editor/fields/FontPicker';
import { TokenColorInput } from '@/components/editor/fields/TokenColorInput';

interface Props {
  value: TypographyProps;
  onChange: (v: TypographyProps) => void;
}

const WEIGHTS = [
  { value: '', label: 'Default' },
  { value: '100', label: '100 Thin' },
  { value: '200', label: '200 Extra Light' },
  { value: '300', label: '300 Light' },
  { value: '400', label: '400 Regular' },
  { value: '500', label: '500 Medium' },
  { value: '600', label: '600 Semi Bold' },
  { value: '700', label: '700 Bold' },
  { value: '800', label: '800 Extra Bold' },
  { value: '900', label: '900 Black' },
];

export function TypographyPanel({ value, onChange }: Props) {
  const update = (key: keyof TypographyProps, v: unknown) => onChange({ ...value, [key]: v || undefined });
  const [sizeMode, setSizeMode] = useState<'fixed' | 'scalable'>(
    value.fontSize?.includes('clamp') ? 'scalable' : 'fixed'
  );

  return (
    <div className="space-y-3">
      {/* Font Family — uses FontPicker with custom fonts */}
      <FontPicker
        label="Font Family"
        value={value.fontFamily || ''}
        onChange={v => update('fontFamily', v)}
      />

      {/* Font Size — fixed or scalable */}
      <div>
        <div className="flex items-center justify-between mb-1">
          <label className="text-[10px] text-base-content/40">Font Size</label>
          <div className="flex bg-base-200 rounded p-0.5">
            <button onClick={() => setSizeMode('fixed')}
              className={`px-1.5 py-0.5 text-[9px] rounded ${sizeMode === 'fixed' ? 'bg-base-100 shadow-sm' : 'text-base-content/40'}`}>
              Fixed
            </button>
            <button onClick={() => setSizeMode('scalable')}
              className={`px-1.5 py-0.5 text-[9px] rounded ${sizeMode === 'scalable' ? 'bg-base-100 shadow-sm' : 'text-base-content/40'}`}>
              Scalable
            </button>
          </div>
        </div>
        {sizeMode === 'fixed' ? (
          <input value={value.fontSize?.includes('clamp') ? '' : (value.fontSize || '')}
            onChange={e => update('fontSize', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]"
            placeholder="16px, 1.2rem, 2vw" />
        ) : (
          <div className="space-y-1.5">
            <div className="grid grid-cols-3 gap-1">
              <div>
                <label className="text-[9px] text-base-content/30">Min</label>
                <input value={value._fontSizeMin || '14px'}
                  onChange={e => {
                    const min = e.target.value || '14px';
                    const pref = value._fontSizePref || '1vw + 12px';
                    const max = value._fontSizeMax || '18px';
                    update('fontSize', `clamp(${min}, ${pref}, ${max})`);
                    onChange({ ...value, fontSize: `clamp(${min}, ${pref}, ${max})`, _fontSizeMin: min } as any);
                  }}
                  className="input input-bordered input-xs w-full text-[10px]" placeholder="14px" />
              </div>
              <div>
                <label className="text-[9px] text-base-content/30">Preferred</label>
                <input value={value._fontSizePref || '1vw + 12px'}
                  onChange={e => {
                    const min = value._fontSizeMin || '14px';
                    const pref = e.target.value || '1vw + 12px';
                    const max = value._fontSizeMax || '18px';
                    update('fontSize', `clamp(${min}, ${pref}, ${max})`);
                    onChange({ ...value, fontSize: `clamp(${min}, ${pref}, ${max})`, _fontSizePref: pref } as any);
                  }}
                  className="input input-bordered input-xs w-full text-[10px]" placeholder="1vw + 12px" />
              </div>
              <div>
                <label className="text-[9px] text-base-content/30">Max</label>
                <input value={value._fontSizeMax || '18px'}
                  onChange={e => {
                    const min = value._fontSizeMin || '14px';
                    const pref = value._fontSizePref || '1vw + 12px';
                    const max = e.target.value || '18px';
                    update('fontSize', `clamp(${min}, ${pref}, ${max})`);
                    onChange({ ...value, fontSize: `clamp(${min}, ${pref}, ${max})`, _fontSizeMax: max } as any);
                  }}
                  className="input input-bordered input-xs w-full text-[10px]" placeholder="18px" />
              </div>
            </div>
            <p className="text-[9px] text-base-content/25">
              Scales between min and max based on viewport. Use vw units in preferred.
            </p>
          </div>
        )}
      </div>

      {/* Weight */}
      <div>
        <label className="text-[10px] text-base-content/40">Weight</label>
        <select value={String(value.fontWeight || '')} onChange={e => update('fontWeight', e.target.value ? Number(e.target.value) : undefined)}
          className="select select-bordered select-xs w-full text-[11px]">
          {WEIGHTS.map(w => <option key={w.value} value={w.value}>{w.label}</option>)}
        </select>
      </div>

      {/* Line height + Letter spacing */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-[10px] text-base-content/40">Line Height</label>
          <input value={value.lineHeight || ''} onChange={e => update('lineHeight', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="1.6" />
        </div>
        <div>
          <label className="text-[10px] text-base-content/40">Letter Spacing</label>
          <input value={value.letterSpacing || ''} onChange={e => update('letterSpacing', e.target.value)}
            className="input input-bordered input-xs w-full text-[11px]" placeholder="-0.02em" />
        </div>
      </div>

      {/* Alignment */}
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

      {/* Transform */}
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

      {/* Color — theme-token aware */}
      <TokenColorInput label="Text Color" value={value.textColor || ''}
        onChange={v => update('textColor', v || undefined)} />

      {/* Font style */}
      <div>
        <label className="text-[10px] text-base-content/40">Style</label>
        <select value={value.fontStyle || 'normal'} onChange={e => update('fontStyle', e.target.value === 'normal' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="normal">Normal</option>
          <option value="italic">Italic</option>
        </select>
      </div>

      {/* Word spacing */}
      <div>
        <label className="text-[10px] text-base-content/40">Word Spacing</label>
        <input value={value.wordSpacing || ''} onChange={e => update('wordSpacing', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="normal" />
      </div>
    </div>
  );
}
