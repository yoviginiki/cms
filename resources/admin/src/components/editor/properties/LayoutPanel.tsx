import { Monitor, Tablet, Smartphone, RotateCcw } from 'lucide-react';
import type { LayoutProps, BlockStyleProps, ResponsiveOverrides } from '@/types/blocks';
import type { Breakpoint } from '@/lib/breakpoints';
import { useEditorStore } from '@/stores/editorStore';
import {
  getResponsiveStyleSection,
  hasResponsiveStyleOverride,
} from '@/lib/responsiveValues';

interface Props {
  value: LayoutProps;
  onChange: (v: LayoutProps) => void;
  style?: BlockStyleProps;
  responsive?: ResponsiveOverrides;
  onResponsiveChange?: (resp: ResponsiveOverrides) => void;
}

// Keys that are most useful as responsive overrides
const RESPONSIVE_KEYS = ['width', 'minWidth', 'maxWidth', 'height', 'minHeight', 'maxHeight', 'display', 'alignment', 'overflow'] as const;

export function LayoutPanel({ value, onChange, style, responsive, onResponsiveChange }: Props) {
  const canvasDevice = useEditorStore((s) => s.canvasDevice);
  const isResponsive = !!onResponsiveChange;
  const bp: Breakpoint = isResponsive ? canvasDevice : 'desktop';

  // Resolve effective values for current breakpoint
  const effective = isResponsive
    ? getResponsiveStyleSection(style, responsive, 'layout', bp) as LayoutProps
    : value;

  const update = (key: keyof LayoutProps, v: unknown) => {
    if (bp === 'desktop' || !isResponsive) {
      onChange({ ...value, [key]: v || undefined });
    } else {
      const resp = responsive ?? {};
      const bpOverrides = resp[bp] ?? {};
      const layoutOverrides = ((bpOverrides as Record<string, unknown>).layout ?? {}) as Record<string, unknown>;
      onResponsiveChange!({
        ...resp,
        [bp]: { ...bpOverrides, layout: { ...layoutOverrides, [key]: v || undefined } },
      });
    }
  };

  const clearAllOverrides = () => {
    if (!isResponsive || bp === 'desktop') return;
    const resp = responsive ?? {};
    const bpOverrides = { ...(resp[bp] ?? {}) };
    delete (bpOverrides as Record<string, unknown>).layout;
    onResponsiveChange!({ ...resp, [bp]: bpOverrides });
  };

  const hasOverrides = isResponsive && bp !== 'desktop' &&
    RESPONSIVE_KEYS.some(k => hasResponsiveStyleOverride(responsive, 'layout', k, bp));

  return (
    <div className="space-y-3">
      {/* Breakpoint indicator */}
      {isResponsive && (
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1">
            {bp === 'desktop' && <Monitor size={11} className="text-base-content/40" />}
            {bp === 'tablet' && <Tablet size={11} className="text-warning" />}
            {bp === 'mobile' && <Smartphone size={11} className="text-info" />}
            <span className="text-[10px] text-base-content/40">
              {bp === 'desktop' ? 'Base values' : `${bp} overrides`}
            </span>
          </div>
          {hasOverrides && (
            <button onClick={clearAllOverrides} className="flex items-center gap-0.5 text-[9px] text-warning hover:text-warning-content">
              <RotateCcw size={9} /> Reset {bp}
            </button>
          )}
        </div>
      )}

      {/* ─── Size ─── */}
      <div className="text-[9px] text-base-content/30 uppercase tracking-wider font-medium">Size</div>
      <div className="grid grid-cols-2 gap-2">
        <SizeField label="Width" prop="width" placeholder="auto / 100% / 50vw" effective={effective} update={update} responsive={responsive} bp={bp} isResponsive={isResponsive} />
        <SizeField label="Height" prop="height" placeholder="auto / 100vh" effective={effective} update={update} responsive={responsive} bp={bp} isResponsive={isResponsive} />
        <SizeField label="Min Width" prop="minWidth" placeholder="0" effective={effective} update={update} responsive={responsive} bp={bp} isResponsive={isResponsive} />
        <SizeField label="Min Height" prop="minHeight" placeholder="0 / 100vh" effective={effective} update={update} responsive={responsive} bp={bp} isResponsive={isResponsive} />
        <SizeField label="Max Width" prop="maxWidth" placeholder="100% / 1200px" effective={effective} update={update} responsive={responsive} bp={bp} isResponsive={isResponsive} />
        <SizeField label="Max Height" prop="maxHeight" placeholder="none" effective={effective} update={update} responsive={responsive} bp={bp} isResponsive={isResponsive} />
      </div>

      {/* Overflow */}
      <div>
        <label className="text-[10px] text-base-content/40">Overflow</label>
        <select value={effective.overflow || 'visible'} onChange={e => update('overflow', e.target.value === 'visible' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="visible">Visible</option>
          <option value="hidden">Hidden</option>
          <option value="auto">Auto (scroll if needed)</option>
          <option value="scroll">Scroll</option>
        </select>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40 mb-1 block">Alignment</label>
        <div className="flex gap-0.5">
          {(['left', 'center', 'right', 'stretch'] as const).map(a => (
            <button key={a} onClick={() => update('alignment', effective.alignment === a ? undefined : a)}
              className={`btn btn-xs flex-1 text-[10px] ${effective.alignment === a ? 'btn-primary' : 'btn-ghost'}`}>
              {a[0].toUpperCase() + a.slice(1)}
            </button>
          ))}
        </div>
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Display</label>
        <select value={effective.display || 'block'} onChange={e => update('display', e.target.value === 'block' ? undefined : e.target.value)}
          className="select select-bordered select-xs w-full text-[11px]">
          <option value="block">Block</option>
          <option value="flex">Flex</option>
          <option value="grid">Grid</option>
          <option value="none">Hidden</option>
        </select>
      </div>

      {effective.display === 'flex' && (
        <div className="grid grid-cols-2 gap-2">
          <div>
            <label className="text-[10px] text-base-content/40">Direction</label>
            <select value={effective.flexDirection || 'row'} onChange={e => update('flexDirection', e.target.value)}
              className="select select-bordered select-xs w-full text-[11px]">
              <option value="row">Row</option>
              <option value="column">Column</option>
            </select>
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Justify</label>
            <select value={effective.justifyContent || ''} onChange={e => update('justifyContent', e.target.value)}
              className="select select-bordered select-xs w-full text-[11px]">
              <option value="">Default</option>
              <option value="flex-start">Start</option>
              <option value="center">Center</option>
              <option value="flex-end">End</option>
              <option value="space-between">Space between</option>
            </select>
          </div>
        </div>
      )}

      <div>
        <label className="text-[10px] text-base-content/40">Z-index</label>
        <input type="number" value={effective.zIndex ?? ''} onChange={e => update('zIndex', e.target.value ? Number(e.target.value) : undefined)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="auto" />
      </div>
    </div>
  );
}

function SizeField({ label, prop, placeholder, effective, update, responsive, bp, isResponsive }: {
  label: string; prop: string; placeholder: string;
  effective: any; update: (k: any, v: any) => void;
  responsive?: any; bp: string; isResponsive: boolean;
}) {
  const hasOverride = isResponsive && bp !== 'desktop' && hasResponsiveStyleOverride(responsive, 'layout', prop, bp as any);
  return (
    <div>
      <label className="text-[10px] text-base-content/40">{label}</label>
      <input value={effective[prop] || ''} onChange={e => update(prop, e.target.value)}
        className={`input input-bordered input-xs w-full text-[11px] ${hasOverride ? 'border-warning/50' : ''}`}
        placeholder={placeholder} />
    </div>
  );
}
