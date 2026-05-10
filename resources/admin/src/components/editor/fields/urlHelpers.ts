/**
 * URL safety helpers for inline link editing.
 *
 * Same scheme policy as backend HeroBlockDefinition.php ctaUrl validation:
 *   Allowed: http, https, mailto, tel, relative paths, anchors
 *   Rejected: javascript, data, vbscript (including obfuscated variants)
 */

const DANGEROUS_SCHEMES = /^(javascript|data|vbscript)\s*:/i;
const SAFE_URL_PATTERN = /^(https?:\/\/|mailto:|tel:|\/|\.\.?\/|#|\?|[a-zA-Z0-9])/i;

/**
 * Strip whitespace and control characters that can obfuscate URL schemes.
 * Browsers ignore these when resolving href, so attackers use them to
 * bypass naive regex checks (e.g. "java\tscript:" or "java script:").
 */
function stripSchemeObfuscation(url: string): string {
  // Remove all ASCII control chars (0x00-0x1F, 0x7F) and Unicode whitespace
  // from the first 20 characters (scheme portion), preserving the rest.
  // This matches browser URL parsing behavior.
  return url.replace(/[\x00-\x1f\x7f\s]/g, '');
}

/**
 * Check whether a URL uses a safe scheme.
 * Empty strings are considered safe (means "no URL").
 *
 * Strips whitespace/control characters before checking to prevent
 * obfuscation attacks like "java script:" or "java\tscript:".
 */
export function isSafeUrl(url: string): boolean {
  const trimmed = url.trim();
  if (!trimmed) return true;
  // Check against stripped version to catch obfuscated schemes
  const stripped = stripSchemeObfuscation(trimmed);
  if (DANGEROUS_SCHEMES.test(stripped)) return false;
  return SAFE_URL_PATTERN.test(stripped);
}

/**
 * Describe why a URL is unsafe, for display in validation messages.
 * Returns empty string if safe.
 */
export function getUrlError(url: string): string {
  const trimmed = url.trim();
  if (!trimmed) return '';
  const stripped = stripSchemeObfuscation(trimmed);
  if (DANGEROUS_SCHEMES.test(stripped)) return 'Dangerous URL scheme (javascript/data/vbscript) is not allowed';
  if (!SAFE_URL_PATTERN.test(stripped)) return 'URL must start with http://, https://, mailto:, tel:, /, #, or a letter';
  return '';
}

/**
 * Check whether a URL points to an external site.
 */
export function isExternalUrl(url: string): boolean {
  return /^https?:\/\//i.test(url.trim());
}

/**
 * Normalize a URL for storage. Strips control characters and trims whitespace.
 * Returns empty string for empty/whitespace-only input.
 */
export function normalizeUrl(url: string): string {
  const trimmed = url.trim();
  if (!trimmed) return '';
  // Strip control characters that could obfuscate schemes
  return stripSchemeObfuscation(trimmed);
}
