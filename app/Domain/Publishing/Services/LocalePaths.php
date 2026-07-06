<?php

namespace App\Domain\Publishing\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

/**
 * Single source of truth for multilingual URL paths.
 *
 * Model:
 * - Site settings hold `default_language` (served at the root) and
 *   `languages` (additional locales, published under /{locale}/).
 * - A page/post declares its language via seo_meta.locale (editor: Page tab → Language).
 *   Content without a locale is treated as the default language.
 * - Translations are linked by slug convention: the English version of page
 *   `about` is a page with slug `about-en` and Language = English. The
 *   locale suffix is stripped from the published URL (/en/about/), and
 *   hreflang alternates are derived from the same convention.
 * - The homepage translation uses slug `{homepage-slug}-{locale}` (e.g. `home-en`)
 *   and publishes at /{locale}/.
 */
class LocalePaths
{
    /** Language metadata shared by the switcher block, the fallback pill and the admin. */
    public const LANGUAGE_META = [
        'en' => ['name' => 'English',    'native' => 'English',    'flag' => '🇬🇧'],
        'bg' => ['name' => 'Bulgarian',  'native' => 'Български',  'flag' => '🇧🇬'],
        'de' => ['name' => 'German',     'native' => 'Deutsch',    'flag' => '🇩🇪'],
        'fr' => ['name' => 'French',     'native' => 'Français',   'flag' => '🇫🇷'],
        'es' => ['name' => 'Spanish',    'native' => 'Español',    'flag' => '🇪🇸'],
        'it' => ['name' => 'Italian',    'native' => 'Italiano',   'flag' => '🇮🇹'],
        'nl' => ['name' => 'Dutch',      'native' => 'Nederlands', 'flag' => '🇳🇱'],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português',  'flag' => '🇵🇹'],
        'ru' => ['name' => 'Russian',    'native' => 'Русский',    'flag' => '🇷🇺'],
        'ja' => ['name' => 'Japanese',   'native' => '日本語',      'flag' => '🇯🇵'],
        'zh' => ['name' => 'Chinese',    'native' => '中文',        'flag' => '🇨🇳'],
        'ko' => ['name' => 'Korean',     'native' => '한국어',      'flag' => '🇰🇷'],
        'ar' => ['name' => 'Arabic',     'native' => 'العربية',    'flag' => '🇸🇦'],
        'tr' => ['name' => 'Turkish',    'native' => 'Türkçe',     'flag' => '🇹🇷'],
        'pl' => ['name' => 'Polish',     'native' => 'Polski',     'flag' => '🇵🇱'],
        'cs' => ['name' => 'Czech',      'native' => 'Čeština',    'flag' => '🇨🇿'],
        'ro' => ['name' => 'Romanian',   'native' => 'Română',     'flag' => '🇷🇴'],
        'uk' => ['name' => 'Ukrainian',  'native' => 'Українська', 'flag' => '🇺🇦'],
        'el' => ['name' => 'Greek',      'native' => 'Ελληνικά',   'flag' => '🇬🇷'],
        'sv' => ['name' => 'Swedish',    'native' => 'Svenska',    'flag' => '🇸🇪'],
    ];

    public static function languageMeta(string $locale): array
    {
        return self::LANGUAGE_META[$locale] ?? ['name' => strtoupper($locale), 'native' => strtoupper($locale), 'flag' => '🌐'];
    }

