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

        $fullContent = (bool) ($site->seo_defaults['feed_full_content'] ?? false);
        $language = $site->settings['default_language'] ?? 'en';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"'
            . ($fullContent ? ' xmlns:content="http://purl.org/rss/1.0/modules/content/"' : '')
            . '>' . "\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>" . e($site->name) . "</title>\n";
        $xml .= "    <link>{$baseUrl}</link>\n";
        $xml .= "    <description>" . e($site->seo_defaults['description'] ?? "Latest posts from {$site->name}") . "</description>\n";
        $xml .= "    <language>" . e($language) . "</language>\n";
        $xml .= "    <atom:link href=\"{$baseUrl}/feed.xml\" rel=\"self\" type=\"application/rss+xml\" />\n";

        if ($posts->isNotEmpty() && $posts->first()->published_at) {
            $xml .= "    <lastBuildDate>" . $posts->first()->published_at->format('r') . "</lastBuildDate>\n";
        }

        foreach ($posts as $post) {
            $xml .= $this->item($site, $post, $baseUrl, $fullContent);
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }

    /** One RSS <item> — canonical URL (== sitemap/canonical), optional full content. */
    private function item(Site $site, $post, string $baseUrl, bool $fullContent): string
    {
        $postUrl = $baseUrl . LocalePaths::urlPath($site, $post);
        $description = e($post->excerpt ?: mb_substr(strip_tags($this->extractTextFromBlocks($post)), 0, 300));
        $pubDate = $post->published_at?->format('r') ?? '';

        $xml = "    <item>\n";
        $xml .= "      <title>" . e($post->title) . "</title>\n";
        $xml .= "      <link>{$postUrl}</link>\n";
        $xml .= "      <guid isPermaLink=\"true\">{$postUrl}</guid>\n";
        $xml .= "      <description>{$description}</description>\n";
        if ($fullContent) {
            $html = $this->extractHtmlFromBlocks($post);
            if ($html !== '') {
                $xml .= "      <content:encoded><![CDATA[" . str_replace(']]>', ']]&gt;', $html) . "]]></content:encoded>\n";
            }
        }
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

        return $xml;
    }

    /**
     * Generate RSS for a specific category — published at /{category}/feed.xml (F4).
     */
    public function generateForCategory(Site $site, $category, int $limit = 20): string
    {
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
        $fullContent = (bool) ($site->seo_defaults['feed_full_content'] ?? false);
        $language = $site->settings['default_language'] ?? 'en';
        $posts = $category->posts()
            ->with(['category', 'author'])
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"'
            . ($fullContent ? ' xmlns:content="http://purl.org/rss/1.0/modules/content/"' : '')
            . '>' . "\n";
        $xml .= "  <channel>\n";
        $xml .= "    <title>" . e($category->name) . " | " . e($site->name) . "</title>\n";
        $xml .= "    <link>{$baseUrl}/{$category->slug}</link>\n";
        $xml .= "    <description>" . e($category->description ?: "Posts in {$category->name}") . "</description>\n";
        $xml .= "    <language>" . e($language) . "</language>\n";
        $xml .= "    <atom:link href=\"{$baseUrl}/{$category->slug}/feed.xml\" rel=\"self\" type=\"application/rss+xml\" />\n";

        foreach ($posts as $post) {
            $xml .= $this->item($site, $post, $baseUrl, $fullContent);
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }

    /**
     * Full post body as HTML for <content:encoded> — text-bearing block
     * content only (already sanitized at save), in reading order.
     */
    private function extractHtmlFromBlocks($post): string
    {
        $html = '';
        foreach ($post->blocks()->orderBy('order')->get() as $block) {
            $data = $block->data ?? [];
            $chunk = trim((string) ($data['content'] ?? $data['text'] ?? ''));
            if ($chunk === '') {
                continue;
            }
            $html .= str_contains($chunk, '<') ? $chunk : '<p>' . e($chunk) . '</p>';
            $html .= "\n";
        }

        return trim($html);
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
