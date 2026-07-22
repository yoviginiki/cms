<?php

namespace App\Services\SiteWizard;

use App\Domain\Publishing\Services\SiteFilesPublisher;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Exact-copy builder for ZIP design packages: each HTML file becomes a page
 * whose raw_html IS the original document, published verbatim (no theme
 * wrapper, no block conversion — the design's own CSS/JS/markup survive
 * untouched). The package's non-HTML files are staged into the site-files
 * store and every relative asset reference is rewritten to their serve URL;
 * links between the package's pages are rewritten to the built pages' URLs in
 * a second pass (finalizeLinks) once every page — and thus its final slug —
 * exists.
 */
class SiteDocumentPageBuilder
{
    /** Marker for a cross-page link until every page's final slug is known. */
    private const LINK_TOKEN = '@@site-wizard-page:';

    public function __construct(
        private ZipSiteIngestor $zip,
        private \App\Domain\Pages\Services\PageService $pages,
    ) {
    }

    /** @return array{page: Page, links: array, title: string} */
    public function build(SiteWizardSession $session, Site $site, array $source): array
    {
        $root = $this->zip->filesRoot($session);
        $file = $root . '/' . $source['ref'];
        if (!is_file($file)) {
            throw new RuntimeException('This page file is missing from the archive.');
        }
        $html = (string) file_get_contents($file);
        if (trim($html) === '') {
            throw new RuntimeException('This page file is empty.');
        }

        // Idempotent per page (1 page per job invocation — no cross-run state).
        $this->stageDesignFiles($site, $root);

        $html = $this->rewriteRefs($session, $site, $source, $html, $root);
        $html = $this->ensureDocument($html);

        $title = $this->titleFrom($html) ?: ucwords(str_replace('-', ' ', $source['slug']));

        $slug = $source['slug'];
        if ($session->mode() === 'into') {
            $prefix = $session->slugPrefix();
            $slug = $slug === 'home' ? $prefix : "{$prefix}-{$slug}";
        }

        $page = $this->pages->createPage([
            'title' => mb_substr($title, 0, 255),
            'slug' => $slug,
            'status' => 'draft',
            'raw_html' => $html,
        ], $site);

        return ['page' => $page, 'links' => [], 'title' => $title];
    }

    /**
     * Second pass, after ALL pages exist: swap the cross-page link tokens for
     * the built pages' real URLs (the home page lives at the site root).
     */
    public function finalizeLinks(SiteWizardSession $session): void
    {
        $urlByToken = [];
        foreach ($session->sources ?? [] as $source) {
            if (($source['status'] ?? '') !== 'done' || empty($source['page_id'])) {
                continue;
            }
            $page = Page::find($source['page_id']);
            if (!$page) {
                continue;
            }
            $isHome = ($source['is_home'] ?? false) && $session->mode() === 'new';
            $urlByToken[self::LINK_TOKEN . $source['ref'] . '@@'] = $isHome ? '/' : "/{$page->slug}/";
        }

        foreach ($session->sources ?? [] as $source) {
            if (empty($source['page_id'])) {
                continue;
            }
            $page = Page::find($source['page_id']);
            if (!$page || !$page->raw_html || !str_contains($page->raw_html, self::LINK_TOKEN)) {
                continue;
            }
            $html = strtr($page->raw_html, $urlByToken);
            // A linked page that failed to build leaves its token behind —
            // point those at the site root rather than shipping the marker.
            $html = preg_replace('/' . preg_quote(self::LINK_TOKEN, '/') . '[^@"\']*@@/', '/', $html);
            $page->forceFill(['raw_html' => $html])->save();
        }
    }

