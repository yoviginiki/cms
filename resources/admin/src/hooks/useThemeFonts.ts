import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { themeEngine } from '@/lib/api';

/**
 * Loads the site's active theme fonts into the editor page.
 * Ensures editor preview uses the same fonts as the published frontend.
 *
 * Extracts font families from resolved theme tokens and injects
 * Google Fonts <link> tags + CSS custom properties into <head>.
 */
export function useThemeFonts(siteId: string) {
  const { data: resolved } = useQuery<any>({
    queryKey: ['theme-resolved-fonts', siteId],
    queryFn: () => themeEngine.resolve(siteId).then((r: any) => r.data?.data),
    enabled: !!siteId,
    staleTime: 5 * 60 * 1000, // 5 min cache
  });

  useEffect(() => {
    if (!resolved?.tokens) return;
    const tokens = resolved.tokens as Record<string, string>;

    // Collect all font-family tokens
    const fontKeys = [
      'font-heading', 'font-body', 'font-mono',
      'font-h1', 'font-h2', 'font-h3', 'font-h4', 'font-h5', 'font-h6',
      'font-button', 'font-nav',
    ];

    const uniqueFamilies = new Set<string>();
    for (const key of fontKeys) {
      const val = tokens[key];
      if (!val || val.includes('system-ui') || val.includes('ui-monospace')) continue;
      const family = val.split(',')[0].replace(/['"]/g, '').trim();
      if (family) uniqueFamilies.add(family);
    }

    // Inject Google Fonts <link> for each unique family
    const linkId = 'theme-fonts-editor';
    let linkEl = document.getElementById(linkId) as HTMLLinkElement | null;

    if (uniqueFamilies.size > 0) {
      const families = Array.from(uniqueFamilies);
      const url = families.map(f =>
        `family=${encodeURIComponent(f)}:wght@300;400;500;600;700`
      ).join('&');
      const fullUrl = `https://fonts.googleapis.com/css2?${url}&display=swap`;

      if (!linkEl) {
        linkEl = document.createElement('link');
        linkEl.id = linkId;
        linkEl.rel = 'stylesheet';
        document.head.appendChild(linkEl);
      }
      linkEl.href = fullUrl;
    }

    // Inject CSS custom properties so editor canvas uses theme fonts
    const styleId = 'theme-tokens-editor';
    let styleEl = document.getElementById(styleId) as HTMLStyleElement | null;
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = styleId;
      document.head.appendChild(styleEl);
    }

    const cssVars = fontKeys
      .filter(key => tokens[key])
      .map(key => `  --${key}: ${tokens[key]};`)
      .join('\n');
    styleEl.textContent = `.editor-canvas-light {\n${cssVars}\n}`;

    return () => {
      // Cleanup on unmount
      document.getElementById(linkId)?.remove();
      document.getElementById(styleId)?.remove();
    };
  }, [resolved]);
}
