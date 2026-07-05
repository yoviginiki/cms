
import type { MagPageData } from '@/types/magazine';

interface PagePanelProps {
  page: MagPageData;
  onChange: (v: Partial<MagPageData>) => void;
}

// Presets in POINTS — the canvas unit (audit W0-7: the old values were
// MILLIMETRES injected into a pt canvas, so "A4" produced a ~74×105mm page)
const PAGE_PRESETS: Record<string, { width: number; height: number }> = {
  A4: { width: 595, height: 842 },
  A3: { width: 842, height: 1191 },
  Letter: { width: 612, height: 792 },
  Tabloid: { width: 792, height: 1224 },
};

export default function PagePanel({ page, onChange }: PagePanelProps) {
  const currentPreset = Object.entries(PAGE_PRESETS).find(
    ([, size]) =>
      (size.width === page.pageSize.width && size.height === page.pageSize.height) ||
      (size.height === page.pageSize.width && size.width === page.pageSize.height)
  );
  const presetKey = currentPreset ? currentPreset[0] : 'Custom';
  const isLandscape = page.pageSize.width > page.pageSize.height;

  const handlePreset = (key: string) => {
    const preset = PAGE_PRESETS[key];
    if (preset) {
      onChange({
        pageSize: isLandscape
          ? { width: preset.height, height: preset.width }
          : { width: preset.width, height: preset.height },
      });
    }
  };

  const toggleOrientation = () => {
    onChange({ pageSize: { width: page.pageSize.height, height: page.pageSize.width } });
  };

  return (
    <div className="space-y-3">
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Page</h3>

      {/* Page size preset */}
      <div>
        <label htmlFor="pagepanel-page-size-1" className="text-[10px] text-base-content/40 mb-0.5 block">Page size</label>
        <select id="pagepanel-page-size-1"
          value={presetKey}
          onChange={(e) => handlePreset(e.target.value)}
          className="select select-bordered select-xs w-full"
        >
          {Object.keys(PAGE_PRESETS).map((key) => (
            <option key={key} value={key}>{key}</option>
          ))}
          <option value="Custom">Custom</option>
        </select>
      </div>

      {/* Width / Height */}
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label htmlFor="pagepanel-width-2" className="text-[10px] text-base-content/40 mb-0.5 block">Width</label>
          <input id="pagepanel-width-2"
            type="number"
            value={page.pageSize.width}
            onChange={(e) => onChange({ pageSize: { ...page.pageSize, width: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label htmlFor="pagepanel-height-3" className="text-[10px] text-base-content/40 mb-0.5 block">Height</label>
          <input id="pagepanel-height-3"
            type="number"
            value={page.pageSize.height}
            onChange={(e) => onChange({ pageSize: { ...page.pageSize, height: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Orientation */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Orientation</label>
        <label className="flex items-center gap-1.5 cursor-pointer">
          <input name="mag-pagepanel-1"
            type="checkbox"
            checked={isLandscape}
            onChange={toggleOrientation}
            className="checkbox checkbox-xs"
          />
          <span className="text-[10px] text-base-content/40">{isLandscape ? 'Landscape' : 'Portrait'}</span>
        </label>
      </div>

      {/* Margins */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Margins</label>
        <div className="grid grid-cols-4 gap-1">
          {(['top', 'right', 'bottom', 'left'] as const).map((side) => (
            <div key={side}>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">{side.charAt(0).toUpperCase()}</label>
              <input name="mag-pagepanel-2"
                type="number"
                value={page.margins[side]}
                onChange={(e) => onChange({ margins: { ...page.margins, [side]: Number(e.target.value) } })}
                className="input input-bordered input-xs w-full"
              />
            </div>
          ))}
        </div>
      </div>

      {/* Bleed */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Bleed</label>
        <div className="grid grid-cols-4 gap-1">
          {(['top', 'right', 'bottom', 'left'] as const).map((side) => (
            <div key={side}>
              <label className="text-[10px] text-base-content/40 mb-0.5 block">{side.charAt(0).toUpperCase()}</label>
              <input name="mag-pagepanel-3"
                type="number"
                value={page.bleed[side]}
                onChange={(e) => onChange({ bleed: { ...page.bleed, [side]: Number(e.target.value) } })}
                className="input input-bordered input-xs w-full"
              />
            </div>
          ))}
        </div>
      </div>

      {/* Column grid */}
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Column Grid</h3>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label htmlFor="pagepanel-count-4" className="text-[10px] text-base-content/40 mb-0.5 block">Count</label>
          <input id="pagepanel-count-4"
            type="number"
            min={1}
            value={page.columns.count}
            onChange={(e) => onChange({ columns: { ...page.columns, count: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label htmlFor="pagepanel-gutter-5" className="text-[10px] text-base-content/40 mb-0.5 block">Gutter</label>
          <input id="pagepanel-gutter-5"
            type="number"
            value={page.columns.gutter}
            onChange={(e) => onChange({ columns: { ...page.columns, gutter: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Baseline grid */}
      <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-2">Baseline Grid</h3>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label htmlFor="pagepanel-increment-6" className="text-[10px] text-base-content/40 mb-0.5 block">Increment</label>
          <input id="pagepanel-increment-6"
            type="number"
            value={page.baselineGrid.increment}
            onChange={(e) => onChange({ baselineGrid: { ...page.baselineGrid, increment: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
        <div>
          <label htmlFor="pagepanel-start-7" className="text-[10px] text-base-content/40 mb-0.5 block">Start</label>
          <input id="pagepanel-start-7"
            type="number"
            value={page.baselineGrid.start}
            onChange={(e) => onChange({ baselineGrid: { ...page.baselineGrid, start: Number(e.target.value) } })}
            className="input input-bordered input-xs w-full"
          />
        </div>
      </div>

      {/* Background color */}
      <div>
        <label className="text-[10px] text-base-content/40 mb-0.5 block">Background color</label>
        <div className="flex gap-1">
          <input name="mag-pagepanel-4"
            type="color"
            value={page.backgroundColor ?? '#ffffff'}
            onChange={(e) => onChange({ backgroundColor: e.target.value })}
            className="w-8 h-6 cursor-pointer rounded border border-base-300"
          />
          <input name="mag-pagepanel-5"
            type="text"
            value={page.backgroundColor ?? ''}
            onChange={(e) => onChange({ backgroundColor: e.target.value || null })}
            className="input input-bordered input-xs flex-1"
          />
        </div>
      </div>

      {/* Master page */}
      <div>
        <label htmlFor="pagepanel-master-page-8" className="text-[10px] text-base-content/40 mb-0.5 block">Master page</label>
        <select id="pagepanel-master-page-8"
          value={page.masterPageId ?? ''}
          onChange={(e) => onChange({ masterPageId: e.target.value || null })}
          className="select select-bordered select-xs w-full"
        >
          <option value="">None</option>
        </select>
      </div>
      {/* Ruler guides (W2-1): numeric editing */}
      {(() => {
        const g = (page as any)._guides as { v: number[]; h: number[] } | undefined;
        if (!g || (g.v.length === 0 && g.h.length === 0)) return null;
        const setG = (axis: 'v' | 'h', i: number, val: number) => {
          const next = { v: [...(g.v || [])], h: [...(g.h || [])] };
          next[axis][i] = val;
          onChange({ _guides: next } as any);
        };
        const rm = (axis: 'v' | 'h', i: number) => {
          const next = { v: [...(g.v || [])], h: [...(g.h || [])] };
          next[axis].splice(i, 1);
          onChange({ _guides: next } as any);
        };
        return (
          <div>
            <h3 className="text-[10px] text-base-content/30 uppercase tracking-wider font-medium mb-1 mt-3">Guides</h3>
            {(['v', 'h'] as const).map((axis) =>
              (g[axis] || []).map((pos, i) => (
                <div key={`${axis}-${i}`} className="flex items-center gap-1 mb-0.5">
                  <span className="text-[9px] text-base-content/40 w-3">{axis === 'v' ? 'X' : 'Y'}</span>
                  <input type="number" name={`guide-${axis}-${i}`} value={pos}
                    onChange={(e) => setG(axis, i, Number(e.target.value))}
                    className="input input-bordered input-xs w-20" />
                  <button className="btn btn-ghost btn-xs px-1" onClick={() => rm(axis, i)}>×</button>
                </div>
              )),
            )}
            <button className="text-[9px] text-warning/70 hover:text-warning"
              onClick={() => onChange({ _guides: { v: [], h: [] } } as any)}>Clear all guides</button>
          </div>
        );
      })()}
    </div>
  );
}
