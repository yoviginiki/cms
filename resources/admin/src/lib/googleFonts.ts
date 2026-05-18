/**
 * Curated Google Fonts Catalog
 *
 * ~80 hand-picked fonts covering sans-serif, serif, display, monospace.
 * Only selected fonts are loaded via Google Fonts CSS2 API.
 *
 * Privacy/Performance notes:
 * - Google Fonts are loaded from fonts.googleapis.com (third-party request)
 * - Only fonts actually selected by the admin are loaded (not this full list)
 * - For GDPR compliance, consider self-hosting fonts via the theme's woff2_url field
 * - All fonts use display=swap for non-blocking rendering
 * - Variable fonts are preferred where available (single file, all weights)
 */

export interface GoogleFont {
  family: string;           // Human-readable name (e.g., "Open Sans")
  category: FontCategory;   // sans-serif, serif, display, monospace
  weights: number[];        // Available weights (e.g., [300, 400, 500, 600, 700])
  variable?: boolean;       // Whether it supports variable font axis
  popular?: boolean;        // Highlight as popular pick
}

export type FontCategory = 'sans-serif' | 'serif' | 'display' | 'monospace';

/**
 * Build a Google Fonts CSS2 URL for a specific font + weights.
 */
export function buildGoogleFontUrl(family: string, weights: number[] = [400, 700]): string {
  const encodedFamily = encodeURIComponent(family);
  const wghtAxis = weights.sort((a, b) => a - b).join(';');
  return `https://fonts.googleapis.com/css2?family=${encodedFamily}:wght@${wghtAxis}&display=swap`;
}

/**
 * Build a <link> tag string for font preloading.
 */
export function buildFontPreviewUrl(family: string): string {
  return buildGoogleFontUrl(family, [400, 700]);
}

/**
 * Build a CSS font-family value with proper fallbacks.
 */
export function buildFontStack(family: string, category: FontCategory): string {
  const fallbacks: Record<FontCategory, string> = {
    'sans-serif': "system-ui, -apple-system, sans-serif",
    'serif': "Georgia, 'Times New Roman', serif",
    'display': "system-ui, sans-serif",
    'monospace': "'SF Mono', 'Fira Code', monospace",
  };
  return `'${family}', ${fallbacks[category]}`;
}

// System font stacks (no external loading needed)
export const SYSTEM_FONTS: GoogleFont[] = [
  { family: 'System Default', category: 'sans-serif', weights: [300, 400, 500, 600, 700] },
];

export const SYSTEM_FONT_STACK = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";

/**
 * Curated font catalog — ~80 fonts organized by category.
 */
