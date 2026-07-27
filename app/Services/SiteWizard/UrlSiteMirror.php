<?php

namespace App\Services\SiteWizard;

use App\Models\SiteWizard\SiteWizardSession;
use App\Support\SsrfGuard;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Exact-copy ingestion for URL imports: mirrors a live site into the session
 * workspace in the SAME layout a design ZIP produces — pages as <slug>.html at
 * the root, every same-origin asset (CSS/JS/images/fonts) at its URL path —
 * so the whole downstream exact pipeline (SiteDocumentPageBuilder, site-files
 * staging, verbatim publish) works unchanged. Because the ORIGINAL stylesheets
 * and scripts ship with the copy, the source site's animations and effects
 * survive.
 *
 * The raw server HTML is mirrored (not a rendered DOM snapshot): scripts run
 * again on the copy exactly as they did on the source, which is what keeps
 * entrance animations, counters and sliders alive.
 */
class UrlSiteMirror
{
    private const ASSET_EXTENSIONS = [
        'css', 'js', 'json', 'txt', 'xml', 'ico', 'svg',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif',
        'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp4', 'webm',
    ];
    private const PAGE_SKIP_EXTENSIONS = [
        'pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'rss',
        ...self::ASSET_EXTENSIONS,
    ];
    private const MAX_ASSETS = 600;
    private const MAX_ASSET_BYTES = 15 * 1024 * 1024;
    private const MAX_TOTAL_BYTES = 300 * 1024 * 1024;

    private string $origin = '';
    private string $root = '';
    private int $totalBytes = 0;
    /** @var array<string,bool> url-path => fetched */
    private array $savedAssets = [];

    public function __construct(private ZipSiteIngestor $workspace)
    {
    }

    /**
     * @return array{pages: array<int, array{path:string, slug:string, is_home:bool}>, stats: array}
     */
    public function mirror(SiteWizardSession $session, string $entryUrl, int $maxPages): array
    {
        SsrfGuard::assertPublicHttpUrl($entryUrl);

        $this->root = $this->workspace->workspacePath($session) . '/files';
        File::ensureDirectoryExists($this->root);
        $session->update(['workspace_path' => "site-wizard/{$session->id}"]);

        $entry = $this->canonicalPageUrl($entryUrl);
        $u = parse_url($entry);
        $this->origin = "{$u['scheme']}://{$u['host']}" . (isset($u['port']) ? ":{$u['port']}" : '');
        $this->totalBytes = 0;
        $this->savedAssets = [];

        // ── discover pages (entry + its same-origin links, capped) ──
        $entryHtml = $this->fetchText($entry);
        if ($entryHtml === null) {
            throw new RuntimeException('The site could not be read.');
        }

        $pageUrls = [$entry];
        foreach ($this->hrefsIn($entryHtml) as $href) {
            if (count($pageUrls) >= $maxPages) {
                break;
            }
            $abs = $this->absolutize($href, $entry);
            if ($abs === null || !$this->isPageUrl($abs)) {
                continue;
            }
            $abs = $this->canonicalPageUrl($abs);
            if (!in_array($abs, $pageUrls, true)) {
                $pageUrls[] = $abs;
            }
        }

        // ── fetch pages, decide slugs ──
        $pages = [];
        $htmlByRef = [];
        $pathToRef = [];
        $usedSlugs = [];
        foreach ($pageUrls as $pageUrl) {
            $html = $pageUrl === $entry ? $entryHtml : $this->fetchText($pageUrl);
            if ($html === null || stripos($html, '<html') === false) {
                continue;
            }
            $slug = $this->slugFor($pageUrl);
            $base = $slug;
            for ($i = 2; isset($usedSlugs[$slug]); $i++) {
                $slug = "{$base}-{$i}";
            }
            $usedSlugs[$slug] = true;
            $ref = "{$slug}.html";
            $htmlByRef[$ref] = $html;
            $pathToRef[$this->pathKey($pageUrl)] = $ref;
            $pages[] = ['path' => $ref, 'slug' => $slug, 'is_home' => $pageUrl === $entry];
        }
        if ($pages === []) {
            throw new RuntimeException('No pages could be mirrored from that URL.');
        }

        // ── mirror assets referenced by the pages ──
        $cssFiles = [];
        foreach ($htmlByRef as $html) {
            foreach ($this->assetUrlsIn($html) as $assetUrl) {
                $saved = $this->saveAsset($assetUrl);
                if ($saved !== null && str_ends_with($saved, '.css')) {
                    $cssFiles[$saved] = $assetUrl;
                }
            }
        }

        // ── CSS pass: pull url()/@import dependencies, make refs portable ──
        // (two rounds: css → imported css → its fonts/images)
        for ($round = 0; $round < 2; $round++) {
            $next = [];
            foreach ($cssFiles as $cssPath => $cssUrl) {
                $file = "{$this->root}/{$cssPath}";
                if (!is_file($file)) {
                    continue;
                }
                $css = (string) file_get_contents($file);
                foreach ($this->cssRefsIn($css) as $ref) {
                    $abs = $this->absolutize($ref, $cssUrl);
                    if ($abs === null) {
                        continue;
                    }
                    $saved = $this->saveAsset($abs);
                    if ($saved !== null && str_ends_with($saved, '.css') && !isset($cssFiles[$saved])) {
                        $next[$saved] = $abs;
                    }
                }
                file_put_contents($file, $this->relativizeCss($css, $cssPath));
            }
            $cssFiles = $next;
            if ($cssFiles === []) {
                break;
            }
        }

        // ── write the pages: same-origin refs become root-relative, page
        //    links become <slug>.html refs the document builder understands ──
        foreach ($htmlByRef as $ref => $html) {
            file_put_contents("{$this->root}/{$ref}", $this->rewriteHtml($html, $pathToRef));
        }

        return [
            'pages' => $pages,
            'stats' => ['files' => count($this->savedAssets), 'bytes' => $this->totalBytes],
        ];
    }

