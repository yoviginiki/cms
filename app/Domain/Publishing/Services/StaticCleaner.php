<?php

namespace App\Domain\Publishing\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Facades\Log;

/**
 * Removes static output files from the live docroot when content is
 * deleted, unpublished, or moved (slug/category/language change).
 * The publisher only ever writes files — this is the delete half.
 */
class StaticCleaner
{
    /** Live docroot for a site — same resolution as DeployService::deployLocal(). */
    public static function docroot(Site $site): ?string
    {
        if ($site->custom_domain) {
            $tenantBase = config('publishing.tenant_base', '/home/cytechno/web');
            $safeDomain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $site->custom_domain);
            if (!$safeDomain || str_contains($safeDomain, '..')) return null;
            $path = $tenantBase . '/' . $safeDomain . '/public_html';
        } else {
            $path = config('publishing.public_path') . '/' . $site->deploySlug();
        }

        return is_dir($path) ? $path : null;
    }

    /** Remove the published static file(s) of a page/post from the live site. */
    public function removeContent(Site $site, Page|Post $content): void
    {
        $relative = $content instanceof Post
            ? LocalePaths::postPath($site, $content)
            : LocalePaths::pagePath($site, $content);

        $this->removePath($site, $relative);
    }

    /**
     * Remove one published file (e.g. 'about/index.html') and prune the
     * directories it leaves empty. Never touches anything outside the docroot
     * and never deletes the docroot itself.
     */
    public function removePath(Site $site, string $relativeFile): void
    {
        $docroot = self::docroot($site);
        if (!$docroot) return;

        // Normalize + containment check (no traversal, no absolute input)
        $relativeFile = ltrim($relativeFile, '/');
        if ($relativeFile === '' || str_contains($relativeFile, '..')) return;

        $file = $docroot . '/' . $relativeFile;
        $realDocroot = realpath($docroot);
        $realDir = realpath(dirname($file));
        if (!$realDocroot || !$realDir || !str_starts_with($realDir . '/', $realDocroot . '/')) return;

        if (is_file($file)) {
            @unlink($file);
            Log::info("StaticCleaner: removed {$relativeFile} from {$docroot}");
        }

        // Prune now-empty directories up to (but never including) the docroot
        $dir = dirname($file);
        while ($dir !== $realDocroot && realpath($dir) !== $realDocroot && is_dir($dir)) {
            if (count(scandir($dir)) !== 2) break; // not empty
            @rmdir($dir);
            $dir = dirname($dir);
        }
    }
}
