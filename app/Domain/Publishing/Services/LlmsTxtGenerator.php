<?php
namespace App\Domain\Publishing\Services;

use App\Models\Site;

/**
 * /llms.txt generated at publish (Track F4 — AI readability).
 *
 * Format per the llmstxt.org proposal (published 2024-09-03, no formal
 * version number; spec re-verified live 2026-07-12): required H1 with the
 * site name, optional blockquote summary, then H2-delimited sections of
 * markdown link lists ("- [name](url): notes"), with a special "Optional"
 * section for secondary URLs.
 *
 * Disable per site via settings.llms_txt = false (default: generated).
 */
class LlmsTxtGenerator
{
    public function generate(Site $site): ?string
    {
        $settings = $site->settings ?? [];
        if (($settings['llms_txt'] ?? true) === false) {
            return null;
        }

        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
        $summary = trim((string) ($site->seo_defaults['description']
            ?? $settings['business_description']
            ?? $settings['tagline']
            ?? ''));

        $md = '# ' . $this->text($site->name) . "\n\n";
        if ($summary !== '') {
            $md .= '> ' . $this->text($summary) . "\n\n";
        }

        $pages = $site->pages()->where('status', 'published')->orderBy('sort_order')->get();
        if ($pages->isNotEmpty()) {
            $md .= "## Pages\n\n";
            foreach ($pages as $page) {
                $md .= $this->link(
                    $page->title,
                    $baseUrl . LocalePaths::urlPath($site, $page),
                    $page->seo_meta['description'] ?? ''
                );
            }
            $md .= "\n";
        }

        $posts = $site->posts()->where('status', 'published')->orderByDesc('published_at')->limit(20)->get();
        if ($posts->isNotEmpty()) {
            $md .= "## Posts\n\n";
            foreach ($posts as $post) {
                $md .= $this->link(
                    $post->title,
                    $baseUrl . LocalePaths::urlPath($site, $post),
                    $post->excerpt ?? ''
                );
            }
            $md .= "\n";
        }

        $md .= "## Optional\n\n";
        $md .= "- [Sitemap]({$baseUrl}/sitemap.xml): full URL index\n";
        $md .= "- [RSS feed]({$baseUrl}/feed.xml): latest posts\n";

        return $md;
    }

    private function link(string $title, string $url, string $notes): string
    {
        $line = '- [' . $this->text($title) . '](' . $url . ')';
        $notes = $this->text($notes);
        if ($notes !== '') {
            $line .= ': ' . $notes;
        }

        return $line . "\n";
    }

    /** Single-line, bracket-safe plain text for markdown link lists. */
    private function text(string $value): string
    {
        return trim(str_replace(['[', ']', "\r", "\n"], ['(', ')', ' ', ' '], strip_tags($value)));
    }
}
