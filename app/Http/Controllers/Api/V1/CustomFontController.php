<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomFontController extends Controller
{
    /**
     * List custom fonts for a site.
     */
    public function index(Site $site): JsonResponse
    {
        $fonts = $site->settings['custom_fonts'] ?? [];
        return response()->json(['data' => array_values($fonts)]);
    }

    /**
     * Upload a custom font file (TTF, WOFF, WOFF2, OTF).
     */
    public function store(Request $request, Site $site): JsonResponse
    {
        $request->validate([
            'font' => ['required', 'file', 'max:5120', 'mimes:ttf,woff,woff2,otf'],
            'family' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9\s\-]+$/'],
            'weight' => ['sometimes', 'integer', 'min:100', 'max:900'],
            'style' => ['sometimes', 'string', 'in:normal,italic'],
        ]);

        $file = $request->file('font');
        $family = $request->input('family');
        $weight = $request->input('weight', 400);
        $fontStyle = $request->input('style', 'normal');
        $ext = $file->getClientOriginalExtension() ?: 'ttf';

        // Sanitize family name for filename
        $safeFamily = Str::slug($family);
        $filename = "{$safeFamily}-{$weight}-{$fontStyle}.{$ext}";

        // Store in fonts directory
        $path = "fonts/{$site->id}/{$filename}";
        Storage::disk('assets')->put($path, file_get_contents($file->getRealPath()));

        // MIME type for CSS
        $format = match ($ext) {
            'woff2' => 'woff2',
            'woff' => 'woff',
            'otf' => 'opentype',
            default => 'truetype',
        };

        // Add to site settings
        $settings = $site->settings ?? [];
        $fonts = $settings['custom_fonts'] ?? [];

        $fontEntry = [
            'id' => Str::uuid()->toString(),
            'family' => $family,
            'weight' => (int) $weight,
            'style' => $fontStyle,
            'filename' => $filename,
            'path' => $path,
            'format' => $format,
            'ext' => $ext,
        ];

        $fonts[] = $fontEntry;
        $settings['custom_fonts'] = $fonts;
        $site->update(['settings' => $settings]);

        return response()->json(['data' => $fontEntry], 201);
    }

    /**
     * Delete a custom font.
     */
    public function destroy(Request $request, Site $site, string $fontId): JsonResponse
    {
        $settings = $site->settings ?? [];
        $fonts = $settings['custom_fonts'] ?? [];

        $font = collect($fonts)->firstWhere('id', $fontId);
        if (!$font) {
            return response()->json(['message' => 'Font not found'], 404);
        }

        // Delete file
        Storage::disk('assets')->delete($font['path'] ?? '');

        // Remove from settings
        $settings['custom_fonts'] = collect($fonts)->where('id', '!=', $fontId)->values()->all();
        $site->update(['settings' => $settings]);

        return response()->json(null, 204);
    }

    /**
     * Serve a font file (public, for preview and static sites).
     */
    public function serve(Site $site, string $filename)
    {
        $path = "fonts/{$site->id}/{$filename}";
        $disk = Storage::disk('assets');

        if (!$disk->exists($path)) {
            abort(404);
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $mime = match ($ext) {
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'otf' => 'font/otf',
            default => 'font/ttf',
        };

        return response()->stream(
            fn() => fpassthru($disk->readStream($path)),
            200,
            [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Access-Control-Allow-Origin' => '*',
            ]
        );
    }
}
