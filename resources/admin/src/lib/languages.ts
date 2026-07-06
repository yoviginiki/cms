// Shared language metadata — keep in sync with LocalePaths::LANGUAGE_META (backend).
export interface LanguageMeta {
  label: string;   // English name
  native: string;  // native name
  flag: string;    // emoji flag
}

export const LANGUAGES: Record<string, LanguageMeta> = {
  en: { label: 'English', native: 'English', flag: '🇬🇧' },
  bg: { label: 'Bulgarian', native: 'Български', flag: '🇧🇬' },
  de: { label: 'German', native: 'Deutsch', flag: '🇩🇪' },
  fr: { label: 'French', native: 'Français', flag: '🇫🇷' },
  es: { label: 'Spanish', native: 'Español', flag: '🇪🇸' },
  it: { label: 'Italian', native: 'Italiano', flag: '🇮🇹' },
  nl: { label: 'Dutch', native: 'Nederlands', flag: '🇳🇱' },
  pt: { label: 'Portuguese', native: 'Português', flag: '🇵🇹' },
  ru: { label: 'Russian', native: 'Русский', flag: '🇷🇺' },
  ja: { label: 'Japanese', native: '日本語', flag: '🇯🇵' },
  zh: { label: 'Chinese', native: '中文', flag: '🇨🇳' },
  ko: { label: 'Korean', native: '한국어', flag: '🇰🇷' },
  ar: { label: 'Arabic', native: 'العربية', flag: '🇸🇦' },
  tr: { label: 'Turkish', native: 'Türkçe', flag: '🇹🇷' },
  pl: { label: 'Polish', native: 'Polski', flag: '🇵🇱' },
  cs: { label: 'Czech', native: 'Čeština', flag: '🇨🇿' },
  ro: { label: 'Romanian', native: 'Română', flag: '🇷🇴' },
  uk: { label: 'Ukrainian', native: 'Українська', flag: '🇺🇦' },
  el: { label: 'Greek', native: 'Ελληνικά', flag: '🇬🇷' },
  sv: { label: 'Swedish', native: 'Svenska', flag: '🇸🇪' },
};

export function langMeta(code: string): LanguageMeta {
  return LANGUAGES[code] ?? { label: code.toUpperCase(), native: code.toUpperCase(), flag: '🌐' };
}

interface SiteLike {
  settings?: { default_language?: string; languages?: string[] } | null;
}

/** Default language of a site ('en' when unset). */
export function siteDefaultLanguage(site?: SiteLike | null): string {
  return site?.settings?.default_language || 'en';
}

/** All enabled locales of a site, default first. */
export function siteLanguages(site?: SiteLike | null): string[] {
  const def = siteDefaultLanguage(site);
  const extra = (site?.settings?.languages || []).filter((l) => l && l !== def);
  return [def, ...extra];
}

/** True when the site has more than one language enabled. */
export function isMultilingual(site?: SiteLike | null): boolean {
  return siteLanguages(site).length > 1;
}

/** Locale of a page/post from its seo_meta (default language when unset). */
export function contentLocale(seoMeta: Record<string, unknown> | null | undefined, site?: SiteLike | null): string {
  return (seoMeta?.locale as string) || siteDefaultLanguage(site);
}
