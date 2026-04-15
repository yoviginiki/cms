<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetServeController extends Controller
{
    public function serve(Site $site, Asset $asset, ?string $variant = null): StreamedResponse
    {
        $path = $asset->storage_path;
        $mimeType = $asset->mime_type;

        if ($variant && isset($asset->variants[$variant])) {
            $path = $asset->variants[$variant];

            // Determine MIME from variant path
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $mimeType = match ($ext) {
                'webp' => 'image/webp',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                default => $asset->mime_type,
            };
        }

        $disk = Storage::disk('assets');

        if (!$disk->exists($path)) {
            abort(404);
        }

        return response()->stream(
            fn() => fpassthru($disk->readStream($path)),
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
