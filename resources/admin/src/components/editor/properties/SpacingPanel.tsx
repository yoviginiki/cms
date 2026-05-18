import { Monitor, Tablet, Smartphone, RotateCcw } from 'lucide-react';
import type { SpacingProps, BlockStyleProps, ResponsiveOverrides } from '@/types/blocks';
import type { Breakpoint } from '@/lib/breakpoints';
import { useEditorStore } from '@/stores/editorStore';
import {
  getResponsiveStyleSection,
  hasResponsiveStyleOverride,
} from '@/lib/responsiveValues';

interface Props {
  value: SpacingProps;
  onChange: (v: SpacingProps) => void;
  style?: BlockStyleProps;
  responsive?: ResponsiveOverrides;
  onResponsiveChange?: (resp: ResponsiveOverrides) => void;
}

const PRESETS = [
  { label: 'None', m: '0', p: '0' },
  { label: 'S', m: '8px', p: '12px' },
  { label: 'M', m: '16px', p: '24px' },
  { label: 'L', m: '32px', p: '40px' },
  { label: 'XL', m: '48px', p: '64px' },
];

const MARGIN_KEYS = ['marginTop', 'marginRight', 'marginBottom', 'marginLeft'] as const;
const PADDING_KEYS = ['paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft'] as const;

export function SpacingPanel({ value, onChange, style, responsive, onResponsiveChange }: Props) {
  const canvasDevice = useEditorStore((s) => s.canvasDevice);
  const isResponsive = !!onResponsiveChange;
  const bp: Breakpoint = isResponsive ? canvasDevice : 'desktop';

  // Resolve effective values for current breakpoint
  const effective = isResponsive
    ? getResponsiveStyleSection(style, responsive, 'spacing', bp) as SpacingProps
    : value;

  const update = (key: keyof SpacingProps, v: string) => {
    if (bp === 'desktop' || !isResponsive) {
      onChange({ ...value, [key]: v || undefined });
    } else {
      // Write to responsive override
      const resp = responsive ?? {};
      const bpOverrides = resp[bp] ?? {};
      const spacingOverrides = ((bpOverrides as Record<string, unknown>).spacing ?? {}) as Record<string, unknown>;
      onResponsiveChange!({
        ...resp,
        [bp]: { ...bpOverrides, spacing: { ...spacingOverrides, [key]: v || undefined } },
      });
    }
  };

  const handlePreset = (p: typeof PRESETS[number]) => {
    const preset = {
      marginTop: p.m, marginBottom: p.m,
      paddingTop: p.p, paddingRight: p.p, paddingBottom: p.p, paddingLeft: p.p,
    };
    if (bp === 'desktop' || !isResponsive) {
      onChange(preset);
    } else {
      const resp = responsive ?? {};
      const bpOverrides = resp[bp] ?? {};
      onResponsiveChange!({
        ...resp,
        [bp]: { ...bpOverrides, spacing: preset },
      });
    }
  };

  const clearAllOverrides = () => {
    if (!isResponsive || bp === 'desktop') return;
    const resp = responsive ?? {};
    const bpOverrides = { ...(resp[bp] ?? {}) };
    delete (bpOverrides as Record<string, unknown>).spacing;
    onResponsiveChange!({ ...resp, [bp]: bpOverrides });
  };

  const hasOverrides = isResponsive && bp !== 'desktop' &&
    [...MARGIN_KEYS, ...PADDING_KEYS, 'gap' as const].some(k =>
      hasResponsiveStyleOverride(responsive, 'spacing', k, bp));

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

      <div className="flex flex-wrap gap-1 mb-2">
        {PRESETS.map(p => (
          <button key={p.label} onClick={() => handlePreset(p)}
            className="px-2 py-0.5 text-[10px] rounded border border-base-300/30 text-base-content/50 hover:bg-base-300/20">
            {p.label}
          </button>
        ))}
      </div>

      <div className="text-[10px] text-base-content/30 uppercase tracking-wider">Margin</div>
      <div className="grid grid-cols-4 gap-1">
        {MARGIN_KEYS.map(k => (
          <div key={k}>
            <label className="block text-[9px] text-base-content/30 text-center mb-0.5">{k.replace('margin', '')[0]}</label>
            <input value={(effective as Record<string, string>)[k] || ''} onChange={e => update(k, e.target.value)}
              className={`input input-bordered input-xs w-full text-[10px] text-center ${
                isResponsive && bp !== 'desktop' && hasResponsiveStyleOverride(responsive, 'spacing', k, bp) ? 'border-warning/50' : ''
              }`} placeholder="0" />
          </div>
        ))}
      </div>

      <div className="text-[10px] text-base-content/30 uppercase tracking-wider">Padding</div>
      <div className="grid grid-cols-4 gap-1">
        {PADDING_KEYS.map(k => (
          <div key={k}>
            <label className="block text-[9px] text-base-content/30 text-center mb-0.5">{k.replace('padding', '')[0]}</label>
            <input value={(effective as Record<string, string>)[k] || ''} onChange={e => update(k, e.target.value)}
              className={`input input-bordered input-xs w-full text-[10px] text-center ${
                isResponsive && bp !== 'desktop' && hasResponsiveStyleOverride(responsive, 'spacing', k, bp) ? 'border-warning/50' : ''
              }`} placeholder="0" />
          </div>
        ))}
      </div>

      <div>
        <label className="text-[10px] text-base-content/40">Gap (flex/grid children)</label>
        <input value={(effective as Record<string, string>).gap || ''} onChange={e => update('gap', e.target.value)}
          className="input input-bordered input-xs w-full text-[11px]" placeholder="16px" />
      </div>
    </div>
  );
}
