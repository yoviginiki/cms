<?php

namespace App\Domain\Publishing\Services;

use App\Models\Page;
use App\Models\Post;
use App\Models\Site;

/**
 * Removes stale published files left behind by slug renames (§7). Full
 * publishes prune them naturally (copyDeploy keep-list / symlink swap);
 * delta promotes merge per-file and would leave the old URL serving the
 * old content forever. PageService/PostService record `_previous_paths`
 * (server-managed) on rename; this deletes them from the live docroot
 * after a successful delta promote, then clears the record.
 *
 * Hardened deliberately: seo_meta is a client-writable blob (the key is
 * stripped in the form requests, but defense in depth) — only `*.html`
 * files strictly inside the site's docroot are ever deleted.
 */
class StalePathCleaner
{
    /** @param array $built the delta batch's built items (type/id/path) */
    public function removeFor(Site $site, array $built, ?string $docroot = null): array
    {
        $docroot = $docroot ?? $this->liveDocroot($site);
        $docrootReal = realpath($docroot);
        if (!$docrootReal) {
            return [];
        }

        $removed = [];
        foreach ($built as $item) {
            $model = ($item['type'] ?? '') === 'page' ? Page::find($item['id']) : Post::find($item['id']);
            if (!$model) {
                continue;
            }
            $prev = $model->seo_meta['_previous_paths'] ?? [];
            if ($prev === []) {
                continue;
            }

            foreach ((array) $prev as $rel) {
                $rel = ltrim((string) $rel, '/');
                if ($rel === '' || $rel === ($item['path'] ?? null) || str_contains($rel, '..') || !str_ends_with($rel, '.html')) {
                    continue;
                }
                $real = realpath($docroot . '/' . $rel);
                if ($real && str_starts_with($real, $docrootReal . '/') && is_file($real)) {
                    @unlink($real);
                    $removed[] = $rel;
                    // prune now-empty parent directories up to the docroot
                    $dir = dirname($real);
                    while ($dir !== $docrootReal && is_dir($dir) && count(scandir($dir) ?: []) <= 2) {
                        @rmdir($dir);
                        $dir = dirname($dir);
                    }
                }
            }

            // Clear the record without bumping updated_at
            $meta = $model->seo_meta ?? [];
            unset($meta['_previous_paths']);
            $model->newQuery()->whereKey($model->getKey())->toBase()->update(['seo_meta' => json_encode($meta)]);
        }

        return $removed;
    }

    public function liveDocroot(Site $site): string
    {
        if ($site->custom_domain) {
            $tenantBase = rtrim(config('publishing.tenant_base', '/home/cytechno/web'), '/');
            $safeDomain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $site->custom_domain);

            return "{$tenantBase}/{$safeDomain}/public_html";
        }

        return config('publishing.public_path') . '/' . $site->slug;
    }
}
