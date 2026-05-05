/**
 * Cyrillic-to-Latin transliteration map (Bulgarian standard).
 */
const CYRILLIC_MAP: Record<string, string> = {
  'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd', 'е': 'e', 'ж': 'zh',
  'з': 'z', 'и': 'i', 'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n',
  'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u', 'ф': 'f',
  'х': 'h', 'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'sht', 'ъ': 'a',
  'ь': '', 'ю': 'yu', 'я': 'ya',
  // uppercase
  'А': 'a', 'Б': 'b', 'В': 'v', 'Г': 'g', 'Д': 'd', 'Е': 'e', 'Ж': 'zh',
  'З': 'z', 'И': 'i', 'Й': 'y', 'К': 'k', 'Л': 'l', 'М': 'm', 'Н': 'n',
  'О': 'o', 'П': 'p', 'Р': 'r', 'С': 's', 'Т': 't', 'У': 'u', 'Ф': 'f',
  'Х': 'h', 'Ц': 'ts', 'Ч': 'ch', 'Ш': 'sh', 'Щ': 'sht', 'Ъ': 'a',
  'Ь': '', 'Ю': 'yu', 'Я': 'ya',
};

/**
 * Transliterate a string from Cyrillic to Latin, then produce a URL-safe slug.
 */
export function slugify(text: string): string {
  // Transliterate Cyrillic characters
  let result = '';
  for (const ch of text) {
    result += CYRILLIC_MAP[ch] ?? ch;
  }

  return result
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')   // strip diacritics
    .replace(/[^a-z0-9]+/g, '-')       // non-alphanumeric → hyphen
    .replace(/^-+|-+$/g, '')           // trim leading/trailing hyphens
    .replace(/-{2,}/g, '-');           // collapse multiple hyphens
}
