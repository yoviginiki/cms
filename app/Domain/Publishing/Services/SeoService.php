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

        // Description
        $description = $seoMeta['description'] ?? $this->autoDescription($content);

        // Canonical URL
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
        $homepageId = $site->settings['homepage_id'] ?? null;
        $isHomepage = ($homepageId && !$isPost && $content->id === $homepageId) || (!$homepageId && $content->slug === 'home');
        $path = $isPost ? '/' . ($content->category ? $content->category->slug . '/' : '') . $content->slug : "/{$content->slug}";
        $canonicalUrl = rtrim($baseUrl, '/') . ($isHomepage ? '/' : $path);

        // OG Image
        $ogImage = $seoMeta['og_image'] ?? ($isPost ? $content->featured_image : null) ?? $siteDefaults['og_image'] ?? '';

        $head = '';
        $head .= "<title>" . e($fullTitle) . "</title>\n";
        $head .= '<meta name="description" content="' . e($description) . '">' . "\n";

        if (!empty($seoMeta['no_index'])) {
            $head .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        }

        $head .= '<link rel="canonical" href="' . e($canonicalUrl) . '">' . "\n";

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
            $head .= '<meta property="article:modified_time" content="' . $content->updated_at->toIso8601String() . '">' . "\n";
            if ($content->category) {
                $head .= '<meta property="article:section" content="' . e($content->category->name) . '">' . "\n";
            }
        }

        // Structured data
        if ($isPost) {
            $head .= $this->structuredData->generateForPost($content, $site) . "\n";
        } else {
            $head .= $this->structuredData->generateForPage($content, $site) . "\n";
        }
        $head .= $this->structuredData->generateBreadcrumbs($content, $site) . "\n";

        return $head;
    }

    private function autoDescription(Page|Post $content): string
    {
        $firstText = $content->blocks()
            ->where('type', 'text')
            ->orderBy('order')
            ->first();

        if ($firstText && !empty($firstText->data['content'])) {
            $text = strip_tags($firstText->data['content']);
            return mb_substr($text, 0, 160);
        }

        return '';
    }
}
