import React from 'react';
import { LAYOUT_GRID, LAYOUT_LABELS, type RowLayout } from './definition';

/**
 * Parse a grid-template-columns value like "1fr 2fr" into proportional weights [1, 2].
 * Falls back to a single full-width column when nothing parses.
 */
export function proportions(gridTemplate: string): number[] {
  const parts = gridTemplate
    .trim()
    .split(/\s+/)
    .map((tok) => parseFloat(tok))
    .filter((n) => Number.isFinite(n) && n > 0);
  return parts.length ? parts : [1];
}

interface RowLayoutPickerProps {
  value: string;
  onChange: (layout: RowLayout) => void;
}

/**
 * Visual column-split picker for Row blocks. Each option renders a proportional
 * diagram of its columns so the split is legible at a glance. Switching layout
 * only changes the `layout` field — children (module content) are preserved,
 * since the Blade/Preview render them into CSS-grid cells.
 */
export const RowLayoutPicker: React.FC<RowLayoutPickerProps> = ({ value, onChange }) => {
  const options = Object.keys(LAYOUT_LABELS) as RowLayout[];

  return (
    <div>
      <label className="text-[11px] text-base-content/50 mb-1.5 block">Column Layout</label>
      <div className="grid grid-cols-2 gap-1.5" role="radiogroup" aria-label="Column layout">
        {options.map((layout) => {
          const isActive = value === layout;
          const cols = proportions(LAYOUT_GRID[layout] || '1fr');
          return (
            <button
              key={layout}
              type="button"
              role="radio"
              aria-checked={isActive}
              title={LAYOUT_LABELS[layout]}
              onClick={() => onChange(layout)}
              className={`flex flex-col gap-1 p-1.5 border transition-colors outline-none focus-visible:ring-1 focus-visible:ring-primary ${
                isActive
                  ? 'border-primary bg-primary/5'
                  : 'border-base-300/30 hover:border-base-content/30 hover:bg-base-300/5'
              }`}
            >
              <div className="flex gap-0.5 h-6 w-full" aria-hidden="true">
                {cols.map((w, i) => (
                  <div
                    key={i}
                    style={{ flexGrow: w }}
                    className={isActive ? 'bg-primary/60' : 'bg-base-content/20'}
                  />
                ))}
              </div>
              <span
                className={`text-[9px] leading-tight truncate ${
                  isActive ? 'text-primary' : 'text-base-content/40'
                }`}
              >
                {LAYOUT_LABELS[layout]}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
};
