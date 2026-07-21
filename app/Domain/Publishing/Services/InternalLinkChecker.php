<?php
namespace App\Domain\Publishing\Services;

use Illuminate\Support\Facades\File;

/**
 * Publish-time internal link check (F5 SEO lint). Scans the built HTML in
 * the staging tree for site-relative hrefs and reports links no built file
 * satisfies. Warning-only: external URLs, fragments, query links, and
 * /admin are ignored. Findings are capped so a systematic breakage (e.g. a
 * renamed category) doesn't flood the deploy log.
 */
class InternalLinkChecker
{
    /**
     * @param string|null $basePrefix URL prefix the site is served under
     *        (slug-hosted sites emit "/{slug}/…" links; the build tree has no
     *        such directory, so strip it before resolving against staging).
     */
    public function check(string $stagingPath, int $maxWarnings = 50, ?string $basePrefix = null): array
    {
        $warnings = [];
        if (!File::isDirectory($stagingPath)) {
            return $warnings;
        }

        $stagingPath = rtrim($stagingPath, '/');

        foreach (File::allFiles($stagingPath) as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }
            $relSource = ltrim(str_replace($stagingPath, '', $file->getPathname()), '/');
            if (!preg_match_all('/href="(\/[^"#?]*)/', File::get($file->getPathname()), $m)) {
                continue;
            }
            foreach (array_unique($m[1]) as $href) {
                if ($basePrefix && ($href === $basePrefix || str_starts_with($href, $basePrefix . '/'))) {
                    $href = substr($href, strlen($basePrefix)) ?: '/';
                }
                if ($href === '/' || $href === '' || str_starts_with($href, '/admin') || str_starts_with($href, '//')) {
                    continue;
                }
                if (!$this->targetExists($stagingPath, $href)) {
                    $warnings[] = "{$relSource}: broken internal link {$href}";
                }
                if (count($warnings) >= $maxWarnings) {
                    $warnings[] = "… link check truncated at {$maxWarnings} findings";

                    return $warnings;
                }
            }
        }

        return $warnings;
    }

    private function targetExists(string $stagingPath, string $href): bool
    {
        $path = rtrim($href, '/');

        // File links (assets, feeds, documents) must exist verbatim
        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return File::exists($stagingPath . $path);
        }

        // Page links resolve to a directory index or a flat .html file
        return File::exists("{$stagingPath}{$path}/index.html")
            || File::exists("{$stagingPath}{$path}.html")
            || File::exists("{$stagingPath}{$path}/index.htm");
    }
}
