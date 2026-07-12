<?php
namespace App\Domain\Publishing\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Domain\Publishing\Services\LocalePaths;

class StructuredDataService
{
    /**
     * Business-type keyword → specific schema.org LocalBusiness subtype. More
     * specific types earn richer local-SEO treatment; unmatched → LocalBusiness.
     * All values are real schema.org types.
     */
    private const BUSINESS_SCHEMA = [
        'hvac' => 'HVACBusiness', 'plumb' => 'Plumber', 'electric' => 'Electrician',
        'roof' => 'RoofingContractor', 'paint' => 'HousePainter', 'contractor' => 'GeneralContractor',
        'dental' => 'Dentist', 'dentist' => 'Dentist', 'clinic' => 'MedicalBusiness', 'doctor' => 'Physician',
        'hotel' => 'LodgingBusiness', 'motel' => 'LodgingBusiness', 'inn' => 'LodgingBusiness',
        'restaurant' => 'Restaurant', 'cafe' => 'CafeOrCoffeeShop', 'coffee' => 'CafeOrCoffeeShop', 'bakery' => 'Bakery',
        'salon' => 'BeautySalon', 'barber' => 'HairSalon', 'hair' => 'HairSalon',
        'spa' => 'HealthAndBeautyBusiness', 'massage' => 'HealthAndBeautyBusiness', 'nail' => 'NailSalon',
        'gym' => 'ExerciseGym', 'yoga' => 'ExerciseGym', 'fitness' => 'ExerciseGym',
        'auto' => 'AutoRepair', 'car repair' => 'AutoRepair', 'mechanic' => 'AutoRepair',
        'real estate' => 'RealEstateAgent', 'law' => 'Attorney', 'attorney' => 'Attorney',
        'account' => 'AccountingService', 'florist' => 'Florist', 'flower' => 'Florist',
        'clean' => 'ProfessionalService', 'photograph' => 'ProfessionalService', 'landscap' => 'GeneralContractor',
    ];

    /**
     * LocalBusiness / Organization structured data for the homepage. Emitted only
     * when the site records a business type (set by the Full-Site generator), so
     * the generated small-business sites carry proper local-SEO identity.
     */
    private function nodeLocalBusiness(Site $site): ?array
    {
        $type = trim((string) ($site->settings['business_type'] ?? ''));
        if ($type === '') {
            return null;
        }
        $node = [
            '@type' => $this->businessSchemaType($type),
            'name' => $site->name,
            'url' => $this->getSiteUrl($site),
        ];
        $desc = trim((string) ($site->settings['business_description'] ?? ''));
        if ($desc !== '') $node['description'] = $desc;
        $image = $site->seo_defaults['og_image'] ?? null;
        if ($image) $node['image'] = $image;
        return $node;
    }

    private function businessSchemaType(string $topic): string
    {
        $t = mb_strtolower($topic);
        foreach (self::BUSINESS_SCHEMA as $needle => $schemaType) {
            if (str_contains($t, $needle)) {
                return $schemaType;
            }
        }
        return 'LocalBusiness';
    }

    /**
     * Single consolidated, deduplicated @graph for the head (F1): WebPage/Article
     * + LocalBusiness (homepage) + BreadcrumbList + FAQPage in one script tag.
     */
    public function generateGraph(Page|Post $content, Site $site, bool $isHomepage = false): string
    {
        $nodes = [];
        if ($content instanceof Post) {
            $nodes[] = $this->nodeForPost($content, $site);
        } else {
            $nodes[] = $this->nodeForPage($content, $site);
            if ($isHomepage && ($lb = $this->nodeLocalBusiness($site))) {
                $nodes[] = $lb;
            }
        }
        $nodes[] = $this->nodeBreadcrumbs($content, $site);
        if ($faq = $this->nodeFaq($content)) {
            $nodes[] = $faq;
        }

        return $this->script(['@context' => 'https://schema.org', '@graph' => array_values($nodes)]);
    }

