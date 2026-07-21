<?php

namespace App\Domain\Collections\Services;

use App\Domain\Publishing\Services\ArchiveBuildService;
use App\Domain\Publishing\Services\AssetPublisher;
use App\Domain\Publishing\Services\BuildPageService;
use App\Domain\Publishing\Services\FaviconGenerator;
use App\Models\ContentCollection;
use App\Models\Record;
use App\Models\Site;
use App\Models\ThemeTemplate;
use App\Support\Blocks\RecordDisplay;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Track G2 — Tier-1 static publishing for Collections: one flat HTML detail
 * page per published record, statically paginated archive pages, and the
 * client-side search index (manifest + content-hashed shards). Modeled on
 * ArchiveBuildService; renders through BuildPageService's context seam so
 * record templates (record-single / record-archive) use the same block
 * machinery as everything else, with built-in fallback views when no
 * template exists.
 */
class CollectionPublishService
{
    /** Raw-JSON size ceiling per shard (~2.5MB raw stays well under 1MB gzipped). */
    private const SHARD_RAW_BYTES = 2_500_000;

    public function __construct(
        private BuildPageService $buildService,
        private ArchiveBuildService $archiveService,
    ) {
    }

    /**
     * Build every collection into the staging tree. Tier decides the
     * artifacts: static → detail pages + archives + JSON index; dynamic →
     * archives as static shells + detail pages for SEO (settings
     * static_details, default ON), NO index — search hits the public API.
     * Switching tier is therefore just a republish.
     *
     * @return array<int, string>
     */
    /**
     * Static JSON feeds for saved queries flagged settings.feed_enabled:
     * /queries/{slug}.json — rows in the public-query shape ({u,t,d} for
     * record queries, raw rows for SQL), capped at 500. Failures warn and
     * never break the publish.
     *
     * @return array<int, string> warnings
     */
    public function buildQueryFeeds(Site $site, string $stagingPath): array
    {
        $warnings = [];
        $queries = \App\Models\SavedQuery::where('site_id', $site->id)->get()
            ->filter(fn ($q) => (bool) ($q->settings['feed_enabled'] ?? false));

        foreach ($queries as $query) {
            try {
                $result = app(\App\Domain\Collections\Queries\QueryRunner::class)->run($query, []);

                if (($result['type'] ?? '') === 'records') {
                    $collection = $query->sourceCollection();
                    $rows = collect($result['rows'])->take(500)->map(fn ($r) => array_filter([
                        'u' => $collection ? RecordDisplay::recordUrl($collection, $r) : null,
                        't' => $r->title,
                        'd' => $r->data,
                    ]))->values()->all();
                } else {
                    $rows = collect($result['rows'] ?? [])->take(500)->values()->all();
                }

                $this->write($stagingPath, "queries/{$query->slug}.json", json_encode([
                    'query' => $query->slug,
                    'name' => $query->name,
                    'generated' => now()->toISOString(),
                    'count' => count($rows),
                    'rows' => $rows,
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            } catch (\Throwable $e) {
                $warnings[] = "Query feed '{$query->slug}' failed: {$e->getMessage()}";
            }
        }

        return $warnings;
    }

    public function buildAll(Site $site, string $stagingPath): array
    {
        $warnings = [];

        $collections = ContentCollection::where('site_id', $site->id)->get();
        foreach ($collections as $collection) {
            $warnings = array_merge($warnings, $this->buildCollection($site, $collection, $stagingPath));
        }

        $this->buildSiteSearchManifest($site, $collections, $stagingPath);

        return $warnings;
    }

    /**
     * Cross-collection search (v3): /search/index.json lists every static
     * collection whose per-collection index was built this publish, plus a
     * Pages source built from the site's published pages — so content-only
     * sites (like the docs site) are searchable too. The search island
     * detects `sources` and merges all indexes client-side.
     */
    private function buildSiteSearchManifest(Site $site, $collections, string $stagingPath): void
    {
        // Slug-hosted sites live in a docroot subdir — every published URL
        // needs the base or the island fetches another site's files.
        $base = RecordDisplay::sitePathBase($site);

        $sources = [];
        foreach ($collections as $collection) {
            if ($collection->tier !== 'static') {
                continue;
            }
            $prefix = $this->prefixFor($collection);
            if (!file_exists("{$stagingPath}/{$prefix}/index.json")) {
                continue; // no index built (collision-skipped or no records)
            }
            $sources[] = [
                'collection' => $collection->slug,
                'name' => $collection->name,
                'manifest' => "{$base}/{$prefix}/index.json",
            ];
        }

        if ($pagesSource = $this->buildPagesSearchIndex($site, $stagingPath)) {
            $sources[] = $pagesSource;
        }

        if ($sources === []) {
            return;
        }

        $this->write($stagingPath, 'search/index.json', json_encode([
            'site' => $site->slug,
            'generated' => now()->toISOString(),
            'fields' => [['key' => '_type', 'label' => 'Type', 'type' => 'select', 'facet' => true]],
            'sources' => $sources,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Pages source for site search: one compact row per published page, the
     * search string extracted from the ALREADY-BUILT HTML in staging (pages
     * build before collections). Returns the manifest source entry, or null
     * when the site has no published pages.
     *
     * @return array{collection: string, name: string, manifest: string}|null
     */
    private function buildPagesSearchIndex(Site $site, string $stagingPath): ?array
    {
        $pages = \App\Models\Page::where('site_id', $site->id)
            ->where('status', 'published')
            ->get(['id', 'slug', 'title']);
        if ($pages->isEmpty()) {
            return null;
        }

        $base = RecordDisplay::sitePathBase($site);

        $rows = [];
        foreach ($pages as $page) {
            $url = "{$base}/" . trim($page->slug, '/') . '/';
            $search = mb_strtolower($page->title);

            $file = $stagingPath . '/' . trim($page->slug, '/') . '/index.html';
            if (is_file($file)) {
                $html = (string) file_get_contents($file);
                // <main> content only — nav/footer would make every page match
                // every site-chrome word.
                if (preg_match('#<main[^>]*>(.*?)</main>#si', $html, $m)) {
                    $html = $m[1];
                }
                $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#si', ' ', $html);
                $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))));
                $search = mb_strtolower(mb_substr($page->title . ' ' . $text, 0, 3000));
            }

            $rows[] = ['u' => $url, 't' => $page->title, 's' => $search];
        }

        $json = json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $shard = 'search/pages-1.' . substr(md5($json), 0, 8) . '.json';
        $this->write($stagingPath, $shard, $json);
        $this->write($stagingPath, 'search/pages.json', json_encode([
            'collection' => '_pages',
            'name' => 'Pages',
            'count' => count($rows),
            'fields' => [],
            'shards' => ["{$base}/{$shard}"],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return ['collection' => '_pages', 'name' => 'Pages', 'manifest' => "{$base}/search/pages.json"];
    }

    /** @return array<int, string> warnings */
    public function buildCollection(Site $site, ContentCollection $collection, string $stagingPath): array
    {
        $prefix = RecordDisplay::pathPrefix($collection);

        // Collision guard: pages/posts write before collections — if the
        // prefix path already holds an index.html we'd silently overwrite a
        // page. Skip with a loud warning instead.
        if (File::exists("{$stagingPath}/{$prefix}/index.html")) {
            return ["Collection '{$collection->slug}': path '/{$prefix}/' already used by a page or category — collection skipped. Set a different path_prefix in the collection settings."];
        }

        $records = Record::where('collection_id', $collection->id)
            ->where('status', 'published')
            ->with('relationsOut.toRecord')
            ->orderByDesc('published_at')
            ->get();

        $isDynamic = $collection->tier === 'dynamic';
        $staticDetails = (bool) ($collection->settings['static_details'] ?? true);

        if (!$isDynamic || $staticDetails) {
            foreach ($records as $record) {
                $this->buildRecordPage($site, $collection, $record, $stagingPath);
            }
        }

        $this->buildArchivePages($site, $collection, $records, $stagingPath);

        if (!$isDynamic) {
            $this->buildIndex($site, $collection, $records, $stagingPath);
        }

        return [];
    }

    /**
     * Delta helper: rebuild a collection's archive pages + search index
     * (record pages themselves are built per-target by the delta job).
     */
    public function rebuildArchiveAndIndex(Site $site, ContentCollection $collection, string $stagingPath): void
    {
        $records = Record::where('collection_id', $collection->id)
            ->where('status', 'published')
            ->with('relationsOut.toRecord')
            ->orderByDesc('published_at')
            ->get();

        $this->buildArchivePages($site, $collection, $records, $stagingPath);
        if ($collection->tier !== 'dynamic') {
            $this->buildIndex($site, $collection, $records, $stagingPath);
        }
    }

    /** Build one record's detail page (used by full and delta publishes). */
    public function buildRecordPage(Site $site, ContentCollection $collection, Record $record, string $stagingPath): void
    {
        $record->loadMissing('relationsOut.toRecord');

        // Hierarchy (S3): breadcrumb chain + child listing for tree collections.
        $ancestors = RecordDisplay::ancestors($collection, $record);
        $children = RecordDisplay::children($collection, $record);

        $context = [
            '__record' => $record,
            '__collection' => $collection,
            '__ancestors' => $ancestors,
            '__children' => $children,
        ];

        $template = ThemeTemplate::resolveForRecord($record);

        if ($template) {
            $blocks = $template->blocks()->whereNull('parent_block_id')->orderBy('order')->with('children')->get();
            $body = $this->buildService->renderBlocksWithContext($blocks, $site, $context);
        } else {
            $body = View::make('publishing.record-single', [
                'site' => $site,
                'collection' => $collection,
                'record' => $record,
                'ancestors' => $ancestors,
                'children' => $children,
            ])->render();
        }

        // Per-record SEO overrides win over derived values.
        $seo = $record->seo_meta ?? [];
        $seoTitle = $seo['title'] ?? $record->title;
        $description = $seo['description'] ?? $this->metaDescription($collection, $record);
        $head = '<title>' . e($seoTitle) . ' | ' . e($site->name) . '</title>'
            . '<meta name="description" content="' . e($description) . '">'
            . '<meta property="og:title" content="' . e($seoTitle) . '">';
        $thumb = null;
        if (!empty($seo['og_image'])) {
            $thumb = RecordDisplay::assetUrl($site, $seo['og_image']);
        }
        $thumb = $thumb ?? RecordDisplay::thumbUrl($site, $collection, $record);
        if ($thumb) {
            $head .= '<meta property="og:image" content="' . e($thumb) . '">';
        }

        $html = $this->wrapInLayout($site, $head, $body, $record->title);

        $path = ltrim(RecordDisplay::recordUrl($collection, $record), '/') . 'index.html';
        $this->write($stagingPath, $path, $html);
    }

    /** Statically paginated archive: /{prefix}/ + /{prefix}/page/{n}/. */
    private function buildArchivePages(Site $site, ContentCollection $collection, $records, string $stagingPath): void
    {
        $prefix = $this->prefixFor($collection);
        $perPage = max(6, min(100, (int) ($collection->settings['per_page'] ?? 24)));
        $totalPages = max(1, (int) ceil($records->count() / $perPage));
        $template = ThemeTemplate::resolveForRecordArchive($site->id, $collection->id);

        for ($page = 1; $page <= $totalPages; $page++) {
            $pageRecords = $records->forPage($page, $perPage)->values();

            $context = [
                '__collection' => $collection,
                '__archiveRecords' => $pageRecords,
                '__archiveRecordCount' => $records->count(),
                '__archiveCurrentPage' => $page,
                '__archiveTotalPages' => $totalPages,
                '__archiveBaseUrl' => "/{$prefix}",
            ];

            if ($template) {
                $blocks = $template->blocks()->whereNull('parent_block_id')->orderBy('order')->with('children')->get();
                $body = $this->buildService->renderBlocksWithContext($blocks, $site, $context);
            } else {
                $body = View::make('publishing.record-archive', [
                    'site' => $site,
                    'collection' => $collection,
                    'records' => $pageRecords,
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'baseUrl' => "/{$prefix}",
                ])->render();
            }

            $head = '<title>' . e($collection->name) . ($page > 1 ? " — page {$page}" : '') . ' | ' . e($site->name) . '</title>'
                . '<meta name="description" content="' . e(mb_substr("Browse {$collection->name} — {$site->name}", 0, 160)) . '">';

            // Runtime injection is auto-detected from data-cs-role in the body
            // (templated archives with search blocks); the fallback archive has
            // none and must not pay for an unused script.
            $html = $this->wrapInLayout($site, $head, $body, $collection->name);

            $path = $page === 1 ? "{$prefix}/index.html" : "{$prefix}/page/{$page}/index.html";
            $this->write($stagingPath, $path, $html);
        }
    }

    /**
     * The static search index: manifest at /{prefix}/index.json + content-
     * hashed shards next to it. Compact row shape shared with
     * collections-search.js: {u,t,s,f,d,i}.
     */
    public function buildIndex(Site $site, ContentCollection $collection, $records, string $stagingPath): void
    {
        $prefix = $this->prefixFor($collection);
        $fields = $collection->fields();

        $searchable = array_values(array_filter($fields, fn ($f) => $f['searchable'] ?? false));
        $facetable = array_values(array_filter($fields, fn ($f) => $f['facetable'] ?? false));
        // Display fields: what result cards may show (scalars only, keep the index lean).
        $display = array_values(array_filter($fields, fn ($f) => in_array($f['type'], ['text', 'price', 'select', 'sku', 'date', 'boolean', 'number'], true)));

        $rows = [];
        foreach ($records as $record) {
            $searchStrings = [mb_strtolower($record->title ?? '')];
            foreach ($searchable as $field) {
                if ($field['type'] === 'relation') {
                    // Related record titles — "search by author" on a book.
                    $titles = $record->relationsOut
                        ->where('relation_key', $field['key'])
                        ->map(fn ($e) => $e->toRecord?->title)
                        ->filter();
                    foreach ($titles as $title) {
                        $searchStrings[] = mb_strtolower($title);
                    }
                    continue;
                }
                $plain = RecordDisplay::plain($record, $field);
                if ($plain !== '') {
                    $searchStrings[] = mb_strtolower($plain);
                }
            }
            // Pivot text/sku values (supplier part numbers) are searchable too.
            foreach ($record->relationsOut as $edge) {
                foreach ($edge->pivot ?? [] as $v) {
                    if (is_string($v) && $v !== '') {
                        $searchStrings[] = mb_strtolower($v);
                    }
                }
            }

            $facets = [];
            foreach ($facetable as $field) {
                if ($field['type'] === 'relation') {
                    $titles = $record->relationsOut
                        ->where('relation_key', $field['key'])
                        ->map(fn ($e) => $e->toRecord?->title)
                        ->filter()
                        ->values()
                        ->all();
                    if ($titles !== []) {
                        $facets[$field['key']] = $titles;
                    }
                } else {
                    $value = $record->data[$field['key']] ?? null;
                    if ($value !== null && $value !== '' && $value !== []) {
                        $facets[$field['key']] = $value;
                    }
                }
            }

            $displayValues = [];
            foreach ($display as $field) {
                $value = $record->data[$field['key']] ?? null;
                if ($value !== null && $value !== '') {
                    $displayValues[$field['key']] = $value;
                }
            }

            $row = [
                'u' => RecordDisplay::recordUrl($collection, $record),
                't' => $record->title,
                's' => implode(' ', array_unique(array_filter($searchStrings))),
            ];
            if ($facets !== []) {
                $row['f'] = $facets;
            }
            if ($displayValues !== []) {
                $row['d'] = $displayValues;
            }
            if ($thumb = RecordDisplay::thumbUrl($site, $collection, $record)) {
                // Publish the asset and reference its static hashed path.
                $row['i'] = AssetPublisher::rewriteHtml('<img src="' . $thumb . '">');
                $row['i'] = preg_match('/src="([^"]+)"/', $row['i'], $m) ? $m[1] : null;
                if ($row['i'] === null) {
                    unset($row['i']);
                }
            }

            $rows[] = $row;
        }

        // Shard rows by raw JSON size; shard filenames are content-hashed so
        // browsers cache-bust naturally, the manifest URL stays stable.
        $shardLimit = (int) config('collections.shard_raw_bytes', self::SHARD_RAW_BYTES);
        $shards = [];
        $current = [];
        $currentBytes = 2;
        foreach ($rows as $row) {
            $bytes = strlen(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) + 1;
            if ($current !== [] && $currentBytes + $bytes > $shardLimit) {
                $shards[] = $current;
                $current = [];
                $currentBytes = 2;
            }
            $current[] = $row;
            $currentBytes += $bytes;
        }
        if ($current !== []) {
            $shards[] = $current;
        }

        $shardUrls = [];
        foreach ($shards as $i => $shardRows) {
            $json = json_encode($shardRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $hash = substr(md5($json), 0, 8);
            $filename = 'index-' . ($i + 1) . ".{$hash}.json";
            File::ensureDirectoryExists("{$stagingPath}/{$prefix}");
            File::put("{$stagingPath}/{$prefix}/{$filename}", $json);
            $shardUrls[] = "/{$prefix}/{$filename}";
        }

        $manifest = [
            'collection' => $collection->slug,
            'name' => $collection->name,
            'count' => count($rows),
            'currency' => RecordDisplay::currencySymbol($collection, $site),
            'generated' => now()->toIso8601String(),
            'fields' => array_values(array_map(fn ($f) => [
                'key' => $f['key'],
                'label' => $f['label'],
                'type' => $f['type'],
                'facet' => (bool) ($f['facetable'] ?? false),
            ], array_filter($fields, fn ($f) => ($f['facetable'] ?? false) || in_array($f['type'], ['text', 'price', 'select', 'sku', 'date', 'boolean', 'number'], true)))),
            'shards' => $shardUrls,
        ];

        File::ensureDirectoryExists("{$stagingPath}/{$prefix}");
        File::put("{$stagingPath}/{$prefix}/index.json", json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Publish the search-island runtime and return its script tag. The copy
     * goes into the STAGING tree (AssetPublisher deploy target) so it ships
     * atomically with the build — writing to the live docroot mid-build is
     * lost when the symlink strategy swaps builds. The CMS public dir gets a
     * copy too (preview iframe loads the same /assets/… path from the
     * Laravel origin).
     */
    public static function publishSearchRuntime(Site $site): string
    {
        $source = resource_path('js/collections-search.js');
        $hash = substr(md5_file($source), 0, 8);

        $bases = array_filter([AssetPublisher::deployTarget(), public_path()]);
        foreach ($bases as $base) {
            try {
                File::ensureDirectoryExists("{$base}/assets");
                if (!file_exists("{$base}/assets/collections-search.{$hash}.js")) {
                    File::copy($source, "{$base}/assets/collections-search.{$hash}.js");
                    @chmod("{$base}/assets/collections-search.{$hash}.js", 0664);
                }
            } catch (\Throwable $e) {
                logger()->warning("collections-search publish failed ({$base}) for site {$site->id}: {$e->getMessage()}");
            }
        }

        return '<script defer src="/assets/collections-search.' . $hash . '.js"></script>';
    }

    /** Sitemap entries for every statically-published record page + archive page 1. */
    public function sitemapUrls(Site $site): array
    {
        $urls = [];
        $collections = ContentCollection::where('site_id', $site->id)->get();
        foreach ($collections as $collection) {
            $prefix = $this->prefixFor($collection);
            $urls[] = ['path' => "/{$prefix}/", 'lastmod' => $collection->updated_at?->toW3cString()];
            if ($collection->tier === 'dynamic' && !($collection->settings['static_details'] ?? true)) {
                continue; // no static detail pages to list
            }
            $records = Record::where('collection_id', $collection->id)->where('status', 'published')->get(['id', 'slug', 'status', 'updated_at']);
            foreach ($records as $record) {
                $urls[] = ['path' => RecordDisplay::recordUrl($collection, $record), 'lastmod' => $record->updated_at?->toW3cString()];
            }
        }

        return $urls;
    }

    private function prefixFor(ContentCollection $collection): string
    {
        return RecordDisplay::pathPrefix($collection);
    }

    private function metaDescription(ContentCollection $collection, Record $record): string
    {
        foreach ($collection->fields() as $field) {
            if (in_array($field['type'], ['rich_text', 'text'], true) && $field['key'] !== $collection->titleField()) {
                $plain = RecordDisplay::plain($record, $field);
                if ($plain !== '') {
                    return mb_substr($plain, 0, 160);
                }
            }
        }

        return mb_substr("{$record->title} — {$collection->name}, {$collection->site?->name}", 0, 160);
    }

    /** Same full-page wrapping as templated category archives. */
    private function wrapInLayout(Site $site, string $headContent, string $body, string $title, bool $includeSearchRuntime = false): string
    {
        $vars = $this->archiveService->getArchiveVars($site);
        $themeConfig = $site->theme?->config ?? [];

        $bodyScripts = '';
        if ($includeSearchRuntime || str_contains($body, 'data-cs-role')) {
            $bodyScripts = self::publishSearchRuntime($site);
        }

        return View::make('publishing.layout', array_merge($vars, [
            'headContent' => $headContent . app(FaviconGenerator::class)->headLink(),
            'headScripts' => '',
            'bodyScripts' => $bodyScripts,
            'fontPreloads' => $vars['fontPreloads'] ?? '',
            'hookHeadScripts' => '',
            'hookBodyOpen' => '',
            'hookBodyClose' => '',
            'renderedBlocks' => $body,
            'mainStyle' => 'max-width:var(--container-width,1200px);margin:0 auto;padding:0 var(--container-padding,24px);',
            'content' => (object) ['title' => $title, 'seo_meta' => []],
            'themeConfig' => $themeConfig,
        ]))->render();
    }

    private function write(string $stagingPath, string $path, string $html): void
    {
        $html = AssetPublisher::rewriteHtml($html);
        File::ensureDirectoryExists(dirname("{$stagingPath}/{$path}"));
        File::put("{$stagingPath}/{$path}", $html);
    }
}
