<?php

namespace App\Domain\Publishing\Services;

use App\Models\Site;

class RssFeedGenerator
{
    public function generate(Site $site, int $limit = 20): string
    {
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
        $posts = $site->posts()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>" . e($site->name) . "</title>\n";
        $xml .= "    <link>{$baseUrl}</link>\n";
        $xml .= "    <description>" . e($site->seo_defaults['description'] ?? "Latest posts from {$site->name}") . "</description>\n";
        $xml .= "    <language>en</language>\n";
        $xml .= "    <atom:link href=\"{$baseUrl}/feed.xml\" rel=\"self\" type=\"application/rss+xml\" />\n";

        if ($posts->isNotEmpty() && $posts->first()->published_at) {
            $xml .= "    <lastBuildDate>" . $posts->first()->published_at->format('r') . "</lastBuildDate>\n";
        }

        foreach ($posts as $post) {
            $postUrl = "{$baseUrl}/blog/{$post->slug}";
            $description = e($post->excerpt ?: mb_substr(strip_tags($this->extractTextFromBlocks($post)), 0, 300));
            $pubDate = $post->published_at?->format('r') ?? '';

            $xml .= "    <item>\n";
            $xml .= "      <title>" . e($post->title) . "</title>\n";
            $xml .= "      <link>{$postUrl}</link>\n";
            $xml .= "      <guid isPermaLink=\"true\">{$postUrl}</guid>\n";
            $xml .= "      <description>{$description}</description>\n";
            if ($pubDate) {
                $xml .= "      <pubDate>{$pubDate}</pubDate>\n";
            }
            if ($post->category) {
                $xml .= "      <category>" . e($post->category->name) . "</category>\n";
            }
            if ($post->author) {
                $xml .= "      <author>" . e($post->author->email) . " (" . e($post->author->name) . ")</author>\n";
            }
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }

    /**
     * Generate RSS for a specific category.
     */
    public function generateForCategory(Site $site, $category, int $limit = 20): string
    {
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
        $posts = $category->posts()
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>" . e($category->name) . " | " . e($site->name) . "</title>\n";
        $xml .= "    <link>{$baseUrl}/{$category->slug}</link>\n";
        $xml .= "    <description>" . e($category->description ?: "Posts in {$category->name}") . "</description>\n";

        foreach ($posts as $post) {
            $xml .= "    <item>\n";
            $xml .= "      <title>" . e($post->title) . "</title>\n";
            $xml .= "      <link>{$baseUrl}/blog/{$post->slug}</link>\n";
            $xml .= "      <guid isPermaLink=\"true\">{$baseUrl}/blog/{$post->slug}</guid>\n";
            $xml .= "      <description>" . e($post->excerpt ?: '') . "</description>\n";
            if ($post->published_at) {
                $xml .= "      <pubDate>" . $post->published_at->format('r') . "</pubDate>\n";
            }
            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }

    private function extractTextFromBlocks($post): string
    {
        $text = '';
        foreach ($post->blocks()->orderBy('order')->get() as $block) {
            $data = $block->data ?? [];
            $text .= ' ' . ($data['content'] ?? '') . ' ' . ($data['text'] ?? '');
        }
        return strip_tags(trim($text));
    }
}
