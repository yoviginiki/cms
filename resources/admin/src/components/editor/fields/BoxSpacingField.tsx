import { useState } from 'react';

interface BoxSpacingFieldProps {
  label: string;
  /** Current per-side values. Keys: top, right, bottom, left */
  value: { top?: string; right?: string; bottom?: string; left?: string };
  onChange: (val: { top: string; right: string; bottom: string; left: string }) => void;
  placeholder?: string;
  helperText?: string;
}

export function BoxSpacingField({ label, value, onChange, placeholder = '0', helperText }: BoxSpacingFieldProps) {
  const [linked, setLinked] = useState(() => {
    const t = value.top || '';
    const r = value.right || '';
    const b = value.bottom || '';
    const l = value.left || '';
    // Start linked if all sides are equal or all empty
    return (!t && !r && !b && !l) || (t === r && r === b && b === l);
  });

  const top = value.top || '';
  const right = value.right || '';
  const bottom = value.bottom || '';
  const left = value.left || '';

  const handleChange = (side: 'top' | 'right' | 'bottom' | 'left', v: string) => {
    if (linked) {
      onChange({ top: v, right: v, bottom: v, left: v });
    } else {
      onChange({ top, right, bottom, left, [side]: v });
    }
  };

  const handleToggleLink = () => {
    if (!linked) {
      // When linking, set all sides to the top value
      onChange({ top, right: top, bottom: top, left: top });
    }
    setLinked(!linked);
  };

  const handleClear = () => {
    onChange({ top: '', right: '', bottom: '', left: '' });
  };

  return (
    <div className="mb-3">
      <div className="flex items-center justify-between mb-1">
        <label className="block text-[11px] font-medium text-base-content/50">{label}</label>
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={handleToggleLink}
            className={`text-[10px] px-1.5 py-0.5 rounded ${linked ? 'bg-primary/10 text-primary' : 'bg-base-200 text-base-content/40'}`}
            title={linked ? 'Unlink sides' : 'Link all sides'}
          >
            {linked ? '🔗' : '⛓️‍💥'}
          </button>
          <button
            type="button"
            onClick={handleClear}
            className="text-[10px] px-1.5 py-0.5 rounded bg-base-200 text-base-content/40 hover:text-base-content/60"
            title="Clear all"
          >
            ✕
          </button>
        </div>
      </div>
      {linked ? (
        <input
          type="text"
          value={top}
          onChange={(e) => handleChange('top', e.target.value)}
          placeholder={placeholder}
          className="input input-bordered input-sm w-full text-[12px]"
        />
      ) : (
        <div className="grid grid-cols-2 gap-1">
          <div>
            <span className="text-[9px] text-base-content/40">Top</span>
            <input
              type="text"
              value={top}
              onChange={(e) => handleChange('top', e.target.value)}
              placeholder={placeholder}
              className="input input-bordered input-xs w-full text-[11px]"
            />
          </div>
          <div>
            <span className="text-[9px] text-base-content/40">Right</span>
            <input
              type="text"
              value={right}
              onChange={(e) => handleChange('right', e.target.value)}
              placeholder={placeholder}
              className="input input-bordered input-xs w-full text-[11px]"
            />
          </div>
          <div>
            <span className="text-[9px] text-base-content/40">Bottom</span>
            <input
              type="text"
              value={bottom}
              onChange={(e) => handleChange('bottom', e.target.value)}
              placeholder={placeholder}
              className="input input-bordered input-xs w-full text-[11px]"
            />
          </div>
          <div>
            <span className="text-[9px] text-base-content/40">Left</span>
            <input
              type="text"
              value={left}
              onChange={(e) => handleChange('left', e.target.value)}
              placeholder={placeholder}
              className="input input-bordered input-xs w-full text-[11px]"
            />
          </div>
        </div>
      )}
      {helperText && <p className="text-[10px] text-base-content/40 mt-0.5">{helperText}</p>}
    </div>
  );
}
