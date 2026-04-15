<?php
namespace App\Domain\Publishing\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

class StructuredDataService
{
    public function generateForPage(Page $page, Site $site): string
    {
        $url = $this->getSiteUrl($site);
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $page->title,
            'url' => $url . '/' . ($page->slug === 'home' ? '' : $page->slug),
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
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'url' => $url . '/blog/' . $post->slug,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at->toIso8601String(),
            'publisher' => [
                '@type' => 'Organization',
                'name' => $site->name,
                'url' => $url,
            ],
        ];
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
                $items[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $content->category->name, 'item' => $url . '/blog/category/' . $content->category->slug];
            }
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $content->title, 'item' => $url . '/blog/' . $content->slug];
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
                $items[] = ['@type' => 'ListItem', 'position' => $i + 2, 'name' => $page->title, 'item' => $url . '/' . $page->slug];
            }
        }

        $data = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
    }

    private function getSiteUrl(Site $site): string
    {
        return $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
    }
}
