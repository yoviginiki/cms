<?php

namespace App\Domain\Assets\Services;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AssetService
{
    /**
     * Hosts external images may be imported from (site generation, demo
     * content). Deliberately narrow — importFromUrl fetches server-side, so
     * an open list would be an SSRF vector.
     */
    public const IMPORT_ALLOWED_HOSTS = [
        'loremflickr.com',
        'images.pexels.com',
        'picsum.photos',
        'fastly.picsum.photos',
    ];

    private const IMPORT_MAX_BYTES = 10 * 1024 * 1024;

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Import an external image into the media library (F-pipeline: generated
     * sites should reference library assets — WebP variants, dimensions, alt —
     * instead of hotlinking). Returns null on any failure so callers can fall
     * back to the external URL; checksum dedupe in upload() makes re-imports
     * (idempotent re-generation) reuse the existing asset.
     */
    public function importFromUrl(Site $site, string $url, ?string $altText = null, ?string $name = null): ?Asset
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, self::IMPORT_ALLOWED_HOSTS, true)) {
            return null;
        }

        $tmp = null;
        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withUserAgent('Stillopress-Importer/1.0')
                ->get($url);
            if (!$response->successful()) {
                return null;
            }

            $body = $response->body();
            if ($body === '' || strlen($body) > self::IMPORT_MAX_BYTES) {
                return null;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'img_import_');
            file_put_contents($tmp, $body);

            // Trust the bytes, not the headers: must decode as a real image.
            $size = @getimagesize($tmp);
            if (!$size || empty($size['mime']) || !str_starts_with($size['mime'], 'image/')) {
                return null;
            }
            $ext = match ($size['mime']) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                default => null,
            };
            if (!$ext) {
                return null;
            }

            $filename = ($name ? Str::slug($name) : 'imported-' . substr(md5($url), 0, 8)) . ".{$ext}";
            $asset = $this->upload($site, new UploadedFile($tmp, $filename, $size['mime'], null, true));

            if ($altText && !$asset->alt_text) {
                $asset->update(['alt_text' => $altText]);
            }

            return $asset;
        } catch (\Throwable $e) {
            logger()->warning("Image import failed for {$url}: {$e->getMessage()}");

            return null;
        } finally {
            if ($tmp) {
                @unlink($tmp);
            }
        }
    }

    public function upload(Site $site, UploadedFile $file): Asset
    {
        $checksum = hash_file('sha256', $file->getRealPath());

        // Deduplicate by checksum within site
        $existing = Asset::where('site_id', $site->id)
            ->where('checksum', $checksum)
            ->first();

        if ($existing) {
            return $existing;
        }

        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension();
        $basePath = "sites/{$site->id}/assets";
        $storagePath = "{$basePath}/{$uuid}.{$extension}";

        $mimeType = $file->getMimeType();

        // SVG is markup, not pixels: scrub scripts/handlers/external refs at
        // the door (S7 — previously stored raw). Unparseable SVG is rejected.
        $contents = file_get_contents($file->getRealPath());
        if ($mimeType === 'image/svg+xml') {
            $contents = app(SvgSanitizer::class)->sanitize($contents);
            if ($contents === null) {
                throw new \InvalidArgumentException('SVG file could not be sanitized — upload rejected.');
            }
        }
        Storage::disk('assets')->put($storagePath, $contents);

        $dimensions = null;
        $variants = [];

        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            $imageSize = getimagesize($file->getRealPath());
            if ($imageSize) {
                $dimensions = ['width' => $imageSize[0], 'height' => $imageSize[1]];
            }

            $variants = $this->generateImageVariants($file->getRealPath(), $basePath, $uuid);
        }

        return Asset::create([
            'site_id' => $site->id,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'dimensions' => $dimensions,
            'variants' => $variants,
            'checksum' => $checksum,
        ]);
    }

    public function delete(Asset $asset): void
    {
        $disk = Storage::disk('assets');

        // Delete original
        $disk->delete($asset->storage_path);

        // Delete variants
        foreach ($asset->variants as $path) {
            $disk->delete($path);
        }

        $asset->delete();
    }

    /**
     * (Re)generate variants for an existing image asset — backfill for assets
     * uploaded while variant generation was broken (they have none), or after
     * the variant set changes. Updates the asset row and returns the map.
     */
    public function regenerateVariants(Asset $asset): array
    {
        if (!str_starts_with((string) $asset->mime_type, 'image/') || $asset->mime_type === 'image/svg+xml') {
            return $asset->variants ?? [];
        }

        $disk = Storage::disk('assets');
        if (!$asset->storage_path || !$disk->exists($asset->storage_path)) {
            return $asset->variants ?? [];
        }

        // Work from a local temp copy — storage-driver agnostic
        $ext = pathinfo($asset->storage_path, PATHINFO_EXTENSION);
        $tmp = tempnam(sys_get_temp_dir(), 'asset_') . ($ext ? ".{$ext}" : '');
        file_put_contents($tmp, $disk->get($asset->storage_path));

        try {
            $variants = $this->generateImageVariants(
                $tmp,
                dirname($asset->storage_path),
                pathinfo($asset->storage_path, PATHINFO_FILENAME)
            );

            $update = ['variants' => $variants];
            if (!$asset->dimensions && ($size = @getimagesize($tmp))) {
                $update['dimensions'] = ['width' => $size[0], 'height' => $size[1]];
            }
            $asset->update($update);

            return $variants;
        } finally {
            @unlink($tmp);
        }
    }

    private function generateImageVariants(string $sourcePath, string $basePath, string $uuid): array
    {
        $variants = [];
        $disk = Storage::disk('assets');

        try {
            $image = $this->imageManager->decodePath($sourcePath);

            // thumb_200: 200px square crop
            $thumb = clone $image;
            $thumb->cover(200, 200);
            $thumbPath = "{$basePath}/{$uuid}_thumb_200.jpg";
            $disk->put($thumbPath, $thumb->encodeUsingFileExtension('jpg', 80));
            $variants['thumb_200'] = $thumbPath;

            // medium_800: 800px wide, maintain aspect
            if ($image->width() > 800) {
                $medium = clone $image;
                $medium->scale(width: 800);
                $mediumPath = "{$basePath}/{$uuid}_medium_800.jpg";
                $disk->put($mediumPath, $medium->encodeUsingFileExtension('jpg', 85));
                $variants['medium_800'] = $mediumPath;

                // webp_800
                $webpPath = "{$basePath}/{$uuid}_webp_800.webp";
                $disk->put($webpPath, $medium->encodeUsingFileExtension('webp', 80));
                $variants['webp_800'] = $webpPath;
            }

            // small_400: 400px wide for mobile
            if ($image->width() > 400) {
                $small = clone $image;
                $small->scale(width: 400);
                $smallPath = "{$basePath}/{$uuid}_small_400.jpg";
                $disk->put($smallPath, $small->encodeUsingFileExtension('jpg', 80));
                $variants['small_400'] = $smallPath;

                $webp400Path = "{$basePath}/{$uuid}_webp_400.webp";
                $disk->put($webp400Path, $small->encodeUsingFileExtension('webp', 75));
                $variants['webp_400'] = $webp400Path;
            }

            // large_1600: for retina/large screens
            if ($image->width() > 1600) {
                $large = clone $image;
                $large->scale(width: 1600);
                $largePath = "{$basePath}/{$uuid}_large_1600.jpg";
                $disk->put($largePath, $large->encodeUsingFileExtension('jpg', 85));
                $variants['large_1600'] = $largePath;

                $webp1600Path = "{$basePath}/{$uuid}_webp_1600.webp";
                $disk->put($webp1600Path, $large->encodeUsingFileExtension('webp', 80));
                $variants['webp_1600'] = $webp1600Path;
            }
        } catch (\Throwable $e) {
            // Don't fail the upload over variant generation, but DON'T swallow
            // silently — a broken image library must be visible, not hidden.
            logger()->warning("Image variant generation failed for {$uuid}: {$e->getMessage()}");
            report($e);
        }

        return $variants;
    }
}