    /**
     * Stage every non-HTML package file into the site-files store (shipped
     * with each static build, served under /api/v1/sites/{id}/files/ in the
     * admin preview). Re-copying on each page build is cheap and idempotent.
     */
    private function stageDesignFiles(Site $site, string $root): void
    {
        $target = SiteFilesPublisher::storageRoot($site);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $f) {
            if (!$f->isFile() || in_array(strtolower($f->getExtension()), ['html', 'htm'], true)) {
                continue;
            }
            $rel = ltrim(substr($f->getPathname(), strlen($root)), '/');
            File::ensureDirectoryExists(dirname("{$target}/{$rel}"));
            File::copy($f->getPathname(), "{$target}/{$rel}");
        }
    }

    /**
     * Rewrite href/src/poster/content/data-src and srcset references:
     * package-internal page links become link tokens (resolved by
     * finalizeLinks), files that exist in the package become site-file serve
     * URLs, everything external stays untouched.
     */
    private function rewriteRefs(SiteWizardSession $session, Site $site, array $source, string $html, string $root): string
    {
        $pageRefs = [];
        foreach ($session->sources ?? [] as $s) {
            $pageRefs[strtolower((string) $s['ref'])] = (string) $s['ref'];
        }
        $baseDir = str_contains($source['ref'], '/') ? dirname($source['ref']) : '';

        $rewriteOne = function (string $url) use ($pageRefs, $site, $root, $baseDir): string {
            if ($url === '' || preg_match('#^(\#|[a-z][a-z0-9+.-]*:|//)#i', $url)) {
                return $url; // anchor, absolute scheme (http/mailto/data/…), protocol-relative
            }

            $suffix = '';
            if (preg_match('/^([^?#]*)([?#].*)$/', $url, $m)) {
                [, $url, $suffix] = $m;
            }
            if ($url === '') {
                return $url . $suffix;
            }

            // Root-absolute refs treat the package root as the site root
            // (some exports emit them); everything else resolves against the
            // linking page's own directory.
            $path = str_starts_with($url, '/')
                ? ltrim($url, '/')
                : ($baseDir === '' ? $url : "{$baseDir}/{$url}");
            $path = $this->normalize($path);
            if ($path === null) {
                return $url . $suffix;
            }

            if (isset($pageRefs[strtolower($path)])) {
                return self::LINK_TOKEN . $pageRefs[strtolower($path)] . '@@' . $suffix;
            }
            if (is_file("{$root}/{$path}")) {
                $encoded = implode('/', array_map('rawurlencode', explode('/', $path)));

                return "/api/v1/sites/{$site->id}/files/{$encoded}" . $suffix;
            }

            return $url . $suffix;
        };

        $html = preg_replace_callback(
            '#\b(href|src|poster|content|data-src)=(["\'])([^"\']*)\2#i',
            fn ($m) => $m[1] . '=' . $m[2] . $rewriteOne($m[3]) . $m[2],
            $html
        );

        return preg_replace_callback('#\bsrcset=(["\'])([^"\']+)\1#i', function ($m) use ($rewriteOne) {
            $entries = array_map(function ($entry) use ($rewriteOne) {
                $entry = trim($entry);
                if ($entry === '') {
                    return $entry;
                }
                $parts = preg_split('/\s+/', $entry, 2);

                return $rewriteOne($parts[0]) . (isset($parts[1]) ? " {$parts[1]}" : '');
            }, explode(',', $m[2]));

            return 'srcset=' . $m[1] . implode(', ', $entries) . $m[1];
        }, $html);
    }

    /** Collapse ./ and ../ segments; null when the path escapes the package. */
    private function normalize(string $path): ?string
    {
        $out = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($out === []) {
                    return null;
                }
                array_pop($out);
                continue;
            }
            $out[] = $segment;
        }

        return $out === [] ? null : implode('/', $out);
    }

    /**
     * The publish pipeline treats a leading <!doctype>/<html> as "publish
     * verbatim" — guarantee that shape even for fragment-ish exports.
     */
    private function ensureDocument(string $html): string
    {
        return preg_match('/^\s*(?:<!doctype\b|<html\b)/i', $html) === 1
            ? $html
            : "<!DOCTYPE html>\n<html>\n<head><meta charset=\"utf-8\"></head>\n<body>\n{$html}\n</body>\n</html>";
    }

    private function titleFrom(string $html): ?string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));

            return $title !== '' ? $title : null;
        }

        return null;
    }
}
