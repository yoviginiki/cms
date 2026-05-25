/**
 * BLOCK-EFFECTS-1 — Global Card/Image Hover Effects + Image Filters.
 *
 * Reusable helpers for blocks that render cards/images.
 * Used by both editor Preview and Blade rendering (parity maintained via shared presets).
 */

// ═══════════════════════════════════════
// Schema types
// ═══════════════════════════════════════

export interface CardEffects {
  enabled?: boolean;
  hover?: CardHoverEffects;
  imageFilter?: ImageFilterEffects;
  overlay?: ImageOverlayEffects;
  imageHoverReveal?: ImageHoverRevealEffects;
}

export interface CardHoverEffects {
  enabled?: boolean;
  preset?: HoverPreset;
  scale?: number;
  translateY?: number;
  shadow?: 'none' | 'soft' | 'medium' | 'strong';
  duration?: number;
  easing?: 'ease' | 'ease-out' | 'ease-in-out';
}

export interface ImageFilterEffects {
  enabled?: boolean;
  preset?: FilterPreset;
  grayscale?: number;
  sepia?: number;
  brightness?: number;
  contrast?: number;
  saturation?: number;
}

export interface ImageOverlayEffects {
  enabled?: boolean;
  color?: string;
  opacity?: number;
  blendMode?: 'normal' | 'multiply' | 'screen' | 'overlay' | 'soft-light';
}

export interface ImageHoverRevealEffects {
  enabled?: boolean;
  mode?: RevealMode;
  duration?: number;
  easing?: 'ease' | 'ease-out' | 'ease-in-out';
}

export type RevealMode = 'none' | 'fade' | 'reveal-left' | 'reveal-right' | 'reveal-top' | 'reveal-bottom' | 'circle' | 'diagonal';

export const REVEAL_MODES: RevealMode[] = ['none', 'fade', 'reveal-left', 'reveal-right', 'reveal-top', 'reveal-bottom', 'circle', 'diagonal'];

export type HoverPreset = 'none' | 'lift' | 'scale' | 'lift-scale' | 'soft-pop' | 'strong-pop';
export type FilterPreset = 'none' | 'grayscale' | 'sepia' | 'muted' | 'high-contrast' | 'custom';

// ═══════════════════════════════════════
// Hover presets
// ═══════════════════════════════════════

export const HOVER_PRESETS: Record<HoverPreset, { scale: number; translateY: number; shadow: string }> = {
  'none': { scale: 1, translateY: 0, shadow: 'none' },
  'lift': { scale: 1, translateY: -6, shadow: 'medium' },
  'scale': { scale: 1.03, translateY: 0, shadow: 'soft' },
  'lift-scale': { scale: 1.02, translateY: -4, shadow: 'medium' },
  'soft-pop': { scale: 1.02, translateY: -3, shadow: 'soft' },
  'strong-pop': { scale: 1.05, translateY: -8, shadow: 'strong' },
};

const SHADOW_VALUES: Record<string, string> = {
  'none': 'none',
  'soft': '0 4px 12px rgba(0,0,0,0.08)',
  'medium': '0 8px 24px rgba(0,0,0,0.12)',
  'strong': '0 16px 40px rgba(0,0,0,0.18)',
};

// ═══════════════════════════════════════
// Filter presets
// ═══════════════════════════════════════

export const FILTER_PRESETS: Record<FilterPreset, { grayscale: number; sepia: number; brightness: number; contrast: number; saturation: number }> = {
  'none': { grayscale: 0, sepia: 0, brightness: 100, contrast: 100, saturation: 100 },
  'grayscale': { grayscale: 100, sepia: 0, brightness: 100, contrast: 100, saturation: 100 },
  'sepia': { grayscale: 0, sepia: 80, brightness: 100, contrast: 100, saturation: 100 },
  'muted': { grayscale: 0, sepia: 0, brightness: 95, contrast: 90, saturation: 60 },
  'high-contrast': { grayscale: 0, sepia: 0, brightness: 105, contrast: 130, saturation: 110 },
  'custom': { grayscale: 0, sepia: 0, brightness: 100, contrast: 100, saturation: 100 },
};

// ═══════════════════════════════════════
// Normalize
// ═══════════════════════════════════════