export const GOOGLE_FONTS: GoogleFont[] = [
  // ─── Sans-Serif (most versatile for body + headings) ───
  { family: 'Inter', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true, popular: true },
  { family: 'Open Sans', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true, popular: true },
  { family: 'Roboto', category: 'sans-serif', weights: [300, 400, 500, 700], popular: true },
  { family: 'Lato', category: 'sans-serif', weights: [300, 400, 700], popular: true },
  { family: 'Montserrat', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true, popular: true },
  { family: 'Poppins', category: 'sans-serif', weights: [300, 400, 500, 600, 700], popular: true },
  { family: 'Nunito', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Raleway', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true, popular: true },
  { family: 'Work Sans', category: 'sans-serif', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'DM Sans', category: 'sans-serif', weights: [400, 500, 700], popular: true },
  { family: 'Manrope', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true, popular: true },
  { family: 'Plus Jakarta Sans', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Outfit', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Space Grotesk', category: 'sans-serif', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Sora', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Figtree', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Geist', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Rubik', category: 'sans-serif', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Barlow', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], popular: true },
  { family: 'Barlow Condensed', category: 'sans-serif', weights: [300, 400, 500, 600, 700], popular: true },
  { family: 'Nunito Sans', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true, popular: true },
  { family: 'Mulish', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Quicksand', category: 'sans-serif', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Cabin', category: 'sans-serif', weights: [400, 500, 600, 700], variable: true },
  { family: 'Karla', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Albert Sans', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Lexend', category: 'sans-serif', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Urbanist', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Red Hat Display', category: 'sans-serif', weights: [300, 400, 500, 600, 700, 800] },
  { family: 'Overpass', category: 'sans-serif', weights: [300, 400, 600, 700, 800], variable: true },
  { family: 'Source Sans 3', category: 'sans-serif', weights: [300, 400, 500, 600, 700], variable: true, popular: true },
  { family: 'Noto Sans', category: 'sans-serif', weights: [300, 400, 500, 600, 700], variable: true },

  // ─── Serif (editorial, luxury, traditional) ───
  { family: 'Playfair Display', category: 'serif', weights: [400, 500, 600, 700, 800], variable: true, popular: true },
  { family: 'Merriweather', category: 'serif', weights: [300, 400, 700], popular: true },
  { family: 'Lora', category: 'serif', weights: [400, 500, 600, 700], variable: true, popular: true },
  { family: 'PT Serif', category: 'serif', weights: [400, 700] },
  { family: 'Source Serif 4', category: 'serif', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Libre Baskerville', category: 'serif', weights: [400, 700] },
  { family: 'Cormorant Garamond', category: 'serif', weights: [300, 400, 500, 600, 700] },
  { family: 'EB Garamond', category: 'serif', weights: [400, 500, 600, 700, 800], variable: true },
  { family: 'Crimson Pro', category: 'serif', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Bitter', category: 'serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Instrument Serif', category: 'serif', weights: [400] },
  { family: 'DM Serif Display', category: 'serif', weights: [400] },
  { family: 'Fraunces', category: 'serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Newsreader', category: 'serif', weights: [300, 400, 500, 600, 700, 800], variable: true },
  { family: 'Spectral', category: 'serif', weights: [300, 400, 500, 600, 700, 800] },
  { family: 'Vollkorn', category: 'serif', weights: [400, 500, 600, 700, 800], variable: true },
  { family: 'Cardo', category: 'serif', weights: [400, 700] },
  { family: 'Noto Serif', category: 'serif', weights: [400, 700], variable: true },
  { family: 'Bodoni Moda', category: 'serif', weights: [400, 500, 600, 700, 800], variable: true },

  // ─── Display (headings, hero text, branding) ───
  { family: 'Bebas Neue', category: 'display', weights: [400], popular: true },
  { family: 'Oswald', category: 'display', weights: [300, 400, 500, 600, 700], variable: true, popular: true },
  { family: 'Abril Fatface', category: 'display', weights: [400] },
  { family: 'Anton', category: 'display', weights: [400] },
  { family: 'Righteous', category: 'display', weights: [400] },
  { family: 'Archivo Black', category: 'display', weights: [400] },
  { family: 'Comfortaa', category: 'display', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Josefin Sans', category: 'display', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Fredoka', category: 'display', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Syne', category: 'display', weights: [400, 500, 600, 700, 800], variable: true },
  { family: 'Space Mono', category: 'display', weights: [400, 700] },
  { family: 'Bungee', category: 'display', weights: [400] },

  // ─── Monospace (code, technical content) ───
  { family: 'JetBrains Mono', category: 'monospace', weights: [300, 400, 500, 600, 700, 800], variable: true, popular: true },
  { family: 'Fira Code', category: 'monospace', weights: [300, 400, 500, 600, 700], variable: true, popular: true },
  { family: 'Source Code Pro', category: 'monospace', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'IBM Plex Mono', category: 'monospace', weights: [300, 400, 500, 600, 700] },
  { family: 'Roboto Mono', category: 'monospace', weights: [300, 400, 500, 600, 700], variable: true },
  { family: 'Inconsolata', category: 'monospace', weights: [300, 400, 500, 600, 700], variable: true },
];

/** All fonts including system option */
export const ALL_FONTS = [...SYSTEM_FONTS, ...GOOGLE_FONTS];

/** Font categories for filtering */
export const FONT_CATEGORIES: { value: FontCategory | 'all'; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'sans-serif', label: 'Sans Serif' },
  { value: 'serif', label: 'Serif' },
  { value: 'display', label: 'Display' },
  { value: 'monospace', label: 'Monospace' },
];

/** Font roles available for configuration */
export interface FontRoles {
  body?: string;          // Global body text
  heading?: string;       // Global headings (h1-h6 default)
  mono?: string;          // Code / technical
  h1?: string;            // H1 override (optional)
  h2?: string;            // H2 override (optional)
  h3?: string;            // H3 override (optional)
  h4?: string;            // H4 override (optional)
  h5?: string;            // H5 override (optional)
  h6?: string;            // H6 override (optional)
  button?: string;        // Button text (optional)
  nav?: string;           // Navigation text (optional)
}

export const FONT_ROLE_LABELS: Record<keyof FontRoles, string> = {
  body: 'Body Text',
  heading: 'Headings (H1-H6)',
  mono: 'Code / Mono',
  h1: 'H1 Override',
  h2: 'H2 Override',
  h3: 'H3 Override',
  h4: 'H4 Override',
  h5: 'H5 Override',
  h6: 'H6 Override',
  button: 'Button Text',
  nav: 'Navigation',
};

/** Find a font by family name */
export function findFont(family: string): GoogleFont | undefined {
  const clean = family.replace(/^['"]|['"]$/g, '').split(',')[0].trim();
  return ALL_FONTS.find(f => f.family.toLowerCase() === clean.toLowerCase());
}
