import { describe, it, expect } from 'vitest';
import { normalizeThemeTokens, tokensToCssVars, validateThemeManifest, THEME_DEFAULTS } from './themeTokens';

describe('normalizeThemeTokens', () => {
  it('returns defaults for null input', () => {
    const result = normalizeThemeTokens(null);
    expect(result.colors.primary).toBe(THEME_DEFAULTS.colors.primary);
    expect(result.typography.headingFont).toBe(THEME_DEFAULTS.typography.headingFont);
  });

  it('returns defaults for empty object', () => {
    const result = normalizeThemeTokens({});
    expect(result.colors.primary).toBe(THEME_DEFAULTS.colors.primary);
  });

  it('preserves valid color values', () => {
    const result = normalizeThemeTokens({ colors: { primary: '#ff0000' } });
    expect(result.colors.primary).toBe('#ff0000');
    expect(result.colors.secondary).toBe(THEME_DEFAULTS.colors.secondary);
  });

  it('rejects invalid color values', () => {
    const result = normalizeThemeTokens({ colors: { primary: 'not-a-color' } });
    expect(result.colors.primary).toBe(THEME_DEFAULTS.colors.primary);
  });

  it('clamps scale to 1-2 range', () => {
    expect(normalizeThemeTokens({ typography: { scale: 0.5 } }).typography.scale).toBe(1);
    expect(normalizeThemeTokens({ typography: { scale: 5 } }).typography.scale).toBe(2);
    expect(normalizeThemeTokens({ typography: { scale: 1.5 } }).typography.scale).toBe(1.5);
  });

  it('validates component style enums', () => {
    const result = normalizeThemeTokens({ components: { buttonStyle: 'pill' } });
    expect(result.components.buttonStyle).toBe('pill');
  });

  it('rejects invalid component style', () => {
    const result = normalizeThemeTokens({ components: { buttonStyle: 'banana' } });
    expect(result.components.buttonStyle).toBe('rounded');
  });

  it('validates dimension values', () => {
    const result = normalizeThemeTokens({ spacing: { containerWidth: '1400px' } });
    expect(result.spacing.containerWidth).toBe('1400px');
  });

  it('rejects unsafe dimension values', () => {
    const result = normalizeThemeTokens({ spacing: { containerWidth: 'url(evil)' } });
    expect(result.spacing.containerWidth).toBe(THEME_DEFAULTS.spacing.containerWidth);
  });
});

describe('tokensToCssVars', () => {
  it('generates correct CSS variable names', () => {
    const vars = tokensToCssVars(normalizeThemeTokens(null));
    expect(vars['--cms-color-primary']).toBe(THEME_DEFAULTS.colors.primary);
    expect(vars['--cms-font-heading']).toBe(THEME_DEFAULTS.typography.headingFont);
    expect(vars['--cms-container-width']).toBe(THEME_DEFAULTS.spacing.containerWidth);
  });

  it('maps all expected variables', () => {
    const vars = tokensToCssVars(normalizeThemeTokens(null));
    expect(Object.keys(vars).length).toBe(18);
    expect(vars['--cms-radius-medium']).toBe('0.5rem');
  });
});

describe('validateThemeManifest', () => {
  it('returns error for null', () => {
    expect(validateThemeManifest(null)).toContain('Manifest must be an object');
  });

  it('returns error for missing $metadata', () => {
    const errors = validateThemeManifest({});
    expect(errors).toContain('Missing $metadata');
  });

  it('returns error for missing name', () => {
    const errors = validateThemeManifest({ $metadata: {} });
    expect(errors).toContain('Missing $metadata.name');
  });

  it('returns error for no tokens', () => {
    const errors = validateThemeManifest({ $metadata: { name: 'Test' } });
    expect(errors).toContain('Must have at least primitive or semantic tokens');
  });

  it('passes for valid manifest', () => {
    const errors = validateThemeManifest({
      $metadata: { name: 'Test', version: '1.0.0' },
      semantic: { color: { brand: { $type: 'color', $value: '#000' } } },
    });
    expect(errors.length).toBe(0);
  });
});
