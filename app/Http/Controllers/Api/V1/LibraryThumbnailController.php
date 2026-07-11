<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a Library item's cached preview thumbnail (Builder P1 Slice E) from
 * the `assets` disk. Public + keyed by the item's uuid (unguessable) — the
 * image is a low-sensitivity preview, so no per-tenant auth, mirroring how
 * AssetServeController streams public media.
 */
class LibraryThumbnailController extends Controller
{
    public function serve(string $id): StreamedResponse
    {
        $path = "library-thumbs/{$id}.png";
        $disk = Storage::disk('assets');

        abort_unless($disk->exists($path), 404);

        return response()->stream(
            fn () => fpassthru($disk->readStream($path)),
            200,
            [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'public, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
