import { Monitor, Tablet, Smartphone, RotateCcw, Copy } from 'lucide-react';
import { useEditorStore } from '@/stores/editorStore';

/**
 * Transform panel for absolutely-positioned LAYER blocks (blocks inside a
 * slide / free canvas). Desktop edits block.data.layout; tablet/mobile edit
 * block.data.responsiveLayout[bp] (emitted as scoped @media overrides by
 * SliderRender — breakpoints ≤1023 / ≤767, same as BlockStyle).
 */
export interface LayerLayout {
  x?: string;
  y?: string;
  widthPct?: number;
  heightPct?: number;
  rotation?: number;
  zIndex?: number;
  hidden?: boolean;
  /** per-device override only (tablet/mobile): emitted as scoped @media
      font-size targeting the layer's descendants */
  fontSize?: string;
}
export interface ResponsiveLayerLayout {
  tablet?: LayerLayout;
  mobile?: LayerLayout;
}

interface LayerTransformPanelProps {
  value: LayerLayout;
  onChange: (layout: LayerLayout) => void;
  responsive?: ResponsiveLayerLayout;
  onResponsiveChange?: (v: ResponsiveLayerLayout) => void;
}

/** 9-point anchor presets: canonical starting positions on the slide canvas */
const ANCHORS: { x: string; y: string; label: string }[] = [
  { x: '8%', y: '10%', label: 'top left' }, { x: '40%', y: '10%', label: 'top center' }, { x: '72%', y: '10%', label: 'top right' },
  { x: '8%', y: '40%', label: 'middle left' }, { x: '40%', y: '40%', label: 'middle center' }, { x: '72%', y: '40%', label: 'middle right' },
  { x: '8%', y: '70%', label: 'bottom left' }, { x: '40%', y: '70%', label: 'bottom center' }, { x: '72%', y: '70%', label: 'bottom right' },
];

const DEVICE_ICONS = { desktop: Monitor, tablet: Tablet, mobile: Smartphone } as const;

export function LayerTransformPanel({ value, onChange, responsive, onResponsiveChange }: LayerTransformPanelProps) {
  const canvasDevice = useEditorStore(s => s.canvasDevice);
  const bp = onResponsiveChange ? canvasDevice : 'desktop';
  const override: LayerLayout = bp !== 'desktop' ? (responsive?.[bp] ?? {}) : {};
  const effective: LayerLayout = bp === 'desktop' ? value : { ...value, ...override };
  const hasOverride = bp !== 'desktop' && Object.keys(override).length > 0;
  const DeviceIcon = DEVICE_ICONS[bp];

  const update = (patch: Partial<LayerLayout>) => {
    if (bp === 'desktop') {
      onChange({ ...value, ...patch });
    } else {
      onResponsiveChange!({ ...responsive, [bp]: { ...override, ...patch } });
    }
  };
  const clearOverride = () => {
    if (bp === 'desktop') return;
    const next = { ...responsive };
    delete next[bp];
    onResponsiveChange!(next);
  };
  const copyFrom = (source: 'desktop' | 'tablet' | 'mobile') => {
    const src: LayerLayout = source === 'desktop' ? value : { ...value, ...(responsive?.[source] ?? {}) };
    const { x, y, widthPct, heightPct } = src;
    update({ x, y, widthPct, heightPct });
  };

  const num = (v: string, min: number, max: number): number | undefined => {
    if (v === '') return undefined;
    const n = Number(v);
    return Number.isFinite(n) ? Math.max(min, Math.min(max, n)) : undefined;
  };

  return (
    <div className={`space-y-2 ${hasOverride ? 'border-l-2 border-warning pl-2' : ''}`}>
      <div className="flex items-center justify-between text-[10px] text-base-content/40">
        <span className="flex items-center gap-1"><DeviceIcon size={11} /> {bp}{hasOverride ? ' (override)' : ''}</span>
        {bp !== 'desktop' && (
          <span className="flex items-center gap-1">
            <button type="button" title="Copy layout from desktop" onClick={() => copyFrom('desktop')}
              className="flex items-center gap-0.5 hover:text-primary"><Copy size={10} /> desktop</button>
            {bp === 'mobile' && (
              <button type="button" title="Copy layout from tablet" onClick={() => copyFrom('tablet')}
                className="flex items-center gap-0.5 hover:text-primary"><Copy size={10} /> tablet</button>
            )}
            {hasOverride && (
              <button type="button" title="Clear this device's overrides" onClick={clearOverride}
                className="hover:text-warning"><RotateCcw size={10} /></button>
            )}
          </span>
        )}
      </div>

      <div className="flex items-start gap-3">
        <div>
          <label className="text-[10px] text-base-content/40 block mb-0.5">Anchor</label>
          <div className="grid grid-cols-3 gap-0.5 w-fit">
            {ANCHORS.map(a => (
              <button key={a.label} type="button" title={a.label}
                onClick={() => update({ x: a.x, y: a.y })}
                className={`w-4 h-4 border ${effective.x === a.x && effective.y === a.y
                  ? 'bg-primary border-primary'
                  : 'border-base-300 hover:border-base-content/40'}`} />
            ))}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-1.5 flex-1">
          <div>
            <label className="text-[10px] text-base-content/40">X</label>
            <input value={effective.x ?? ''} onChange={e => update({ x: e.target.value || undefined })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="8% / 120px" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Y</label>
            <input value={effective.y ?? ''} onChange={e => update({ y: e.target.value || undefined })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="32% / 40px" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Width %</label>
            <input type="number" min={0} max={100} value={effective.widthPct ?? ''}
              onChange={e => update({ widthPct: num(e.target.value, 0, 100) })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="auto" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Height %</label>
            <input type="number" min={0} max={100} value={effective.heightPct ?? ''}
              onChange={e => update({ heightPct: num(e.target.value, 0, 100) })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="auto" />
          </div>
        </div>
      </div>

      {bp === 'desktop' ? (
        <div className="grid grid-cols-2 gap-1.5">
          <div>
            <label className="text-[10px] text-base-content/40">Rotation °</label>
            <input type="number" min={-360} max={360} value={effective.rotation ?? ''}
              onChange={e => update({ rotation: num(e.target.value, -360, 360) })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="0" />
          </div>
          <div>
            <label className="text-[10px] text-base-content/40">Z-index</label>
            <input type="number" min={0} max={99} value={effective.zIndex ?? ''}
              onChange={e => update({ zIndex: num(e.target.value, 0, 99) })}
              className="input input-bordered input-xs w-full text-[11px]" placeholder="2" />
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-2 gap-1.5 items-end">
          <div>
            <label className="text-[10px] text-base-content/40">Font size ({bp})</label>
            <input value={override.fontSize ?? ''} placeholder="e.g. 40px"
              onChange={e => update({ fontSize: e.target.value || undefined })}
              className="input input-bordered input-xs w-full text-[11px]" />
          </div>
          <label className="flex items-center justify-between text-[11px] text-base-content/60 cursor-pointer pb-1">
            Hidden on {bp}
            <input type="checkbox" className="toggle toggle-xs" checked={!!override.hidden}
              onChange={e => update({ hidden: e.target.checked || undefined })} />
          </label>
        </div>
      )}
    </div>
  );
}
