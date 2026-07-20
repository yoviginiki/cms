<?php

namespace App\Domain\References\Services;

use App\Domain\References\Contracts\ReferenceExtractor;
use App\Domain\References\Extractors\FieldMapExtractor;
use App\Domain\References\Extractors\NullExtractor;

/**
 * Maps EVERY registered block type to a reference extractor.
 *
 * Completeness is part of the block registry contract: adding a block type
 * without adding an entry here fails ExtractorCoverageTest. Use NullExtractor
 * for types that reference nothing — never omit a type.
 */
class ReferenceExtractorRegistry
{
    /** @var array<string, ReferenceExtractor> */
    private array $map;

    public function __construct()
    {
        $null = new NullExtractor();

        $this->map = [
            // ── Slider system ──────────────────────────────────────────────
            // page → slider embed (the edge that drives stale-page republish)
            'slider_ref' => new FieldMapExtractor(
                idFields: ['sliderId' => ['slider', 'embeds']],
            ),
            // slide backgrounds carry assets; the slider ENTITY is the source
            // of these edges (recomputed when its block tree is saved)
            'slide' => new FieldMapExtractor(
                idFields: ['background.assetId' => ['asset', 'uses_asset']],
                urlFields: ['background.src'],
            ),
            'slider' => $null, // root config only (height/swiper) — no references
            'shape' => $null,

            // ── Global Sections (P2) ───────────────────────────────────────
            // page → global-section embed (drives stale-page republish exactly
            // like slider_ref; the generic staleness walk handles the cascade)
            'global_ref' => new FieldMapExtractor(
                idFields: ['sectionId' => ['global_section', 'embeds']],
            ),

            // ── Entity-ID + URL bearing blocks ─────────────────────────────
            'image' => new FieldMapExtractor(
                idFields: ['asset_id' => ['asset', 'uses_asset']],
                urlFields: ['url'],
            ),
            'hero' => new FieldMapExtractor(
                idFields: ['bg_asset_id' => ['asset', 'uses_asset']],
                urlFields: ['bg_image', 'ctaUrl'],
            ),
            'section' => new FieldMapExtractor(
                idFields: ['bg_asset_id' => ['asset', 'uses_asset']],
                urlFields: ['bg_image', 'background_image'],
            ),
            'flipbook' => new FieldMapExtractor(
                idFields: [
                    'pdf_asset_id' => ['asset', 'uses_asset'],
                    'category_id' => ['category', 'lists'],
                ],
                urlFields: ['pdf_url'],
            ),
            'menu' => new FieldMapExtractor(
                idFields: ['menuId' => ['menu', 'embeds']],
                urlFields: ['customItems.*.url'],
            ),
            'postcard' => new FieldMapExtractor(
                idFields: ['postId' => ['post', 'embeds']],
            ),

            // ── Listing blocks: lists edge to the category, wildcard when unfiltered ──
            'latestposts' => new FieldMapExtractor(
                idFields: ['categoryId' => ['category', 'lists']],
                listFallbacks: ['categoryId' => 'post'],
            ),
            'postgrid' => new FieldMapExtractor(
                idFields: ['categoryId' => ['category', 'lists']],
                listFallbacks: ['categoryId' => 'post'],
            ),
            'relatedposts' => new FieldMapExtractor(wildcardLists: ['post']),
            'post-loop' => new FieldMapExtractor(wildcardLists: ['post']),
            'categorylist' => new FieldMapExtractor(wildcardLists: ['category']),

            // ── Collections (Track G2): loops + search islands list a collection —
            // RecordService marks the collection stale on any record change, so
            // pages carrying these blocks republish (fresh counts/options).
            // Unset collectionId (archive-template context) → no edge; the
            // archive rebuild is driven by the publish pipeline itself.
            'record-loop' => new FieldMapExtractor(idFields: ['collectionId' => ['collection', 'lists'], 'queryId' => ['query', 'embeds'], 'relatedCollectionId' => ['collection', 'lists']]),
            'query-stat' => new FieldMapExtractor(idFields: ['queryId' => ['query', 'embeds']]),
            'query-table' => new FieldMapExtractor(idFields: ['queryId' => ['query', 'embeds']]),
            'search-box' => new FieldMapExtractor(idFields: ['collectionId' => ['collection', 'lists']]),
            'facet-filter' => new FieldMapExtractor(idFields: ['collectionId' => ['collection', 'lists']]),
            'results-grid' => new FieldMapExtractor(idFields: ['collectionId' => ['collection', 'lists']]),

            // ── URL-bearing media/CTA blocks ───────────────────────────────
            'gallery' => new FieldMapExtractor(urlFields: ['images.*']),
            'logostrip' => new FieldMapExtractor(urlFields: ['logos.*']),
            'catalog' => new FieldMapExtractor(
                urlFields: ['items.*.images.*'],
                htmlFields: ['items.*.content'],
            ),
            'video' => new FieldMapExtractor(urlFields: ['url', 'poster']),
            'audio' => new FieldMapExtractor(urlFields: ['url']),
            'imagecaption' => new FieldMapExtractor(urlFields: ['src']),
            'fullbleed' => new FieldMapExtractor(urlFields: ['src']),
            'beforeafter' => new FieldMapExtractor(urlFields: ['beforeSrc', 'afterSrc']),
            'testimonial' => new FieldMapExtractor(urlFields: ['items.*.avatar']),
            'ctabanner' => new FieldMapExtractor(urlFields: ['buttonUrl', 'backgroundImage']),
            'button' => new FieldMapExtractor(urlFields: ['url']),
            'paywall' => new FieldMapExtractor(urlFields: ['ctaUrl']),
            'pricingcard' => new FieldMapExtractor(urlFields: ['ctaUrl']),
            'pricingtable' => new FieldMapExtractor(urlFields: ['plans.*.ctaUrl']),
            'socialembed' => new FieldMapExtractor(urlFields: ['url']),

            // ── Rich-content blocks: internal links + inline asset srcs ────
            'text' => new FieldMapExtractor(htmlFields: ['content']),
            'rich-text' => new FieldMapExtractor(htmlFields: ['content']),
            'paragraph' => new FieldMapExtractor(htmlFields: ['content']),
            'accordion' => new FieldMapExtractor(htmlFields: ['items.*.content']),
            'html-embed' => new FieldMapExtractor(htmlFields: ['html', 'content']),

            // ── Structural / decorative / ambient blocks: no references ────
            // (post-* family binds to the CURRENT post at build time — the post
            // itself being republished covers it, no stored edge needed)
            'anchormenu' => $null,
            'archive-pagination' => $null,
            'langswitcher' => $null, // locale switcher — links resolved at build, no stored edges
            'authorbox' => $null,
            'breadcrumbs' => $null,
            'caption' => $null,
            'category-header' => $null,
            'chart' => $null,
            'code' => $null,
            'column' => $null,
            'columns' => $null,
            'contact-form' => $null,
            'container' => $null,
            'customform' => $null,
            'divider' => $null,
            'dropcap' => $null,
            'featurecomparison' => $null,
            'featuregrid' => $null,
            'footnote' => $null,
            'grid' => $null,
            'group' => $null,
            'heading' => $null,
            'icon' => $null,
            'list' => $null,
            'map' => $null,
            'modal' => $null,
            'newsletter' => $null,
            'overlap' => $null,
            'post-content' => $null,
            'post-excerpt' => $null,
            'post-image' => $null,
            'post-meta' => $null,
            'post-navigation' => $null,
            'post-title' => $null,
            'post-video' => $null,
            'record-title' => $null,
            'record-image' => $null,
            'field-value' => $null,
            'pullquote' => $null,
            'readingprogress' => $null,
            'row' => $null,
            'runningtext' => $null,
            'scroll_page' => $null,
            'sharebuttons' => $null,
            'sidenote' => $null,
            'spacer' => $null,
            'stats' => $null,
            'stickysidebar' => $null,
            'table' => $null,
            'tabs' => $null,
            'textdivider' => $null,
            'timeline' => $null,
            'toc' => $null,
            'tooltip' => $null,
        ];
    }

    public function for(string $blockType): ?ReferenceExtractor
    {
        return $this->map[$blockType] ?? null;
    }

    public function has(string $blockType): bool
    {
        return isset($this->map[$blockType]);
    }

    /** @return string[] */
    public function coveredTypes(): array
    {
        return array_keys($this->map);
    }
}