    // ─── Individual emitters (single-node scripts) — retained for direct use/tests ───
    public function generateForPage(Page $page, Site $site): string { return $this->wrap($this->nodeForPage($page, $site)); }
    public function generateForPost(Post $post, Site $site): string { return $this->wrap($this->nodeForPost($post, $site)); }
    public function generateBreadcrumbs(Page|Post $content, Site $site): string { return $this->wrap($this->nodeBreadcrumbs($content, $site)); }
    public function generateFaqPage(Page|Post $content): ?string { $n = $this->nodeFaq($content); return $n ? $this->wrap($n) : null; }
    public function generateLocalBusiness(Site $site): ?string { $n = $this->nodeLocalBusiness($site); return $n ? $this->wrap($n) : null; }

    // ─── Node builders (arrays without @context; assembled into @graph) ───

    private function nodeForPage(Page $page, Site $site): array
    {
        return [
            '@type' => 'WebPage',
            'name' => $page->title,
            'url' => $this->contentUrl($site, $page),
            'isPartOf' => ['@type' => 'WebSite', 'name' => $site->name, 'url' => $this->getSiteUrl($site)],
        ];
    }

    private function nodeForPost(Post $post, Site $site): array
    {
        $contentUrl = $this->contentUrl($site, $post);
        $articleType = $site->seo_defaults['article_type'] ?? 'BlogPosting';
        if (!in_array($articleType, ['Article', 'NewsArticle', 'BlogPosting'], true)) {
            $articleType = 'BlogPosting';
        }
        $node = [
            '@type' => $articleType,
            'headline' => $post->title,
            'url' => $contentUrl,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $contentUrl],
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => ($post->content_modified_at ?? $post->updated_at)->toIso8601String(),
            'publisher' => ['@type' => 'Organization', 'name' => $site->name, 'url' => $this->getSiteUrl($site)],
        ];
        if ($post->author?->name) $node['author'] = ['@type' => 'Person', 'name' => $post->author->name];
        if ($post->featured_image) $node['image'] = $post->featured_image;
        if ($post->excerpt) $node['description'] = $post->excerpt;
        return $node;
    }

    /** Block-driven FAQ node from accordion blocks (null below Google's 2-Q&A min). */
    private function nodeFaq(Page|Post $content): ?array
    {
        $questions = [];
        foreach ($content->blocks()->where('type', 'accordion')->orderBy('order')->get() as $block) {
            foreach (($block->data['items'] ?? []) as $item) {
                $q = trim(strip_tags((string) ($item['title'] ?? '')));
                $a = trim(strip_tags((string) ($item['content'] ?? '')));
                if ($q !== '' && $a !== '') {
                    $questions[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => mb_substr($a, 0, 1000)]];
                }
            }
        }
        return count($questions) >= 2 ? ['@type' => 'FAQPage', 'mainEntity' => $questions] : null;
    }

    private function nodeBreadcrumbs(Page|Post $content, Site $site): array
    {
        $url = $this->getSiteUrl($site);
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $url . '/']];

        if ($content instanceof Post) {
            if ($content->category) {
                $items[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $content->category->name, 'item' => $url . '/' . $content->category->slug];
            }
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $content->title, 'item' => $this->contentUrl($site, $content)];
        } else {
            $chain = [];
            $current = $content;
            while ($current) {
                $chain[] = $current;
                $current = $current->parent;
            }
            $chain = array_reverse($chain);
            foreach ($chain as $i => $page) {
                $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $page->title, 'item' => $this->contentUrl($site, $page)];
            }
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    private function wrap(array $node): string
    {
        return $this->script(['@context' => 'https://schema.org'] + $node);
    }

    private function script(array $data): string
    {
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    private function getSiteUrl(Site $site): string
    {
        return $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
    }

    /**
     * Absolute URL for a page/post that matches the canonical + sitemap exactly
     * (same LocalePaths routing, trailing slash trimmed like the canonical).
     */
    private function contentUrl(Site $site, Page|Post $content): string
    {
        return rtrim($this->getSiteUrl($site), '/') . rtrim(LocalePaths::urlPath($site, $content), '/');
    }
}
