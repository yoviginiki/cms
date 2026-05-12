/**
 * Shadow Style Helpers
 *
 * Shared shadow generation for both preset and custom shadows.
 * Used by Hero (block.data.sectionShadow*) and BaseBlock (block.style.visual.boxShadow).
 *
 * Security: CSS is ALWAYS generated from structured values, never from raw user strings.
 */

// ── Safe CSS validators (reuse from blockStyles if imported, or local copies) ──
const DIM_RE = /^-?\d+(\.\d+)?(px|rem|em)$/;
const COLOR_RE = /^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s,./%]+\)|oklch\([\d\s,./%]+\))$/;

function safeDim(v: string): string {
  return DIM_RE.test(v.trim()) ? v.trim() : '';
}
function safeColor(v: string): string {
  return COLOR_RE.test(v.trim()) ? v.trim() : '';
}

// ── Types ──

export type ShadowMode = 'preset' | 'custom';

export type ShadowPreset = '' | 'none' | 'subtle' | 'sm' | 'medium' | 'md' | 'large' | 'lg' | 'glow';

export interface ShadowCustom {
  x?: string;
  y?: string;
  blur?: string;
  spread?: string;
  color?: string;
  opacity?: number;
  inset?: boolean;
}

export interface ShadowData {
  shadowMode?: ShadowMode;
  shadowPreset?: ShadowPreset;
  shadowCustom?: ShadowCustom;
  // Legacy keys
  sectionShadow?: string;
  boxShadow?: string;
}

// ── Unified Preset Map ──
// Accepts both naming conventions (sm/md/lg and subtle/medium/large/glow)
export const SHADOW_PRESETS: Record<string, string> = {
  // Hero-style presets
  subtle: '0 1px 3px rgba(0,0,0,0.12)',
  medium: '0 8px 24px rgba(0,0,0,0.18)',
  large:  '0 20px 40px rgba(0,0,0,0.24)',
  glow:   '0 0 30px rgba(255,255,255,0.35)',
  // BaseBlock-style presets (kept for backward compat)
  sm: '0 1px 2px rgba(0,0,0,0.04)',
  md: '0 4px 12px rgba(0,0,0,0.06)',
  lg: '0 12px 32px rgba(0,0,0,0.10)',
};

// ── Hex to RGBA helper ──
function hexToRgba(hex: string, alpha: number): string {
  const clean = hex.replace('#', '');
  let r = 0, g = 0, b = 0;
  if (clean.length === 3) {
    r = parseInt(clean[0] + clean[0], 16);
    g = parseInt(clean[1] + clean[1], 16);
    b = parseInt(clean[2] + clean[2], 16);
  } else if (clean.length >= 6) {
    r = parseInt(clean.slice(0, 2), 16);
    g = parseInt(clean.slice(2, 4), 16);
    b = parseInt(clean.slice(4, 6), 16);
  }
  return `rgba(${r},${g},${b},${alpha.toFixed(2)})`;
}

// ── Build shadow CSS string from structured data ──

/**
 * Generate a safe CSS box-shadow string.
 *
 * @param mode - 'preset' or 'custom'
 * @param preset - preset name (subtle/medium/large/glow/sm/md/lg)
 * @param custom - structured custom values (optional)
 * @returns CSS box-shadow string, or empty string for no shadow
 */
export function buildShadowCss(
  mode?: string,
  preset?: string,
  custom?: ShadowCustom,
): string {
  // Default to preset mode
  const effectiveMode = mode === 'custom' ? 'custom' : 'preset';

  if (effectiveMode === 'custom' && custom) {
    const x = safeDim(custom.x || '') || '0px';
    const y = safeDim(custom.y || '') || '4px';
    // Blur must be non-negative (CSS spec)
    const rawBlur = safeDim(custom.blur || '') || '12px';
    const blur = rawBlur.startsWith('-') ? '12px' : rawBlur;
    const spread = safeDim(custom.spread || '') || '0px';
    const color = safeColor(custom.color || '') || '#000000';
    const rawOpacity = Number(custom.opacity ?? 15);
    const alpha = Number.isFinite(rawOpacity) ? Math.max(0, Math.min(100, rawOpacity)) / 100 : 0.15;
    const inset = custom.inset ? 'inset ' : '';

    // Convert hex to rgba with opacity
    const rgba = color.startsWith('#') ? hexToRgba(color, alpha) : color;
    return `${inset}${x} ${y} ${blur} ${spread} ${rgba}`;
  }

  // Preset mode
  if (preset && preset !== 'none' && preset !== '') {
    return SHADOW_PRESETS[preset] || '';
  }

  return '';
}

/**
 * Build React.CSSProperties with boxShadow.
 */
export function buildShadowStyle(
  mode?: string,
  preset?: string,
  custom?: ShadowCustom,
): React.CSSProperties {
  const css = buildShadowCss(mode, preset, custom);
  return css ? { boxShadow: css } : {};
}
