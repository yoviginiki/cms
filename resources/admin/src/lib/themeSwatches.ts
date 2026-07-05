// ═══════════════════════════════════════════════════════════════════════════
// Theme-token swatches (W3): pull the site's REAL palette out of a theme
// config so magazine color fields offer brand colors instead of naked hex.
// Handles both flat tokens ('color-primary': '#e63b2e') and W3C document
// tokens ('semantic.color.brand': { $type:'color', $value:'#e63b2e' }).
// ═══════════════════════════════════════════════════════════════════════════

export interface Swatch {
  name: string;
  value: string;
}

const HEX = /^#[0-9a-fA-F]{3,8}$/;

const prettify = (key: string) =>
  key
    .replace(/^semantic\.|^color[-.]/, '')
    .replace(/[-._]/g, ' ')
    .replace(/\bcolor\b/g, '')
    .trim()
    .replace(/\s+/g, ' ') || key;

export function extractColorSwatches(themeConfig: unknown): Swatch[] {
  const tokens = (themeConfig as any)?.tokens;
  if (!tokens || typeof tokens !== 'object') return [];
  const out: Swatch[] = [];
  const seen = new Set<string>();
  for (const [key, raw] of Object.entries(tokens as Record<string, unknown>)) {
    if (!/color/i.test(key)) continue;
    const value = typeof raw === 'string' ? raw : (raw as any)?.$value;
    if (typeof value !== 'string' || !HEX.test(value)) continue;
    const norm = value.toLowerCase();
    if (seen.has(norm)) continue;
    seen.add(norm);
    out.push({ name: prettify(key), value });
  }
  return out;
}

/** sensible fallback when the site has no theme yet */
export const DEFAULT_SWATCHES: Swatch[] = [
  { name: 'black', value: '#111111' },
  { name: 'white', value: '#ffffff' },
  { name: 'gray', value: '#6b7280' },
  { name: 'red', value: '#e63b2e' },
  { name: 'blue', value: '#2563eb' },
  { name: 'green', value: '#16a34a' },
  { name: 'amber', value: '#f59e0b' },
];

const RECENT_KEY = 'mag-recent-colors';
const RECENT_MAX = 8;

export function recentColors(): string[] {
  try {
    const v = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
    return Array.isArray(v) ? v.filter((c) => typeof c === 'string' && HEX.test(c)).slice(0, RECENT_MAX) : [];
  } catch {
    return [];
  }
}

export function pushRecentColor(color: string): void {
  if (!HEX.test(color)) return;
  try {
    const next = [color, ...recentColors().filter((c) => c.toLowerCase() !== color.toLowerCase())].slice(0, RECENT_MAX);
    localStorage.setItem(RECENT_KEY, JSON.stringify(next));
  } catch {
    /* storage unavailable — recents are a nicety */
  }
}