export function normalizeCardEffects(raw: any): CardEffects {
  if (!raw || typeof raw !== 'object') return { enabled: false };
  return {
    enabled: !!raw.enabled,
    hover: raw.hover ? {
      enabled: !!raw.hover.enabled,
      preset: HOVER_PRESETS[raw.hover?.preset as HoverPreset] ? raw.hover.preset : 'none',
      scale: clamp(raw.hover?.scale ?? 1, 1, 1.2),
      translateY: clamp(raw.hover?.translateY ?? 0, -40, 0),
      shadow: ['none', 'soft', 'medium', 'strong'].includes(raw.hover?.shadow) ? raw.hover.shadow : 'none',
      duration: clamp(raw.hover?.duration ?? 300, 100, 1000),
      easing: ['ease', 'ease-out', 'ease-in-out'].includes(raw.hover?.easing) ? raw.hover.easing : 'ease-out',
    } : undefined,
    imageFilter: raw.imageFilter ? {
      enabled: !!raw.imageFilter.enabled,
      preset: FILTER_PRESETS[raw.imageFilter?.preset as FilterPreset] ? raw.imageFilter.preset : 'none',
      grayscale: clamp(raw.imageFilter?.grayscale ?? 0, 0, 100),
      sepia: clamp(raw.imageFilter?.sepia ?? 0, 0, 100),
      brightness: clamp(raw.imageFilter?.brightness ?? 100, 50, 200),
      contrast: clamp(raw.imageFilter?.contrast ?? 100, 50, 200),
      saturation: clamp(raw.imageFilter?.saturation ?? 100, 0, 200),
    } : undefined,
    overlay: raw.overlay ? {
      enabled: !!raw.overlay.enabled,
      color: safeColor(raw.overlay?.color || '#000000'),
      opacity: clamp(raw.overlay?.opacity ?? 30, 0, 100),
      blendMode: ['normal', 'multiply', 'screen', 'overlay', 'soft-light'].includes(raw.overlay?.blendMode) ? raw.overlay.blendMode : 'normal',
    } : undefined,
    imageHoverReveal: raw.imageHoverReveal ? {
      enabled: !!raw.imageHoverReveal.enabled,
      mode: REVEAL_MODES.includes(raw.imageHoverReveal?.mode) ? raw.imageHoverReveal.mode : 'none',
      duration: clamp(raw.imageHoverReveal?.duration ?? 500, 150, 1500),
      easing: ['ease', 'ease-out', 'ease-in-out'].includes(raw.imageHoverReveal?.easing) ? raw.imageHoverReveal.easing : 'ease-out',
    } : undefined,
  };
}

// ═══════════════════════════════════════
// CSS builders (used by Preview and can be replicated in Blade)
// ═══════════════════════════════════════

/** Build inline style for card wrapper (base state — no hover) */
export function buildCardBaseStyle(effects: CardEffects): React.CSSProperties {
  if (!effects.enabled || !effects.hover?.enabled) return {};
  const h = effects.hover;
  const duration = h.duration ?? 300;
  const easing = h.easing ?? 'ease-out';
  return {
    transition: `transform ${duration}ms ${easing}, box-shadow ${duration}ms ${easing}`,
    willChange: 'transform, box-shadow',
  };
}

/** Build inline style for card wrapper hover state */
export function buildCardHoverStyle(effects: CardEffects): React.CSSProperties {
  if (!effects.enabled || !effects.hover?.enabled) return {};
  const h = effects.hover;
  const preset = HOVER_PRESETS[h.preset || 'none'];
  const scale = h.scale ?? preset.scale;
  const translateY = h.translateY ?? preset.translateY;
  const shadow = SHADOW_VALUES[h.shadow || preset.shadow] || 'none';

  const transforms: string[] = [];
  if (translateY !== 0) transforms.push(`translateY(${translateY}px)`);
  if (scale !== 1) transforms.push(`scale(${scale})`);

  return {
    transform: transforms.length > 0 ? transforms.join(' ') : undefined,
    boxShadow: shadow !== 'none' ? shadow : undefined,
  };
}

/** Build CSS filter string for image */
export function buildImageFilterCss(effects: CardEffects): string {
  if (!effects.enabled || !effects.imageFilter?.enabled) return '';
  const f = effects.imageFilter;
  const preset = FILTER_PRESETS[f.preset || 'none'];
  const gs = f.preset === 'custom' ? (f.grayscale ?? 0) : preset.grayscale;
  const sp = f.preset === 'custom' ? (f.sepia ?? 0) : preset.sepia;
  const br = f.preset === 'custom' ? (f.brightness ?? 100) : preset.brightness;
  const ct = f.preset === 'custom' ? (f.contrast ?? 100) : preset.contrast;
  const st = f.preset === 'custom' ? (f.saturation ?? 100) : preset.saturation;

  const parts: string[] = [];
  if (gs > 0) parts.push(`grayscale(${gs}%)`);
  if (sp > 0) parts.push(`sepia(${sp}%)`);
  if (br !== 100) parts.push(`brightness(${br}%)`);
  if (ct !== 100) parts.push(`contrast(${ct}%)`);
  if (st !== 100) parts.push(`saturate(${st}%)`);

  return parts.length > 0 ? parts.join(' ') : '';
}

