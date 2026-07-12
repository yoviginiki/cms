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
    public function generateLocalBusiness(Site $site): ?string
    {
        $type = trim((string) ($site->settings['business_type'] ?? ''));
        if ($type === '') {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $this->businessSchemaType($type),
            'name' => $site->name,
            'url' => $this->getSiteUrl($site),
        ];
        $desc = trim((string) ($site->settings['business_description'] ?? ''));
        if ($desc !== '') {
            $data['description'] = $desc;
        }
        $image = $site->seo_defaults['og_image'] ?? null;
        if ($image) {
            $data['image'] = $image;
        }

        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
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

    public function generateForPage(Page $page, Site $site): string
    {
        $url = $this->getSiteUrl($site);
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $page->title,
            'url' => $this->contentUrl($site, $page),
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => $site->name,
                'url' => $url,
            ],
        ];
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    public function generateForPost(Post $post, Site $site): string
    {
        $url = $this->getSiteUrl($site);
        $contentUrl = $this->contentUrl($site, $post);

        // Article subtype per site setting (Article | NewsArticle | BlogPosting).
        $articleType = $site->seo_defaults['article_type'] ?? 'BlogPosting';
        if (!in_array($articleType, ['Article', 'NewsArticle', 'BlogPosting'], true)) {
            $articleType = 'BlogPosting';
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => $articleType,
            'headline' => $post->title,
            'url' => $contentUrl,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $contentUrl],
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at->toIso8601String(),
            'publisher' => [
                '@type' => 'Organization',
                'name' => $site->name,
                'url' => $url,
            ],
        ];
        if ($post->author?->name) {
            $data['author'] = ['@type' => 'Person', 'name' => $post->author->name];
        }
        if ($post->featured_image) {
            $data['image'] = $post->featured_image;
        }
        if ($post->excerpt) {
            $data['description'] = $post->excerpt;
        }
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    public function generateBreadcrumbs(Page|Post $content, Site $site): string
    {
        $url = $this->getSiteUrl($site);
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $url . '/']];

        if ($content instanceof Post) {
            if ($content->category) {
                $items[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $content->category->name, 'item' => $url . '/' . $content->category->slug];
            }
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $content->title, 'item' => $this->contentUrl($site, $content)];
        } else {
            // Build page hierarchy
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

        $data = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
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
