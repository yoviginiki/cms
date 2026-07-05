// ═══════════════════════════════════════════════════════════════════════════
// SwatchPicker (W3): drop-in color field = native picker + hex input + the
// site's theme-token swatches + recent colors + screen eyedropper (native
// EyeDropper API where available). Swatches arrive via MagSwatchContext so
// every panel shares ONE fetch.
// ═══════════════════════════════════════════════════════════════════════════
import { createContext, useContext, useState } from 'react';
import { DEFAULT_SWATCHES, pushRecentColor, recentColors, type Swatch } from '@/lib/themeSwatches';

export const MagSwatchContext = createContext<Swatch[]>(DEFAULT_SWATCHES);

interface Props {
  value: string;
  onChange: (color: string) => void;
  name: string;
  compact?: boolean;
}

export function SwatchPicker({ value, onChange, name, compact }: Props) {
  const swatches = useContext(MagSwatchContext);
  const [showAll, setShowAll] = useState(false);
  const recents = recentColors();
  const canPick = typeof (window as any).EyeDropper === 'function';

  const commit = (c: string) => {
    onChange(c);
    pushRecentColor(c);
  };
  const eyedrop = async () => {
    try {
      const r = await new (window as any).EyeDropper().open();
      if (r?.sRGBHex) commit(r.sRGBHex);
    } catch {
      /* user cancelled */
    }
  };

  const visible = showAll ? swatches : swatches.slice(0, 7);

  return (
    <div className="space-y-1">
      <div className="flex gap-1.5 items-center">
        <input
          type="color"
          name={`${name}-picker`}
          value={/^#[0-9a-fA-F]{6}$/.test(value) ? value : '#000000'}
          onChange={(e) => commit(e.target.value)}
          className="w-7 h-7 rounded cursor-pointer border border-base-300/30 shrink-0"
        />
        <input
          type="text"
          name={`${name}-hex`}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onBlur={(e) => /^#[0-9a-fA-F]{3,8}$/.test(e.target.value) && pushRecentColor(e.target.value)}
          className="input input-bordered input-xs flex-1 min-w-0 text-[10px] font-mono"
          placeholder="#hex"
        />
        {canPick && (
          <button type="button" className="btn btn-ghost btn-xs px-1" title="Pick a color from the screen" onClick={eyedrop}>
            💧
          </button>
        )}
      </div>
      {!compact && (
        <>
          <div className="flex flex-wrap gap-1">
            {visible.map((s) => (
              <button
                key={s.name + s.value}
                type="button"
                title={`${s.name} — ${s.value}`}
                onClick={() => commit(s.value)}
                className={`w-4 h-4 rounded-sm border ${value?.toLowerCase() === s.value.toLowerCase() ? 'border-primary ring-1 ring-primary' : 'border-base-300/40'}`}
                style={{ backgroundColor: s.value }}
              />
            ))}
            {swatches.length > 7 && (
              <button type="button" className="text-[9px] text-base-content/40 hover:text-base-content/70 px-0.5" onClick={() => setShowAll((v) => !v)}>
                {showAll ? 'less' : `+${swatches.length - 7}`}
              </button>
            )}
          </div>
          {recents.length > 0 && (
            <div className="flex flex-wrap gap-1 items-center">
              <span className="text-[8px] text-base-content/25 uppercase">recent</span>
              {recents.map((c) => (
                <button key={c} type="button" title={c} onClick={() => commit(c)}
                  className="w-3.5 h-3.5 rounded-sm border border-base-300/40" style={{ backgroundColor: c }} />
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
}
