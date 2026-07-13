# Languages & Translations

Publish the same site in multiple languages, each page at a clean locale-prefixed URL.

## Setup

In **Site Settings → Languages**, choose the site's **default language** and add any additional languages. The default language:

- sets the `<html lang>` attribute on every published page (and archives, and feeds),
- serves at the site root; other locales publish under a prefix (`/en/about/`, etc.).

## Translating content

Open a page or post and use the **Translations** panel: create or link the translation for each language. Each translation is a full page of its own — its content, SEO fields, and locale — connected to its siblings.

- A translation's locale can also be set per page (SEO panel → locale) for one-off pages in another language.
- Only **published** translations appear on the live site; drafts stay invisible.

## What publishes automatically

- **hreflang alternates**: every page with published translations emits the full alternate set in its head, so search engines serve the right language.
- **The language switcher block**: drop it in your header global to give visitors a locale switcher that links each page to its actual translations (not just the other homepage).
- Canonicals, sitemap entries, and structured data all use the locale-correct URLs.

## Tips

- Translate your menus and header/footer globals too — they're content like everything else.
- The site default description and title template are per-site; per-page SEO fields belong to each translation.
