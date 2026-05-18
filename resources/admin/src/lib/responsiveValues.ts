/**
 * Responsive Values Helper
 *
 * Provides typed utilities for reading/writing breakpoint-specific
 * overrides stored in block.data.responsive.
 *
 * Data model:
 *   block.data = {
 *     textAlignment: 'center',     // ← desktop/base value
 *     sectionHeight: 'md',
 *     responsive: {                 // ← optional overrides
 *       tablet: { textAlignment: 'left' },
 *       mobile: { sectionHeight: 'auto' },
 *     }
 *   }
 *
 * Inheritance: mobile → tablet → desktop (base).
 * Missing overrides inherit from the next larger breakpoint.
 */

export type Breakpoint = 'desktop' | 'tablet' | 'mobile';

export interface ResponsiveData {
  tablet?: Record<string, unknown>;
  mobile?: Record<string, unknown>;
}

/**
 * Get the effective value for a key at a given breakpoint.
 *
 * Inheritance chain:
 *   desktop → top-level data[key]
 *   tablet  → responsive.tablet[key] ?? data[key]
 *   mobile  → responsive.mobile[key] ?? responsive.tablet[key] ?? data[key]
 */
export function getResponsiveValue(
  data: Record<string, unknown>,
  key: string,
  breakpoint: Breakpoint,
): unknown {
  const resp = data.responsive as ResponsiveData | undefined;
  const baseValue = data[key];

  if (breakpoint === 'desktop') return baseValue;

  const tabletValue = resp?.tablet?.[key];
  if (breakpoint === 'tablet') {
    return tabletValue !== undefined && tabletValue !== '' ? tabletValue : baseValue;
  }

  // mobile: check mobile override → tablet override → base
  const mobileValue = resp?.mobile?.[key];
  if (mobileValue !== undefined && mobileValue !== '') return mobileValue;
  if (tabletValue !== undefined && tabletValue !== '') return tabletValue;
  return baseValue;
}

/**
 * Set a responsive override for a specific breakpoint.
 * Returns a new data object (does not mutate).
 *
 * For desktop, writes directly to the top-level key.
 * For tablet/mobile, writes to responsive.tablet/mobile.
 */
export function setResponsiveValue(
  data: Record<string, unknown>,
  key: string,
  breakpoint: Breakpoint,
  value: unknown,
): Record<string, unknown> {
  if (breakpoint === 'desktop') {
    return { ...data, [key]: value };
  }

  const resp = (data.responsive as ResponsiveData) || {};
  const bpData = resp[breakpoint] || {};

  return {
    ...data,
    responsive: {
      ...resp,
      [breakpoint]: { ...bpData, [key]: value },
    },
  };
}

/**
 * Clear (remove) a responsive override, reverting to inherited value.
 * Returns a new data object (does not mutate).
 *
 * Only works for tablet/mobile. Desktop values cannot be "cleared" — set them directly.
 */
export function clearResponsiveValue(
  data: Record<string, unknown>,
  key: string,
  breakpoint: 'tablet' | 'mobile',
): Record<string, unknown> {
  const resp = (data.responsive as ResponsiveData) || {};
  const bpData = { ...(resp[breakpoint] || {}) };
  delete bpData[key];

  return {
    ...data,
    responsive: {
      ...resp,
      [breakpoint]: bpData,
    },
  };
}

/**
 * Check whether a key has an explicit override at a given breakpoint.
 */
export function hasResponsiveOverride(
  data: Record<string, unknown>,
  key: string,
  breakpoint: Breakpoint,
): boolean {
  if (breakpoint === 'desktop') return true; // desktop always has the base value
  const resp = data.responsive as ResponsiveData | undefined;
  const val = resp?.[breakpoint]?.[key];
  return val !== undefined && val !== '';
}

// ═══════════════════════════════════════════════════
// Style-aware responsive helpers
//
// These work with block.style (spacing/layout/visual)
// and block.responsive (tablet/mobile overrides).
//
// Data model:
//   block.style    = { spacing: { marginTop: '32px' } }   ← desktop base
//   block.responsive = {
//     tablet: { spacing: { marginTop: '24px' } },          ← tablet override
//     mobile: { spacing: { marginTop: '16px' } },          ← mobile override
//   }
// ═══════════════════════════════════════════════════

