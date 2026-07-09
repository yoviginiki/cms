<?php

namespace App\Domain\Publishing\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Storage;

/**
 * Copies assets to the public_html directory during publish
 * and provides static URLs for published pages.
 */
class AssetPublisher
{
    private static array $published = [];
    private static ?string $deployTarget = null;

    /**
     * Set the deploy target path for asset publishing.
     * Called by PublishSiteJob before building pages.
     */
    public static function setDeployTarget(?string $path): void
    {
        self::$deployTarget = $path;
    }

    /**
     * Resolve an asset ID (optionally a named variant) or API URL to a static
     * public URL, publishing the underlying file to the deploy target.
     *
     * $variant is a key in $asset->variants (e.g. 'webp_800', 'thumb_200').
     * When it resolves to a real variant file, THAT file is published (not the
     * original), so responsive/WebP srcsets point at optimized assets.
     */
    public static function resolveUrl(?string $assetId = null, ?string $apiUrl = null, ?string $variant = null): ?string
    {
        // Extract asset ID (+ optional variant) from API URL if needed
        if (!$assetId && $apiUrl) {
            if (preg_match('#assets/([0-9a-f-]{36})/serve(?:/([a-z0-9_]+))?#', $apiUrl, $m)) {
                $assetId = $m[1];
                $variant = $variant ?? ($m[2] ?? null);
            }
        }

        if (!$assetId) return $apiUrl; // Can't resolve, return as-is

        $cacheKey = $variant ? "{$assetId}:{$variant}" : $assetId;
        if (isset(self::$published[$cacheKey])) {
            return self::$published[$cacheKey];
        }

        $asset = Asset::find($assetId);
        if (!$asset || !$asset->storage_path) {
            return $apiUrl;
        }

        // Pick the variant file when requested and available, else the original.
        $variants = $asset->variants ?? [];
        $storagePath = ($variant && !empty($variants[$variant])) ? $variants[$variant] : $asset->storage_path;

        $disk = Storage::disk('assets');
        if (!$disk->exists($storagePath)) {
            // Requested variant missing (e.g. not generated) — fall back to original.
            if ($storagePath !== $asset->storage_path && $disk->exists($asset->storage_path)) {
                $storagePath = $asset->storage_path;
                $variant = null;
            } else {
                return $apiUrl;
            }
        }

        // Content-hashed public filename; the variant suffix disambiguates the
        // several derivatives that share one source asset (and its checksum).
        $ext = pathinfo($storagePath, PATHINFO_EXTENSION) ?: 'bin';
        $hash = $asset->checksum ?: md5($asset->id);
        $name = $variant ? "{$hash}_{$variant}.{$ext}" : "{$hash}.{$ext}";
        $publicPath = "/assets/files/{$name}";

        // Copy to deploy target (public_html of the correct domain)
        $targetBase = self::$deployTarget ?: base_path('../../public_html');
        $fullPath = $targetBase . $publicPath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (!file_exists($fullPath)) {
            $contents = $disk->get($storagePath);
            file_put_contents($fullPath, $contents);
            @chmod($fullPath, 0664);
        }

        self::$published[$cacheKey] = $publicPath;
        return $publicPath;
    }

    /**
     * Rewrite all /api/v1/.../assets/.../serve[/variant] URLs in an HTML string
     * to static URLs, preserving and publishing the requested variant.
     */
    public static function rewriteHtml(string $html): string
    {
        return preg_replace_callback(
            '#/api/v1/sites/[0-9a-f-]+/assets/([0-9a-f-]{36})/serve(?:/([a-z0-9_]+))?#',
            function ($matches) {
                $url = self::resolveUrl($matches[1], null, $matches[2] ?? null);
                return $url ?: $matches[0];
            },
            $html
        );
    }

    /**
     * Reset the publish cache (call at start of each publish run).
     */
    public static function reset(): void
    {
        self::$published = [];
        self::$deployTarget = null;
    }
}
