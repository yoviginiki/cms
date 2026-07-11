import { useId } from 'react';

/**
 * Color input with theme-token binding: pick a semantic token (emitted as
 * var(--color-*) — resolved by the published page's DesignTokenGenerator CSS)
 * or a literal color. BlockStyle::safeColor accepts the var() pattern
 * end-to-end, so token values survive the publish pipeline.
 */
// Semantic color tokens actually emitted by DesignTokenGenerator (kept in sync
// with the theme's :root vars — publish resolves these, so preview + output match).
const TOKENS: { name: string; label: string }[] = [
  { name: 'var(--color-primary)', label: 'Primary' },
  { name: 'var(--color-accent)', label: 'Accent' },
  { name: 'var(--color-text)', label: 'Text' },
  { name: 'var(--color-heading)', label: 'Heading' },
  { name: 'var(--color-text-muted)', label: 'Muted' },
  { name: 'var(--color-bg)', label: 'Background' },
  { name: 'var(--color-bg-alt)', label: 'Surface' },
  { name: 'var(--color-border)', label: 'Border' },
];

interface TokenColorInputProps {
  label: string;
  value: string;
  onChange: (val: string) => void;
}

export function TokenColorInput({ label, value, onChange }: TokenColorInputProps) {
  const id = useId();
  const isToken = (value || '').startsWith('var(');
  const activeToken = TOKENS.find(t => t.name === value);

  return (
    <div>
      <label htmlFor={id} className="text-[10px] text-base-content/40">{label}</label>
      <div className="flex items-center gap-1.5 mt-0.5">
        <input
          type="color"
          value={isToken ? '#888888' : (value || '#000000')}
          disabled={isToken}
          onChange={(e) => onChange(e.target.value)}
          className={`h-7 w-7 cursor-pointer border border-base-300 p-0.5 ${isToken ? 'opacity-30' : ''}`}
          aria-label={`${label} color picker`}
        />
        <input
          id={id}
          type="text"
          value={value || ''}
          onChange={(e) => onChange(e.target.value)}
          placeholder="#000000 or var(--color-primary)"
          className="flex-1 min-w-0 border border-base-300 rounded px-2 py-1 text-xs bg-base-100"
        />
        {isToken && (
          <button type="button" onClick={() => onChange('')}
            className="text-[9px] px-1.5 py-1 badge badge-primary badge-outline shrink-0"
            title="Clear token binding">
            {activeToken?.label ?? 'token'} ✕
          </button>
        )}
      </div>
      <div className="flex flex-wrap gap-1 mt-1">
        {TOKENS.map(t => (
          <button key={t.name} type="button"
            onClick={() => onChange(t.name)}
            className={`text-[9px] px-1.5 py-0.5 border ${value === t.name
              ? 'border-primary text-primary bg-primary/10'
              : 'border-base-300 text-base-content/50 hover:border-base-content/40'}`}
            title={t.name}>
            {t.label}
          </button>
        ))}
      </div>
    </div>
  );
}
