<?php

namespace App\Domain\Assets\Services;

use App\Models\Asset;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AssetService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
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

        Storage::disk('assets')->put($storagePath, file_get_contents($file->getRealPath()));

        $mimeType = $file->getMimeType();
        $dimensions = null;
        $variants = [];

        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            $imageSize = getimagesize($file->getRealPath());
            if ($imageSize) {
                $dimensions = ['width' => $imageSize[0], 'height' => $imageSize[1]];
            }

            $variants = $this->generateImageVariants($file, $basePath, $uuid);
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

    private function generateImageVariants(UploadedFile $file, string $basePath, string $uuid): array
    {
        $variants = [];
        $disk = Storage::disk('assets');

        try {
            $image = $this->imageManager->read($file->getRealPath());

            // thumb_200: 200px square crop
            $thumb = clone $image;
            $thumb->cover(200, 200);
            $thumbPath = "{$basePath}/{$uuid}_thumb_200.jpg";
            $disk->put($thumbPath, $thumb->toJpeg(80)->toString());
            $variants['thumb_200'] = $thumbPath;

            // medium_800: 800px wide, maintain aspect
            if ($image->width() > 800) {
                $medium = clone $image;
                $medium->scale(width: 800);
                $mediumPath = "{$basePath}/{$uuid}_medium_800.jpg";
                $disk->put($mediumPath, $medium->toJpeg(85)->toString());
                $variants['medium_800'] = $mediumPath;

                // webp_800
                $webpPath = "{$basePath}/{$uuid}_webp_800.webp";
                $disk->put($webpPath, $medium->toWebp(80)->toString());
                $variants['webp_800'] = $webpPath;
            }
        } catch (\Throwable) {
            // Image processing failed, continue without variants
        }

        return $variants;
    }
}
