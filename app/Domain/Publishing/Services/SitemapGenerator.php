<?php
namespace App\Domain\Publishing\Services;

use App\Models\Site;

class SitemapGenerator
{
    public function generate(Site $site): string
    {
        $baseUrl = $site->custom_domain ? "https://{$site->custom_domain}" : "https://{$site->slug}.ensodo.eu";
        $urls = [];

        // Homepage
        $urls[] = $this->urlEntry($baseUrl . '/', '1.0', 'daily', now()->toW3cString());

        // Pages (locale-aware paths; the default-locale homepage is already listed)
        $pages = $site->pages()->where('status', 'published')->orderBy('sort_order')->get();
        foreach ($pages as $page) {
            $path = LocalePaths::urlPath($site, $page);
            if ($path === '/') continue; // homepage entry above
            $urls[] = $this->urlEntry(
                rtrim($baseUrl . $path, '/'),
                '0.8', 'weekly', $page->updated_at->toW3cString()
            );
        }

        // Categories
        $categories = $site->categories()->whereHas('posts', fn($q) => $q->where('status', 'published'))->get();
        foreach ($categories as $cat) {
            $urls[] = $this->urlEntry(
                $baseUrl . '/' . $cat->slug,
                '0.6', 'weekly'
            );
        }

        // Posts
        $posts = $site->posts()->where('status', 'published')->orderByDesc('published_at')->get();
        foreach ($posts as $post) {
            $urls[] = $this->urlEntry(
                rtrim($baseUrl . LocalePaths::urlPath($site, $post), '/'),
                '0.7', 'monthly', $post->updated_at->toW3cString()
            );
        }

        // Published magazines (statically deployed under /magazine/…)
        $issues = \App\Domain\IssueComposer\Models\MagazineIssue::where('site_id', $site->id)
            ->where('status', 'published')->get();
        $magPublisher = app(\App\Domain\Publishing\Services\MagazineStaticPublisher::class);
        foreach ($issues as $issue) {
            $urls[] = $this->urlEntry(
                $baseUrl . '/' . $magPublisher->issuePath($issue) . '/',
                '0.7', 'monthly', $issue->updated_at?->toW3cString()
            );
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= implode("\n", $urls);
        $xml .= "\n</urlset>";

        return $xml;
    }

    private function urlEntry(string $loc, string $priority, string $changefreq, ?string $lastmod = null): string
    {
        $entry = "  <url>\n    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        $entry .= "    <priority>{$priority}</priority>\n";
        $entry .= "    <changefreq>{$changefreq}</changefreq>\n";
        if ($lastmod) {
            $entry .= "    <lastmod>{$lastmod}</lastmod>\n";
        }
        $entry .= "  </url>";
        return $entry;
    }
}
