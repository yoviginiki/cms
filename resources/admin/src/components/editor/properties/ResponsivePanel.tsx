import { Monitor, Tablet, Smartphone } from 'lucide-react';
import type { ResponsiveOverrides } from '@/types/blocks';

interface Props {
  value: ResponsiveOverrides;
  onChange: (v: ResponsiveOverrides) => void;
}

export function ResponsivePanel({ value, onChange }: Props) {

  const hideOn = value.hideOn || [];
  const toggleHide = (d: 'desktop' | 'tablet' | 'mobile') => {
    const next = hideOn.includes(d) ? hideOn.filter(x => x !== d) : [...hideOn, d];
    onChange({ ...value, hideOn: next.length > 0 ? next : undefined });
  };

  return (
    <div className="space-y-3">
      <div>
        <label className="text-[10px] text-base-content/40 mb-1 block">Hide on device</label>
        <div className="flex gap-1">
          {([
            { key: 'desktop' as const, icon: Monitor, label: 'Desktop' },
            { key: 'tablet' as const, icon: Tablet, label: 'Tablet' },
            { key: 'mobile' as const, icon: Smartphone, label: 'Mobile' },
          ]).map(d => (
            <button key={d.key} onClick={() => toggleHide(d.key)}
              className={`btn btn-xs flex-1 gap-1 text-[10px] ${hideOn.includes(d.key) ? 'btn-error btn-outline' : 'btn-ghost'}`}>
              <d.icon size={11} /> {d.label}
            </button>
          ))}
        </div>
      </div>

      <div className="p-2 bg-base-200/50 rounded text-[10px] text-base-content/30">
        <p>Responsive overrides let you change selected layout properties per breakpoint.</p>
        <p className="mt-1">Tablet: ≤1024px | Mobile: ≤640px</p>
        <p className="mt-1">Currently supported for Hero block pilot: text alignment, section height, content max width. More properties and blocks coming soon.</p>
      </div>
    </div>
  );
}