    // ── fetching ──

    private function fetchText(string $url): ?string
    {
        try {
            SsrfGuard::assertPublicHttpUrl($url);
            $res = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (StillopressSiteWizard)'])
                ->timeout(30)->connectTimeout(10)->get($url);

            return $res->successful() ? $res->body() : null;
        } catch (\Throwable $e) {
            Log::info('UrlSiteMirror: page fetch failed', ['url' => $url, 'err' => mb_substr($e->getMessage(), 0, 120)]);

            return null;
        }
    }

    /** Download one same-origin asset to its URL path; returns the saved relative path. */
    private function saveAsset(string $url): ?string
    {
        if (!str_starts_with($url, $this->origin . '/')) {
            return null;
        }
        $path = $this->normalizePath((string) parse_url($url, PHP_URL_PATH));
        if ($path === null) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ASSET_EXTENSIONS, true)) {
            return null;
        }
        if (isset($this->savedAssets[$path])) {
            return $path;
        }
        if (count($this->savedAssets) >= self::MAX_ASSETS || $this->totalBytes >= self::MAX_TOTAL_BYTES) {
            return null;
        }

        try {
            SsrfGuard::assertPublicHttpUrl($url);
            $res = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (StillopressSiteWizard)'])
                ->timeout(45)->connectTimeout(10)->get($url);
            if (!$res->successful()) {
                return null;
            }
            $body = $res->body();
            if (strlen($body) > self::MAX_ASSET_BYTES) {
                return null;
            }
            File::ensureDirectoryExists(dirname("{$this->root}/{$path}"));
            file_put_contents("{$this->root}/{$path}", $body);
            $this->savedAssets[$path] = true;
            $this->totalBytes += strlen($body);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── URL parsing helpers ──

    private function canonicalPageUrl(string $url): string
    {
        $u = parse_url($url);
        $path = $u['path'] ?? '/';

        return "{$u['scheme']}://{$u['host']}" . (isset($u['port']) ? ":{$u['port']}" : '') . $path;
    }

    private function pathKey(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return '/' . trim($path, '/');
    }

    private function isPageUrl(string $abs): bool
    {
        if (!str_starts_with($abs, $this->origin . '/') && $abs !== $this->origin) {
            return false;
        }
        $path = (string) parse_url($abs, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::PAGE_SKIP_EXTENSIONS, true)) {
            return false;
        }
        // WP plumbing that never makes a page
        if (preg_match('#/(wp-admin|wp-json|wp-login|xmlrpc|feed|cart|checkout)(/|$)#i', $path)) {
            return false;
        }

        return true;
    }

    private function slugFor(string $pageUrl): string
    {
        $path = trim((string) parse_url($pageUrl, PHP_URL_PATH), '/');
        if ($path === '') {
            return 'home';
        }
        $slug = strtolower(preg_replace('/[^a-z0-9\x{80}-\x{10FFFF}]+/iu', '-', rawurldecode($path)));
        $slug = trim(preg_replace('/-{2,}/', '-', $slug), '-');

        return $slug !== '' ? mb_substr($slug, 0, 80) : 'page';
    }

    private function absolutize(string $href, string $baseUrl): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|javascript|data):#i', $href)) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $href = parse_url($baseUrl, PHP_URL_SCHEME) . ':' . $href;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $b = parse_url($baseUrl);
        $originPart = "{$b['scheme']}://{$b['host']}" . (isset($b['port']) ? ":{$b['port']}" : '');
        if (str_starts_with($href, '/')) {
            return $originPart . $href;
        }
        $dir = rtrim(dirname($b['path'] ?? '/'), '/');

        return "{$originPart}{$dir}/{$href}";
    }

    private function normalizePath(string $urlPath): ?string
    {
        $out = [];
        foreach (explode('/', rawurldecode($urlPath)) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                if ($out === []) {
                    return null;
                }
                array_pop($out);
                continue;
            }
            if (str_contains($seg, "\0") || str_contains($seg, '\\')) {
                return null;
            }
            $out[] = $seg;
        }

        return $out === [] ? null : implode('/', $out);
    }

    // ── reference extraction ──

    /** @return array<int,string> */
    private function hrefsIn(string $html): array
    {
        preg_match_all('#<a\b[^>]*\bhref=(["\'])(.*?)\1#is', $html, $m);

        return $m[2] ?? [];
    }

    /** Same-origin asset URLs referenced by a page (attributes, srcset, inline url()). */
    private function assetUrlsIn(string $html): array
    {
        $urls = [];
        preg_match_all('#\b(?:href|src|poster|content|data-src)=(["\'])([^"\']+)\1#i', $html, $m);
        foreach ($m[2] as $ref) {
            $urls[] = $ref;
        }
        preg_match_all('#\bsrcset=(["\'])([^"\']+)\1#i', $html, $m);
        foreach ($m[2] as $set) {
            foreach (explode(',', $set) as $entry) {
                $urls[] = preg_split('/\s+/', trim($entry), 2)[0] ?? '';
            }
        }
        preg_match_all('#url\(\s*(["\']?)([^"\')]+)\1\s*\)#i', $html, $m);
        foreach ($m[2] as $ref) {
            $urls[] = $ref;
        }

        $out = [];
        foreach ($urls as $ref) {
            $abs = $this->absolutize($ref, $this->origin . '/');
            if ($abs !== null && str_starts_with($abs, $this->origin . '/')) {
                $out[$abs] = true;
            }
        }

        return array_keys($out);
    }

    /** @return array<int,string> url()/@import references inside a stylesheet */
    private function cssRefsIn(string $css): array
    {
        $refs = [];
        preg_match_all('#url\(\s*(["\']?)([^"\')]+)\1\s*\)#i', $css, $m);
        foreach ($m[2] as $ref) {
            if (!str_starts_with($ref, 'data:')) {
                $refs[] = $ref;
            }
        }
        preg_match_all('#@import\s+(["\'])([^"\']+)\1#i', $css, $m);
        foreach ($m[2] as $ref) {
            $refs[] = $ref;
        }

        return $refs;
    }

    // ── rewriting ──

    /**
     * Make a mirrored stylesheet portable: same-origin absolute and
     * root-absolute references become RELATIVE to the css file itself, so they
     * resolve wherever the file tree is served from (admin preview and the
     * published static site alike).
     */
    private function relativizeCss(string $css, string $cssPath): string
    {
        $prefix = str_repeat('../', substr_count($cssPath, '/'));
        $origin = preg_quote($this->origin, '#');

        $css = preg_replace("#{$origin}/#", $prefix, $css);
        // url(/wp-content/...) → url(../../wp-content/...)
        $css = preg_replace_callback('#url\(\s*(["\']?)/([^"\')/][^"\')]*)\1\s*\)#i',
            fn ($m) => "url({$m[1]}{$prefix}{$m[2]}{$m[1]})", $css);

        return preg_replace_callback('#(@import\s+)(["\'])/([^"\']+)\2#i',
            fn ($m) => "{$m[1]}{$m[2]}{$prefix}{$m[3]}{$m[2]}", $css);
    }

    /**
     * Page HTML: strip the origin (all refs become root-relative — attributes,
     * srcset, inline styles, preloads), then point links at mirrored pages to
     * their <slug>.html refs so SiteDocumentPageBuilder can token-link them.
     */
    private function rewriteHtml(string $html, array $pathToRef): string
    {
        $html = str_replace(
            [$this->origin . '/', str_replace(['https://', 'http://'], '//', $this->origin) . '/'],
            '/',
            $html
        );

        return preg_replace_callback('#\bhref=(["\'])([^"\']*)\1#i', function ($m) use ($pathToRef) {
            $href = $m[2];
            $suffix = '';
            if (preg_match('/^([^?#]*)([?#].*)$/', $href, $p)) {
                [, $href, $suffix] = $p;
            }
            if ($href === '' || !str_starts_with($href, '/')) {
                return $m[0];
            }
            $key = '/' . trim($href, '/');
            if (isset($pathToRef[$key])) {
                return 'href=' . $m[1] . '/' . $pathToRef[$key] . $suffix . $m[1];
            }

            return $m[0];
        }, $html);
    }
}
