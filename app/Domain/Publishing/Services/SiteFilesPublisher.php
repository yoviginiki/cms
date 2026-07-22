<?php

namespace App\Domain\Publishing\Services;

use App\Models\Site;
use Illuminate\Support\Facades\File;

/**
 * Ships a site's verbatim design files (CSS/JS/images/fonts an exact-copy
 * import preserved from the original package) with every static build.
 *
 * The files live under storage/app/site-files/{site_id}/ mirroring the
 * package's own tree, so relative references INSIDE the files (a font next to
 * its CSS) keep working without any rewriting. Pages reference them as
 * /api/v1/sites/{id}/files/{path} — served with auth in the admin preview,
 * swapped to the static /site-files/{path} copy at publish time (same pattern
 * as AssetPublisher's serve-URL rewrite).
 */
class SiteFilesPublisher
{
    public static function storageRoot(Site $site): string
    {
        $base = rtrim((string) config('cms.site_files_path', ''), '/');
        if ($base === '') {
            $base = storage_path('app/site-files');
        } elseif (!str_starts_with($base, '/')) {
            $base = base_path($base);
        }

        return "{$base}/{$site->id}";
    }

    public static function hasFiles(Site $site): bool
    {
        return is_dir(self::storageRoot($site));
    }

    /** Copy the whole tree into the staging build. Returns the file count. */
    public static function publish(Site $site, string $stagingPath): int
    {
        $root = self::storageRoot($site);
        if (!is_dir($root)) {
            return 0;
        }

        $target = rtrim($stagingPath, '/') . '/site-files';
        File::copyDirectory($root, $target);

        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $count += $f->isFile() ? 1 : 0;
        }

        return $count;
    }

    /** Swap serve URLs for the static copy that publish() ships. */
    public static function rewriteHtml(string $html, Site $site): string
    {
        return str_replace("/api/v1/sites/{$site->id}/files/", '/site-files/', $html);
    }
}
