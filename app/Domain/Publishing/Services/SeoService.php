<?php
namespace App\Domain\Publishing\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

class SeoService
{
    public function __construct(private StructuredDataService $structuredData) {}

    public function generatePageHead(Page|Post $content, Site $site): string
    {
        $seoMeta = $content->seo_meta ?? [];
        $siteDefaults = $site->seo_defaults ?? [];
        $isPost = $content instanceof Post;

        // Title
        $title = $seoMeta['title'] ?? $content->title;
        $titleTemplate = $siteDefaults['title_template'] ?? '{title} | {site_name}';
        $fullTitle = str_replace(['{title}', '{site_name}'], [$title, $site->name], $titleTemplate);

        // Description — explicit → (post) excerpt → block scrape → site default
        $description = trim((string) ($seoMeta['description'] ?? ''));
        if ($description === '' && $isPost && $content->excerpt) {
            $description = mb_substr(trim(strip_tags((string) $content->excerpt)), 0, 160);
        }
        if ($description === '') {
            $description = $this->autoDescription($content);
        }
        if ($description === '') {
            $description = trim((string) ($siteDefaults['description'] ?? ''));
        }

        // Canonical URL — computed from the site domain + slug, overridable per page
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
        $homepageId = $site->settings['homepage_id'] ?? null;
        $isHomepage = ($homepageId && !$isPost && $content->id === $homepageId) || (!$homepageId && $content->slug === 'home');
        $path = $isPost ? '/' . ($content->category ? $content->category->slug . '/' : '') . $content->slug : "/{$content->slug}";
        $canonicalUrl = rtrim($baseUrl, '/') . ($isHomepage ? '/' : $path);
        $canonicalOverride = trim((string) ($seoMeta['canonical'] ?? ''));
        if ($canonicalOverride !== '' && filter_var($canonicalOverride, FILTER_VALIDATE_URL)) {
            $canonicalUrl = $canonicalOverride;
        }

        // OG Image
        $ogImage = $seoMeta['og_image'] ?? ($isPost ? $content->featured_image : null) ?? $siteDefaults['og_image'] ?? '';

        $head = '';
        $head .= "<title>" . e($fullTitle) . "</title>\n";
        $head .= '<meta name="description" content="' . e($description) . '">' . "\n";

        // Robots — independent index/follow toggles; no tag when both default (index, follow)
        $robots = [];
        if (!empty($seoMeta['no_index'])) {
            $robots[] = 'noindex';
        }
        if (!empty($seoMeta['no_follow'])) {
            $robots[] = 'nofollow';
        }
        if ($robots) {
            $head .= '<meta name="robots" content="' . implode(', ', $robots) . '">' . "\n";
        }

        // Search-engine verification tags (site-level slot)
        foreach (['verification_google' => 'google-site-verification', 'verification_bing' => 'msvalidate.01'] as $key => $metaName) {
            if (!empty($siteDefaults[$key])) {
                $head .= '<meta name="' . $metaName . '" content="' . e($siteDefaults[$key]) . '">' . "\n";
            }
        }

        $head .= '<link rel="canonical" href="' . e($canonicalUrl) . '">' . "\n";
        $head .= app(FaviconGenerator::class)->headLink() . "\n";

        // Open Graph
        $head .= '<meta property="og:title" content="' . e($title) . '">' . "\n";
        $head .= '<meta property="og:description" content="' . e($description) . '">' . "\n";
        $head .= '<meta property="og:url" content="' . e($canonicalUrl) . '">' . "\n";
        $head .= '<meta property="og:type" content="' . ($isPost ? 'article' : 'website') . '">' . "\n";
        $head .= '<meta property="og:site_name" content="' . e($site->name) . '">' . "\n";
        if ($ogImage) {
            $head .= '<meta property="og:image" content="' . e($ogImage) . '">' . "\n";
        }

        // Twitter Card
        $head .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $head .= '<meta name="twitter:title" content="' . e($title) . '">' . "\n";
        $head .= '<meta name="twitter:description" content="' . e($description) . '">' . "\n";
        if ($ogImage) {
            $head .= '<meta name="twitter:image" content="' . e($ogImage) . '">' . "\n";
        }

        // Article-specific meta
        if ($isPost) {
            if ($content->published_at) {
                $head .= '<meta property="article:published_time" content="' . $content->published_at->toIso8601String() . '">' . "\n";
            }
            $head .= '<meta property="article:modified_time" content="' . ($content->content_modified_at ?? $content->updated_at)->toIso8601String() . '">' . "\n";
            if ($content->category) {
                $head .= '<meta property="article:section" content="' . e($content->category->name) . '">' . "\n";
            }
        }

        // Structured data — one consolidated @graph (WebPage/Article + LocalBusiness
        // + BreadcrumbList + FAQPage).
        $head .= $this->structuredData->generateGraph($content, $site, $isHomepage) . "\n";

        return $head;
    }

    private function autoDescription(Page|Post $content): string
    {
        // Fall back across the common text-bearing block types (not just 'text')
        // so pages built from paragraph/rich-text/hero/heading still get a
        // meaningful meta description.
        $textBlocks = $content->blocks()
            ->whereIn('type', ['text', 'paragraph', 'rich-text', 'heading', 'hero', 'pullquote'])
            ->orderBy('order')
            ->get();

        foreach ($textBlocks as $block) {
            $raw = $block->data['content']
                ?? $block->data['text']
                ?? $block->data['heading']
                ?? $block->data['subtitle']
                ?? '';
            $text = trim(strip_tags((string) $raw));
            if ($text !== '') {
                return mb_substr($text, 0, 160);
            }
        }

        return '';
    }
}
