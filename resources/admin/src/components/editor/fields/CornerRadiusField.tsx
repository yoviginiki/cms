import React, { useState } from 'react';

const PRESETS = [
  { value: '', label: 'None' },
  { value: '0.25rem', label: 'Small' },
  { value: '0.5rem', label: 'Medium' },
  { value: '1rem', label: 'Large' },
  { value: '50%', label: 'Pill (50%)' },
];

interface CornerRadiusFieldProps {
  label: string;
  /** Current per-corner values. Keys: topLeft, topRight, bottomRight, bottomLeft */
  value: { topLeft?: string; topRight?: string; bottomRight?: string; bottomLeft?: string };
  onChange: (val: { topLeft: string; topRight: string; bottomRight: string; bottomLeft: string }) => void;
  helperText?: string;
}

export function CornerRadiusField({ label, value, onChange, helperText }: CornerRadiusFieldProps) {
  const [linked, setLinked] = useState(() => {
    const tl = value.topLeft || '';
    const tr = value.topRight || '';
    const br = value.bottomRight || '';
    const bl = value.bottomLeft || '';
    return (!tl && !tr && !br && !bl) || (tl === tr && tr === br && br === bl);
  });
  const [mode, setMode] = useState<'preset' | 'custom'>(() => {
    const tl = value.topLeft || '';
    // If the value matches a preset, start in preset mode
    if (PRESETS.some((p) => p.value === tl) && linked) return 'preset';
    return tl ? 'custom' : 'preset';
  });

  const topLeft = value.topLeft || '';
  const topRight = value.topRight || '';
  const bottomRight = value.bottomRight || '';
  const bottomLeft = value.bottomLeft || '';

  const handlePreset = (v: string) => {
    onChange({ topLeft: v, topRight: v, bottomRight: v, bottomLeft: v });
  };

  const handleChange = (corner: 'topLeft' | 'topRight' | 'bottomRight' | 'bottomLeft', v: string) => {
    if (linked) {
      onChange({ topLeft: v, topRight: v, bottomRight: v, bottomLeft: v });
    } else {
      onChange({ topLeft, topRight, bottomRight, bottomLeft, [corner]: v });
    }
  };

  const handleToggleLink = () => {
    if (!linked) {
      onChange({ topLeft, topRight: topLeft, bottomRight: topLeft, bottomLeft: topLeft });
    }
    setLinked(!linked);
  };

  const handleClear = () => {
    onChange({ topLeft: '', topRight: '', bottomRight: '', bottomLeft: '' });
    setMode('preset');
  };

  return (
    <div className="mb-3">
      <div className="flex items-center justify-between mb-1">
        <label className="block text-[11px] font-medium text-base-content/50">{label}</label>
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={() => setMode(mode === 'preset' ? 'custom' : 'preset')}
            className={`text-[10px] px-1.5 py-0.5 rounded ${mode === 'custom' ? 'bg-primary/10 text-primary' : 'bg-base-200 text-base-content/40'}`}
            title={mode === 'preset' ? 'Switch to custom' : 'Switch to presets'}
          >
            {mode === 'preset' ? 'Custom' : 'Presets'}
          </button>
          {mode === 'custom' && (
            <button
              type="button"
              onClick={handleToggleLink}
              className={`text-[10px] px-1.5 py-0.5 rounded ${linked ? 'bg-primary/10 text-primary' : 'bg-base-200 text-base-content/40'}`}
              title={linked ? 'Unlink corners' : 'Link all corners'}
            >
              {linked ? '🔗' : '⛓️‍💥'}
            </button>
          )}
          <button
            type="button"
            onClick={handleClear}
            className="text-[10px] px-1.5 py-0.5 rounded bg-base-200 text-base-content/40 hover:text-base-content/60"
            title="Clear"
          >
            ✕
          </button>
        </div>
      </div>
      {mode === 'preset' ? (
        <select
          value={topLeft}
          onChange={(e) => handlePreset(e.target.value)}
          className="select select-bordered select-sm w-full text-[12px]"
        >
          {PRESETS.map((p) => (
            <option key={p.value} value={p.value}>{p.label}</option>
          ))}
        </select>
      ) : linked ? (
        <input
          type="text"
          value={topLeft}
          onChange={(e) => handleChange('topLeft', e.target.value)}
          placeholder="e.g. 0.75rem, 12px"
          className="input input-bordered input-sm w-full text-[12px]"
        />
      ) : (
        <div className="grid grid-cols-2 gap-1">
          <div>
            <span className="text-[9px] text-base-content/40">Top-Left</span>
            <input
              type="text"
              value={topLeft}
              onChange={(e) => handleChange('topLeft', e.target.value)}
              placeholder="0"
              className="input input-bordered input-xs w-full text-[11px]"
            />
          </div>
          <div>
            <span className="text-[9px] text-base-content/40">Top-Right</span>
            <input
              type="text"
              value={topRight}
              onChange={(e) => handleChange('topRight', e.target.value)}
              placeholder="0"
              className="input input-bordered input-xs w-full text-[11px]"
            />
          </div>
          <div>
            <span className="text-[9px] text-base-content/40">Bottom-Right</span>
            <input
              type="text"
              value={bottomRight}
              onChange={(e) => handleChange('bottomRight', e.target.value)}
              placeholder="0"
              className="input input-bordered input-xs w-full text-[11px]"
            />
          </div>
          <div>
            <span className="text-[9px] text-base-content/40">Bottom-Left</span>
            <input
              type="text"
              value={bottomLeft}
              onChange={(e) => handleChange('bottomLeft', e.target.value)}
              placeholder="0"
              className="input input-bordered input-xs w-full text-[11px]"
            />
          </div>
        </div>
      )}
      {helperText && <p className="text-[10px] text-base-content/40 mt-0.5">{helperText}</p>}
    </div>
  );
}