    /** Small UI strings used on published pages (breadcrumbs etc.), per locale. */
    private const UI_LABELS = [
        'home' => [
            'en' => 'Home', 'bg' => 'Начало', 'de' => 'Startseite', 'fr' => 'Accueil', 'es' => 'Inicio',
            'it' => 'Home', 'nl' => 'Home', 'pt' => 'Início', 'ru' => 'Главная', 'ja' => 'ホーム',
            'zh' => '首页', 'ko' => '홈', 'ar' => 'الرئيسية', 'tr' => 'Ana Sayfa', 'pl' => 'Strona główna',
            'cs' => 'Domů', 'ro' => 'Acasă', 'uk' => 'Головна', 'el' => 'Αρχική', 'sv' => 'Hem',
        ],
        'blog' => [
            'en' => 'Blog', 'bg' => 'Блог', 'de' => 'Blog', 'fr' => 'Blog', 'es' => 'Blog',
            'it' => 'Blog', 'nl' => 'Blog', 'pt' => 'Blog', 'ru' => 'Блог', 'ja' => 'ブログ',
            'zh' => '博客', 'ko' => '블로그', 'ar' => 'مدونة', 'tr' => 'Blog', 'pl' => 'Blog',
            'cs' => 'Blog', 'ro' => 'Blog', 'uk' => 'Блог', 'el' => 'Ιστολόγιο', 'sv' => 'Blogg',
        ],
    ];

    public static function uiLabel(string $key, string $locale): string
    {
        return self::UI_LABELS[$key][$locale] ?? self::UI_LABELS[$key]['en'] ?? ucfirst($key);
    }

    public static function defaultLanguage(Site $site): string
    {
        return $site->settings['default_language'] ?? 'en';
    }

    /** All enabled locales, default first. */
    public static function languages(Site $site): array
    {
        $default = self::defaultLanguage($site);
        $extra = array_values(array_filter(
            (array) ($site->settings['languages'] ?? []),
            fn ($l) => is_string($l) && $l !== '' && $l !== $default
        ));

        return array_merge([$default], $extra);
    }

    public static function isMultilingual(Site $site): bool
    {
        return count(self::languages($site)) > 1;
    }

    /** Locale of a piece of content (falls back to the site default). */
    public static function contentLocale(Page|Post $content, Site $site): string
    {
        return $content->seo_meta['locale'] ?? self::defaultLanguage($site);
    }

    /** Strip the `-{locale}` translation suffix from a slug. */
    public static function baseSlug(string $slug, string $locale): string
    {
        $suffix = '-' . $locale;
        if (str_ends_with($slug, $suffix) && strlen($slug) > strlen($suffix)) {
            return substr($slug, 0, -strlen($suffix));
        }

        return $slug;
    }

    /** URL prefix for a locale: '' for the default language, '{locale}/' otherwise. */
    public static function prefix(Site $site, string $locale): string
    {
        return $locale === self::defaultLanguage($site) ? '' : $locale . '/';
    }

    private static function homepageSlug(Site $site): string
    {
        $homepageId = $site->settings['homepage_id'] ?? null;
        if ($homepageId) {
            $home = Page::find($homepageId);
            if ($home) return $home->slug;
        }

        return 'home';
    }

    /** Relative file path for a page (e.g. 'index.html', 'about/index.html', 'en/about/index.html'). */
    public static function pagePath(Site $site, Page $page): string
    {
        $locale = self::contentLocale($page, $site);
        $prefix = self::prefix($site, $locale);
        $homepageId = $site->settings['homepage_id'] ?? null;

        if ($prefix === '') {
            $isHome = ($homepageId && $page->id === $homepageId) || (!$homepageId && $page->slug === 'home');
            $slug = $isHome ? '' : $page->slug;
        } else {
            $base = self::baseSlug($page->slug, $locale);
            $isHome = $base === self::homepageSlug($site);
            $slug = $isHome ? '' : $base;
        }

        return $prefix . ($slug ? "{$slug}/" : '') . 'index.html';
    }

    /** Relative file path for a post (e.g. 'portfolio/lamp/index.html', 'en/portfolio/lamp/index.html'). */
    public static function postPath(Site $site, Post $post): string
    {
        $locale = self::contentLocale($post, $site);
        $prefix = self::prefix($site, $locale);
        $slug = $prefix === '' ? $post->slug : self::baseSlug($post->slug, $locale);
        $category = $post->category?->slug;

        return $prefix . ($category ? "{$category}/" : '') . "{$slug}/index.html";
    }

    /** Public URL path ('/', '/about/', '/en/about/') for canonical/hreflang/switcher. */
    public static function urlPath(Site $site, Page|Post $content): string
    {
        $file = $content instanceof Post ? self::postPath($site, $content) : self::pagePath($site, $content);

        return '/' . preg_replace('~index\.html$~', '', $file);
    }

