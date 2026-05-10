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
