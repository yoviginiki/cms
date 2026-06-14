/**
 * Sprint 6 — Theme token normalization and validation helpers.
 * Used by admin UI for theme preview and validation.
 */

export interface ThemeTokens {
  colors: {
    primary: string;
    secondary: string;
    accent: string;
    background: string;
    surface: string;
    text: string;
    muted: string;
    border: string;
  };
  typography: {
    headingFont: string;
    bodyFont: string;
    baseSize: string;
    scale: number;
  };
  spacing: {
    sectionPadding: string;
    containerWidth: string;
    blockGap: string;
  };
  radius: {
    small: string;
    medium: string;
    large: string;
  };
  components: {
    buttonStyle: 'rounded' | 'pill' | 'square';
    cardStyle: 'bordered' | 'shadow' | 'flat';
    headerStyle: 'simple' | 'centered' | 'split';
    footerStyle: 'simple' | 'columns' | 'minimal';
  };
}

const DEFAULTS: ThemeTokens = {
  colors: {
    primary: '#3b82f6',
    secondary: '#6366f1',
    accent: '#f59e0b',
    background: '#ffffff',
    surface: '#f8fafc',
    text: '#1e293b',
    muted: '#94a3b8',
    border: '#e2e8f0',
  },
  typography: {
    headingFont: 'Georgia, serif',
    bodyFont: 'system-ui, sans-serif',
    baseSize: '16px',
    scale: 1.25,
  },
  spacing: {
    sectionPadding: '4rem',
    containerWidth: '1200px',
    blockGap: '2rem',
  },
  radius: {
    small: '0.25rem',
    medium: '0.5rem',
    large: '1rem',
  },
  components: {
    buttonStyle: 'rounded',
    cardStyle: 'bordered',
    headerStyle: 'simple',
    footerStyle: 'simple',
  },
};

/** Normalize a partial token object to full ThemeTokens with safe defaults */
export function normalizeThemeTokens(raw: any): ThemeTokens {
  if (!raw || typeof raw !== 'object') return JSON.parse(JSON.stringify(DEFAULTS));

  return {
    colors: {
      primary: safeColor(raw.colors?.primary) || DEFAULTS.colors.primary,
      secondary: safeColor(raw.colors?.secondary) || DEFAULTS.colors.secondary,
      accent: safeColor(raw.colors?.accent) || DEFAULTS.colors.accent,
      background: safeColor(raw.colors?.background) || DEFAULTS.colors.background,
      surface: safeColor(raw.colors?.surface) || DEFAULTS.colors.surface,
      text: safeColor(raw.colors?.text) || DEFAULTS.colors.text,
      muted: safeColor(raw.colors?.muted) || DEFAULTS.colors.muted,
      border: safeColor(raw.colors?.border) || DEFAULTS.colors.border,
    },
    typography: {
      headingFont: typeof raw.typography?.headingFont === 'string' ? raw.typography.headingFont : DEFAULTS.typography.headingFont,
      bodyFont: typeof raw.typography?.bodyFont === 'string' ? raw.typography.bodyFont : DEFAULTS.typography.bodyFont,
      baseSize: safeDim(raw.typography?.baseSize) || DEFAULTS.typography.baseSize,
      scale: typeof raw.typography?.scale === 'number' ? Math.max(1, Math.min(2, raw.typography.scale)) : DEFAULTS.typography.scale,
    },
    spacing: {
      sectionPadding: safeDim(raw.spacing?.sectionPadding) || DEFAULTS.spacing.sectionPadding,
      containerWidth: safeDim(raw.spacing?.containerWidth) || DEFAULTS.spacing.containerWidth,
      blockGap: safeDim(raw.spacing?.blockGap) || DEFAULTS.spacing.blockGap,
    },
    radius: {
      small: safeDim(raw.radius?.small) || DEFAULTS.radius.small,
      medium: safeDim(raw.radius?.medium) || DEFAULTS.radius.medium,
      large: safeDim(raw.radius?.large) || DEFAULTS.radius.large,
    },
    components: {
      buttonStyle: ['rounded', 'pill', 'square'].includes(raw.components?.buttonStyle) ? raw.components.buttonStyle : DEFAULTS.components.buttonStyle,
      cardStyle: ['bordered', 'shadow', 'flat'].includes(raw.components?.cardStyle) ? raw.components.cardStyle : DEFAULTS.components.cardStyle,
      headerStyle: ['simple', 'centered', 'split'].includes(raw.components?.headerStyle) ? raw.components.headerStyle : DEFAULTS.components.headerStyle,
      footerStyle: ['simple', 'columns', 'minimal'].includes(raw.components?.footerStyle) ? raw.components.footerStyle : DEFAULTS.components.footerStyle,
    },
  };
}

/** Generate CSS variables from theme tokens */
export function tokensToCssVars(tokens: ThemeTokens): Record<string, string> {
  return {
    '--cms-color-primary': tokens.colors.primary,
    '--cms-color-secondary': tokens.colors.secondary,
    '--cms-color-accent': tokens.colors.accent,
    '--cms-color-background': tokens.colors.background,
    '--cms-color-surface': tokens.colors.surface,
    '--cms-color-text': tokens.colors.text,
    '--cms-color-muted': tokens.colors.muted,
    '--cms-color-border': tokens.colors.border,
    '--cms-font-heading': tokens.typography.headingFont,
    '--cms-font-body': tokens.typography.bodyFont,
    '--cms-font-size-base': tokens.typography.baseSize,
    '--cms-font-scale': String(tokens.typography.scale),
    '--cms-section-padding': tokens.spacing.sectionPadding,
    '--cms-container-width': tokens.spacing.containerWidth,
    '--cms-block-gap': tokens.spacing.blockGap,
    '--cms-radius-small': tokens.radius.small,
    '--cms-radius-medium': tokens.radius.medium,
    '--cms-radius-large': tokens.radius.large,
  };
}

/** Validate a theme manifest structure. Returns array of error messages. */
export function validateThemeManifest(manifest: any): string[] {
  const errors: string[] = [];
  if (!manifest || typeof manifest !== 'object') {
    errors.push('Manifest must be an object');
    return errors;
  }
  if (!manifest.$metadata) errors.push('Missing $metadata');
  if (manifest.$metadata && !manifest.$metadata.name) errors.push('Missing $metadata.name');
  if (!manifest.primitive && !manifest.semantic) errors.push('Must have at least primitive or semantic tokens');
  return errors;
}

function safeColor(v: any): string {
  if (typeof v !== 'string') return '';
  if (/^#[0-9a-fA-F]{3,8}$/.test(v)) return v;
  if (/^rgba?\([\d\s,.]+\)$/.test(v)) return v;
  return '';
}

function safeDim(v: any): string {
  if (typeof v !== 'string') return '';
  if (/^-?\d+(\.\d+)?(px|rem|em|%|vh|vw)$/.test(v)) return v;
  return '';
}

export { DEFAULTS as THEME_DEFAULTS };
