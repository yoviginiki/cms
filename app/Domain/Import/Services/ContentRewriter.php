<?php

namespace App\Domain\Import\Services;

use App\Models\Asset;

class ContentRewriter
{
    /**
     * Rewrite block tree: replace old WP URLs and clean up content.
     *
     * @param array $blocks CMS block tree
     * @param array<int, string> $attachmentMap WP attachment ID => CMS asset ID
     * @param string $oldBaseUrl Original WordPress site URL
     * @return array Rewritten block tree
     */
    public function rewrite(array $blocks, array $attachmentMap, string $oldBaseUrl): array
    {
        return array_map(fn($block) => $this->rewriteBlock($block, $attachmentMap, $oldBaseUrl), $blocks);
    }

    private function rewriteBlock(array $block, array $attachmentMap, string $oldBaseUrl): array
    {
        $data = $block['data'] ?? [];

        // Handle image blocks with wp_attachment_id
        if ($block['type'] === 'image' && !empty($data['wp_attachment_id'])) {
            $wpId = (int) $data['wp_attachment_id'];
            if (isset($attachmentMap[$wpId])) {
                $asset = Asset::find($attachmentMap[$wpId]);
                if ($asset) {
                    $data['asset_id'] = $asset->id;
                    $data['url'] = "/api/v1/sites/{$asset->site_id}/assets/{$asset->id}/serve";
                }
            }
            unset($data['wp_attachment_id']);
        }

        // Rewrite URLs in image src
        if ($block['type'] === 'image' && !empty($data['url'])) {
            $data['url'] = $this->rewriteUrl($data['url'], $attachmentMap, $oldBaseUrl);
        }

        // Rewrite URLs in hero background_image
        if ($block['type'] === 'hero' && !empty($data['background_image'])) {
            $data['background_image'] = $this->rewriteUrl($data['background_image'], $attachmentMap, $oldBaseUrl);
        }

        // Rewrite URLs in text/content HTML
        if (!empty($data['content'])) {
            $data['content'] = $this->rewriteHtml($data['content'], $attachmentMap, $oldBaseUrl);
        }

        $block['data'] = $data;

        // Recurse into children
        if (!empty($block['children'])) {
            $block['children'] = $this->rewrite($block['children'], $attachmentMap, $oldBaseUrl);
        }

        return $block;
    }

    /**
     * Rewrite a single URL if it matches a known attachment.
     */
    private function rewriteUrl(string $url, array $attachmentMap, string $oldBaseUrl): string
    {
        // Check if URL is from the old WP site
        if (!$this->isOldSiteUrl($url, $oldBaseUrl)) {
            return $url;
        }

        // Try to find matching asset by URL pattern in attachment map
        foreach ($attachmentMap as $wpId => $assetId) {
            $asset = Asset::find($assetId);
            if ($asset) {
                return "/api/v1/sites/{$asset->site_id}/assets/{$asset->id}/serve";
            }
        }

        return $url;
    }

    /**
     * Rewrite all URLs in HTML content.
     */
    private function rewriteHtml(string $html, array $attachmentMap, string $oldBaseUrl): string
    {
        // Rewrite image src attributes
        $html = preg_replace_callback(
            '/(<img[^>]+src=["\'])([^"\']+)(["\'])/i',
            function ($m) use ($attachmentMap, $oldBaseUrl) {
                return $m[1] . $this->rewriteUrl($m[2], $attachmentMap, $oldBaseUrl) . $m[3];
            },
            $html
        );

        // Strip WordPress-specific CSS classes
        $html = preg_replace('/\s*class="[^"]*(?:wp-block|has-\S+-font-size|has-\S+-color)[^"]*"/', '', $html);

        // Clean up empty class attributes
        $html = preg_replace('/\s*class="\s*"/', '', $html);

        // Remove data-* attributes from WordPress
        $html = preg_replace('/\s*data-wp-[a-z-]+="[^"]*"/', '', $html);

        return $html;
    }

    private function isOldSiteUrl(string $url, string $oldBaseUrl): bool
    {
        if (empty($oldBaseUrl)) {
            return false;
        }

        $oldHost = parse_url($oldBaseUrl, PHP_URL_HOST);

        return $oldHost && str_contains($url, $oldHost);
    }
}