/** Build overlay style object */
export function buildOverlayStyle(effects: CardEffects): React.CSSProperties | null {
  if (!effects.enabled || !effects.overlay?.enabled) return null;
  const o = effects.overlay;
  return {
    position: 'absolute',
    inset: 0,
    backgroundColor: o.color || '#000000',
    opacity: (o.opacity ?? 30) / 100,
    mixBlendMode: o.blendMode || 'normal',
    pointerEvents: 'none',
    borderRadius: 'inherit',
  };
}

// ═══════════════════════════════════════
// Image Hover Reveal
// ═══════════════════════════════════════

/** Check if hover reveal is active */
export function isRevealEnabled(effects: CardEffects): boolean {
  return !!effects.enabled && !!effects.imageHoverReveal?.enabled &&
    effects.imageHoverReveal.mode !== 'none' && !!effects.imageFilter?.enabled;
}

/** Get the clip-path for the filtered overlay in its DEFAULT (visible) state */
function getRevealClipDefault(mode: RevealMode): string {
  switch (mode) {
    case 'reveal-left': return 'inset(0 0 0 0)';
    case 'reveal-right': return 'inset(0 0 0 0)';
    case 'reveal-top': return 'inset(0 0 0 0)';
    case 'reveal-bottom': return 'inset(0 0 0 0)';
    case 'circle': return 'circle(100% at 50% 50%)';
    case 'diagonal': return 'polygon(0 0, 100% 0, 100% 100%, 0 100%)';
    default: return '';
  }
}

/** Get the clip-path for the filtered overlay on HOVER (hidden/revealing original) */
function getRevealClipHover(mode: RevealMode): string {
  switch (mode) {
    case 'reveal-left': return 'inset(0 100% 0 0)';
    case 'reveal-right': return 'inset(0 0 0 100%)';
    case 'reveal-top': return 'inset(100% 0 0 0)';
    case 'reveal-bottom': return 'inset(0 0 100% 0)';
    case 'circle': return 'circle(0% at 50% 50%)';
    case 'diagonal': return 'polygon(0 0, 0 0, 0 100%, 0 100%)';
    default: return '';
  }
}

/** Build filtered overlay layer style (default state — filter visible) */
export function buildRevealFilteredStyle(effects: CardEffects): React.CSSProperties {
  if (!isRevealEnabled(effects)) return {};
  const r = effects.imageHoverReveal!;
  const mode = r.mode || 'fade';
  const duration = r.duration ?? 500;
  const easing = r.easing ?? 'ease-out';
  const filterCss = buildImageFilterCss(effects);
  const clipDefault = getRevealClipDefault(mode);

  return {
    position: 'absolute',
    inset: 0,
    width: '100%',
    height: '100%',
    objectFit: 'cover' as const,
    filter: filterCss || undefined,
    opacity: 1,
    clipPath: clipDefault || undefined,
    transition: mode === 'fade'
      ? `opacity ${duration}ms ${easing}`
      : `clip-path ${duration}ms ${easing}, opacity ${duration}ms ${easing}`,
    pointerEvents: 'none',
    zIndex: 1,
  };
}

/** Build filtered overlay layer style on HOVER (filter disappearing) */
export function buildRevealFilteredHoverStyle(effects: CardEffects): React.CSSProperties {
  if (!isRevealEnabled(effects)) return {};
  const mode = effects.imageHoverReveal!.mode || 'fade';
  const clipHover = getRevealClipHover(mode);

  if (mode === 'fade') {
    return { opacity: 0 };
  }
  return {
    clipPath: clipHover || undefined,
    opacity: clipHover ? undefined : 0,
  };
}

// ═══════════════════════════════════════
// Helpers
// ═══════════════════════════════════════

function clamp(val: number, min: number, max: number): number {
  return Math.max(min, Math.min(max, Number(val) || min));
}

function safeColor(color: string): string {
  if (/^#[0-9a-fA-F]{3,8}$/.test(color)) return color;
  if (/^rgba?\([\d\s.,]+\)$/.test(color)) return color;
  return '#000000';
}
