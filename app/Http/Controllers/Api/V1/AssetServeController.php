<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadAssetRequest;
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
            self::headersFor((string) $mimeType)
        );
    }

    /**
     * Response headers under the active-content policy (F06): only media
     * the browser renders passively is sent inline; anything else — and any
     * executable/markup type that reached the DB (legacy rows) — is a
     * download with a neutral Content-Type. Sanitized SVG stays inline but
     * with a CSP that forbids scripts.
     */
    public static function headersFor(string $mimeType): array
    {
        $base = strtolower(trim(explode(';', $mimeType)[0]));
        $headers = [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($base === 'image/svg+xml') {
            return $headers + [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'inline',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'none'",
            ];
        }
        if (UploadAssetRequest::isActiveContentMime($base)) {
            return $headers + [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment',
            ];
        }
        $inline = str_starts_with($base, 'image/') || str_starts_with($base, 'video/')
            || str_starts_with($base, 'audio/') || str_starts_with($base, 'font/')
            || $base === 'application/pdf';

        return $headers + [
            'Content-Type' => $mimeType,
            'Content-Disposition' => $inline ? 'inline' : 'attachment',
        ];
    }
}