    public static function baseUrl(Site $site): string
    {
        return $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
    }

    /**
     * Published translations of a piece of content, keyed by locale
     * (including the content itself). [locale => ['url' => ..., 'content' => ...]]
     */
    public static function alternates(Site $site, Page|Post $content): array
    {
        $locale = self::contentLocale($content, $site);
        $base = self::baseSlug($content->slug, $locale);
        $isPost = $content instanceof Post;

        $result = [$locale => ['url' => self::urlPath($site, $content), 'content' => $content]];

        foreach (self::languages($site) as $lang) {
            if ($lang === $locale) continue;
            $siblingSlug = $lang === self::defaultLanguage($site) ? $base : "{$base}-{$lang}";
            $query = $isPost
                ? Post::where('site_id', $site->id)->where('slug', $siblingSlug)->where('status', 'published')
                : Page::where('site_id', $site->id)->where('slug', $siblingSlug)->where('status', 'published');
            $sibling = $query->first();
            if ($sibling) {
                $result[$lang] = ['url' => self::urlPath($site, $sibling), 'content' => $sibling];
            }
        }

        return $result;
    }

    /**
     * Language switcher HTML for a published page. Links to the translation
     * counterpart when one exists, otherwise to that language's homepage.
     * Returns '' for single-language sites.
     */
    public static function switcherHtml(Site $site, Page|Post $content): string
    {
        if (!self::isMultilingual($site)) return '';

        $current = self::contentLocale($content, $site);
        $alternates = self::alternates($site, $content);

        $links = '';
        foreach (self::languages($site) as $lang) {
            $label = strtoupper($lang);
            if ($lang === $current) {
                $links .= '<span style="font-weight:700;color:var(--color-text,#1f2937);">' . e($label) . '</span>';
                continue;
            }
            $href = $alternates[$lang]['url'] ?? ('/' . self::prefix($site, $lang));
            $links .= '<a href="' . e($href) . '" style="color:var(--color-text-muted,#6b7280);text-decoration:none;" hreflang="' . e($lang) . '">' . e($label) . '</a>';
        }

        return '<div class="lang-switcher" style="position:fixed;bottom:16px;right:16px;z-index:9000;display:flex;gap:10px;align-items:center;'
            . 'padding:7px 14px;border-radius:999px;background:color-mix(in srgb, var(--color-bg,#ffffff) 85%, transparent);'
            . 'backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border:1px solid var(--color-border,#e5e7eb);'
            . 'box-shadow:0 2px 8px rgba(0,0,0,0.08);font-size:12px;letter-spacing:0.05em;">'
            . $links . '</div>';
    }

    /**
     * Post-process built HTML for multilingual sites: hreflang alternates in
     * <head> and the fallback switcher pill before </body> (skipped when the
     * page already contains a langswitcher block). Single-language sites get
     * their HTML back byte-identical.
     */
    public static function localizeHtml(Site $site, Page|Post $content, string $html): string
    {
        if (!self::isMultilingual($site)) return $html;

        $base = self::baseUrl($site);
        $alternates = self::alternates($site, $content);

        $links = '';
        foreach ($alternates as $lang => $alt) {
            $links .= '<link rel="alternate" hreflang="' . e($lang) . '" href="' . e($base . $alt['url']) . '">' . "\n";
        }
        $default = self::defaultLanguage($site);
        $xDefault = $alternates[$default]['url'] ?? self::urlPath($site, $content);
        $links .= '<link rel="alternate" hreflang="x-default" href="' . e($base . $xDefault) . '">' . "\n";

        $pos = stripos($html, '</head>');
        if ($pos !== false) {
            $html = substr($html, 0, $pos) . $links . substr($html, $pos);
        }

        if (!str_contains($html, 'lang-switcher')) {
            $pos = strripos($html, '</body>');
            if ($pos !== false) {
                $html = substr($html, 0, $pos) . self::switcherHtml($site, $content) . substr($html, $pos);
            }
        }

        return $html;
    }
}
