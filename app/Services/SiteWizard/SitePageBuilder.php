<?php

namespace App\Services\SiteWizard;

use App\Domain\Blocks\Services\BlockService;
use App\Domain\Pages\Services\PageService;
use App\Models\Page;
use App\Models\Site;
use App\Models\SiteWizard\SiteWizardSession;
use App\Services\PageWizard\PageManifestCompiler;
use RuntimeException;

/**
 * Turns one extracted source (a crawled URL or a ZIP HTML file) into a real
 * DRAFT page: sanitize the manifest, pull its images into the media library
 * (rewriting to asset serve URLs), compile to the native section/row/column/
 * module tree, and sync. Everything downstream is a normal editable page.
 */
class SitePageBuilder
{
    public function __construct(
        private SitePageExtractor $extractor,
        private ZipSiteIngestor $zip,
        private PageManifestCompiler $compiler,
        private PageService $pages,
        private BlockService $blocks,
        private SiteWizardMediaImporter $media,
    ) {
    }

    /**
     * Build one source into a draft page.
     *
     * @return array{page: Page, links: array, title: string}
     */
    public function build(SiteWizardSession $session, Site $site, array $source): array
    {
        if (!empty($source['manifest'])) {
            // The ingest step already extracted this page (the entry) — reuse it.
            $extracted = ['manifest' => $source['manifest'], 'nav' => [], 'links' => [], 'style' => []];
        } else {
            $extracted = $session->source === 'zip'
                ? $this->extractor->fromLocalFile($this->zip->filesRoot($session), $source['ref'])
                : $this->extractor->fromUrl($source['ref']);
        }

        $manifest = $extracted['manifest'];
        if (($manifest['blocks'] ?? []) === []) {
            throw new RuntimeException('No importable content found on this page.');
        }

        $manifest['blocks'] = $this->importImages($session, $site, $manifest['blocks']);

        $tree = $this->compiler->compile($manifest);
        if ($tree === []) {
            throw new RuntimeException('This page produced no usable blocks.');
        }

        $title = trim((string) ($manifest['page_title'] ?? '')) ?: ucwords(str_replace('-', ' ', $source['slug']));

        // 'into' mode prefixes slugs so the import stays grouped and can't
        // collide with the target site's own pages (home → the prefix itself).
        $slug = $source['slug'];
        if ($session->mode() === 'into') {
            $prefix = $session->slugPrefix();
            $slug = $slug === 'home' ? $prefix : "{$prefix}-{$slug}";
        }

        $page = $this->pages->createPage([
            'title' => mb_substr($title, 0, 255),
            'slug' => $slug,
            'status' => 'draft',
        ], $site);

        $this->blocks->syncBlocks($page, $tree);

        return ['page' => $page, 'links' => $extracted['links'], 'title' => $title];
    }

    /** Raw extraction of a ZIP page (used by ingest to read the entry page's nav/style). */
    public function extractLocal(SiteWizardSession $session, string $relativePath): array
    {
        return $this->extractor->fromLocalFile($this->zip->filesRoot($session), $relativePath);
    }

    /**
     * Rewrite every image reference in the manifest to a media-library asset
     * serve URL (same pattern as StarterTemplateService::importedImage). The
     * session asset_map dedupes across pages; failures keep the original URL
     * (a hotlink beats a dropped block); the per-site image cap stops runaway
     * imports on image-heavy sites.
     */
    private function importImages(SiteWizardSession $session, Site $site, array $blocks): array
    {
        foreach ($blocks as $i => $block) {
            switch ($block['kind'] ?? '') {
                case 'image':
                    $blocks[$i] = $this->rewriteImageField($session, $site, $block, 'url', (string) ($block['alt'] ?? ''));
                    break;
                case 'gallery':
                    foreach ($block['images'] ?? [] as $j => $url) {
                        $blocks[$i]['images'][$j] = $this->importOne($session, $site, (string) $url) ?? $url;
                    }
                    break;
                case 'columns':
                    foreach ($block['columns'] ?? [] as $j => $cell) {
                        if (!empty($cell['image'])) {
                            $blocks[$i]['columns'][$j]['image'] = $this->importOne($session, $site, (string) $cell['image']) ?? $cell['image'];
                        }
                    }
                    break;
            }
        }

        return $blocks;
    }

    private function rewriteImageField(SiteWizardSession $session, Site $site, array $block, string $field, string $alt): array
    {
        $imported = $this->importOne($session, $site, (string) ($block[$field] ?? ''), $alt);
        if ($imported !== null) {
            $block[$field] = $imported;
        }

        return $block;
    }

    /** Import one image URL; returns the serve URL or null to keep the original. */
    private function importOne(SiteWizardSession $session, Site $site, string $url, string $alt = ''): ?string
    {
        if ($url === '') {
            return null;
        }

        $map = $session->asset_map ?? [];
        if (isset($map[$url]['url'])) {
            return $map[$url]['url'];
        }
        if (count($map) >= (int) config('cms.site_wizard.max_images', 60)) {
            return null;
        }

        $asset = $this->isLocalLoopback($url) && $session->source === 'zip'
            ? $this->media->fromFile($site, $this->loopbackToFile($session, $url), $alt)
            : $this->media->fromUrl($site, $url, $alt);
        if (!$asset) {
            return null;
        }

        $serveUrl = "/api/v1/sites/{$site->id}/assets/{$asset->id}/serve";
        $map[$url] = ['asset_id' => $asset->id, 'url' => $serveUrl];
        $session->update(['asset_map' => $map]);

        return $serveUrl;
    }

    private function isLocalLoopback(string $url): bool
    {
        return in_array((string) parse_url($url, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true);
    }

    /** Map a loopback URL from the throwaway static server back to the extracted file. */
    private function loopbackToFile(SiteWizardSession $session, string $url): string
    {
        $path = ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        $root = $this->zip->filesRoot($session);
        $resolved = realpath($root . '/' . rawurldecode($path));

        // Stay inside the workspace even if the URL was crafted.
        if ($resolved === false || !str_starts_with($resolved, realpath($root) . '/')) {
            return '';
        }

        return $resolved;
    }
}
