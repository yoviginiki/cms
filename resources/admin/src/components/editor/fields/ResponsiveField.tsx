import { Monitor, Tablet, Smartphone, RotateCcw } from 'lucide-react';
import type { Breakpoint } from '@/lib/responsiveValues';
import { hasResponsiveOverride } from '@/lib/responsiveValues';

interface ResponsiveFieldProps {
  /** Block data object. */
  data: Record<string, unknown>;
  /** The data key this field controls. */
  dataKey: string;
  /** Label displayed above the field. */
  label: string;
  /** Called when user clears an override (resets to inherited). */
  onClearOverride: (breakpoint: 'tablet' | 'mobile') => void;
  /** Current selected breakpoint. */
  breakpoint: Breakpoint;
  /** Called when breakpoint changes. */
  onBreakpointChange: (bp: Breakpoint) => void;
  /** The actual field control to render. */
  children: React.ReactNode;
}

const BP_ICONS = {
  desktop: Monitor,
  tablet: Tablet,
  mobile: Smartphone,
} as const;

const BP_LABELS: Record<Breakpoint, string> = {
  desktop: 'Desktop',
  tablet: 'Tablet',
  mobile: 'Mobile',
};

/**
 * Wrapper that adds breakpoint selector and override indicator to any field.
 *
 * Shows:
 * - breakpoint toggle (Desktop / Tablet / Mobile)
 * - "overridden" indicator when tablet/mobile has an explicit value
 * - "Reset" button to clear override and inherit from larger breakpoint
 *
 * Admin-only component. Does not affect published output.
 */
export function ResponsiveField({
  data,
  dataKey,
  label,
  onClearOverride,
  breakpoint,
  onBreakpointChange,
  children,
}: ResponsiveFieldProps) {
  const isOverridden = breakpoint !== 'desktop' && hasResponsiveOverride(data, dataKey, breakpoint);

  return (
    <div className="responsive-field">
      <div className="flex items-center justify-between mb-1">
        <label className="block text-[11px] font-medium text-base-content/50">{label}</label>
        <div className="flex items-center gap-0.5">
          {(['desktop', 'tablet', 'mobile'] as const).map((bp) => {
            const Icon = BP_ICONS[bp];
            const isActive = breakpoint === bp;
            const hasOverride = bp !== 'desktop' && hasResponsiveOverride(data, dataKey, bp);
            return (
              <button
                key={bp}
                type="button"
                onClick={() => onBreakpointChange(bp)}
                className={`p-0.5 rounded transition-colors ${
                  isActive
                    ? 'bg-primary/10 text-primary'
                    : hasOverride
                      ? 'text-warning/60 hover:text-warning'
                      : 'text-base-content/20 hover:text-base-content/40'
                }`}
                title={`${BP_LABELS[bp]}${hasOverride ? ' (overridden)' : ''}`}
                aria-label={`Edit ${label} for ${BP_LABELS[bp]}`}
              >
                <Icon size={11} />
              </button>
            );
          })}
        </div>
      </div>

      {children}

      {isOverridden && (
        <button
          type="button"
          onClick={() => onClearOverride(breakpoint as 'tablet' | 'mobile')}
          className="flex items-center gap-1 mt-0.5 text-[9px] text-warning hover:text-warning/80 transition-colors"
          title={`Reset to ${breakpoint === 'mobile' ? 'tablet/desktop' : 'desktop'} value`}
        >
          <RotateCcw size={8} />
          Reset {BP_LABELS[breakpoint]} override
        </button>
      )}

      {breakpoint !== 'desktop' && !isOverridden && (
        <span className="block mt-0.5 text-[9px] text-base-content/25">
          Inherited from {breakpoint === 'mobile' ? 'tablet/desktop' : 'desktop'}
        </span>
      )}
    </div>
  );
}
