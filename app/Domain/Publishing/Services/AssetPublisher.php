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
     * Resolve an asset ID or API URL to a static public URL.
     * Copies the file to public_html if not already there.
     */
    public static function resolveUrl(?string $assetId = null, ?string $apiUrl = null): ?string
    {
        // Extract asset ID from API URL if needed
        if (!$assetId && $apiUrl) {
            if (preg_match('/assets\/([0-9a-f-]{36})\/serve/', $apiUrl, $m)) {
                $assetId = $m[1];
            }
        }

        if (!$assetId) return $apiUrl; // Can't resolve, return as-is

        // Check cache
        if (isset(self::$published[$assetId])) {
            return self::$published[$assetId];
        }

        $asset = Asset::find($assetId);
        if (!$asset || !$asset->storage_path) {
            return $apiUrl;
        }

        $disk = Storage::disk('assets');
        if (!$disk->exists($asset->storage_path)) {
            return $apiUrl;
        }

        // Determine public path based on file extension
        $ext = pathinfo($asset->storage_path, PATHINFO_EXTENSION) ?: 'bin';
        $hash = $asset->checksum ?: md5($asset->id);
        $publicPath = "/assets/files/{$hash}.{$ext}";

        // Copy to deploy target (public_html of the correct domain)
        $targetBase = self::$deployTarget ?: base_path('../../public_html');
        $fullPath = $targetBase . $publicPath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (!file_exists($fullPath)) {
            $contents = $disk->get($asset->storage_path);
            file_put_contents($fullPath, $contents);
            @chmod($fullPath, 0664);
        }

        self::$published[$assetId] = $publicPath;
        return $publicPath;
    }

    /**
     * Rewrite all /api/v1/.../assets/.../serve URLs in an HTML string to static URLs.
     */
    public static function rewriteHtml(string $html): string
    {
        return preg_replace_callback(
            '#/api/v1/sites/[0-9a-f-]+/assets/([0-9a-f-]{36})/serve(?:/[a-z]+)?#',
            function ($matches) {
                $url = self::resolveUrl($matches[1]);
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