import type { BlockStyleProps, ResponsiveOverrides } from '@/types/blocks';

type StyleSection = keyof BlockStyleProps;

/**
 * Get the effective style value for a key at a given breakpoint.
 * Reads from block.responsive[breakpoint][section][key] with fallback to block.style[section][key].
 */
export function getResponsiveStyleValue(
  style: BlockStyleProps | undefined,
  responsive: ResponsiveOverrides | undefined,
  section: StyleSection,
  key: string,
  breakpoint: Breakpoint,
): unknown {
  const baseSection = (style?.[section] ?? {}) as Record<string, unknown>;
  const baseValue = baseSection[key];

  if (breakpoint === 'desktop') return baseValue;

  const tabletSection = (responsive?.tablet?.[section] ?? {}) as Record<string, unknown>;
  const tabletValue = tabletSection[key];

  if (breakpoint === 'tablet') {
    return tabletValue !== undefined && tabletValue !== '' ? tabletValue : baseValue;
  }

  // mobile: mobile → tablet → desktop
  const mobileSection = (responsive?.mobile?.[section] ?? {}) as Record<string, unknown>;
  const mobileValue = mobileSection[key];
  if (mobileValue !== undefined && mobileValue !== '') return mobileValue;
  if (tabletValue !== undefined && tabletValue !== '') return tabletValue;
  return baseValue;
}

/**
 * Get the full resolved section (e.g. all spacing props) at a given breakpoint.
 * Merges desktop base with tablet/mobile overrides.
 */
export function getResponsiveStyleSection(
  style: BlockStyleProps | undefined,
  responsive: ResponsiveOverrides | undefined,
  section: StyleSection,
  breakpoint: Breakpoint,
): Record<string, unknown> {
  const base = (style?.[section] ?? {}) as Record<string, unknown>;
  if (breakpoint === 'desktop') return { ...base };

  const tabletOverrides = (responsive?.tablet?.[section] ?? {}) as Record<string, unknown>;
  if (breakpoint === 'tablet') return { ...base, ...tabletOverrides };

  const mobileOverrides = (responsive?.mobile?.[section] ?? {}) as Record<string, unknown>;
  return { ...base, ...tabletOverrides, ...mobileOverrides };
}

/**
 * Set a responsive style override. Returns a new responsive object.
 * For desktop, returns null (caller should write to block.style directly).
 */
export function setResponsiveStyleValue(
  responsive: ResponsiveOverrides | undefined,
  section: StyleSection,
  key: string,
  breakpoint: Breakpoint,
  value: unknown,
): ResponsiveOverrides | null {
  if (breakpoint === 'desktop') return null; // desktop writes to block.style

  const resp = responsive ?? {};
  const bpOverrides = resp[breakpoint] ?? {};
  const sectionOverrides = ((bpOverrides as Record<string, unknown>)[section] ?? {}) as Record<string, unknown>;

  return {
    ...resp,
    [breakpoint]: {
      ...bpOverrides,
      [section]: { ...sectionOverrides, [key]: value },
    },
  };
}

/**
 * Clear a responsive style override. Returns a new responsive object.
 */
export function clearResponsiveStyleValue(
  responsive: ResponsiveOverrides | undefined,
  section: StyleSection,
  key: string,
  breakpoint: 'tablet' | 'mobile',
): ResponsiveOverrides {
  const resp = responsive ?? {};
  const bpOverrides = { ...(resp[breakpoint] ?? {}) };
  const sectionOverrides = { ...((bpOverrides as Record<string, unknown>)[section] ?? {}) } as Record<string, unknown>;
  delete sectionOverrides[key];
  (bpOverrides as Record<string, unknown>)[section] = sectionOverrides;

  return { ...resp, [breakpoint]: bpOverrides };
}

/**
 * Check whether a style key has an explicit override at a given breakpoint.
 */
export function hasResponsiveStyleOverride(
  responsive: ResponsiveOverrides | undefined,
  section: StyleSection,
  key: string,
  breakpoint: Breakpoint,
): boolean {
  if (breakpoint === 'desktop') return true;
  const bpOverrides = responsive?.[breakpoint];
  if (!bpOverrides) return false;
  const sectionOverrides = (bpOverrides as Record<string, unknown>)[section] as Record<string, unknown> | undefined;
  const val = sectionOverrides?.[key];
  return val !== undefined && val !== '';
}
