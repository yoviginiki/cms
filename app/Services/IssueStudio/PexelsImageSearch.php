<?php

namespace App\Services\IssueStudio;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thin client over the Pexels photo-search API. Issue Studio uses it to find a
 * stock photo matching an image slot's art-direction note (+ the issue's tone /
 * genre) when the author hasn't supplied their own image.
 *
 * Fails soft everywhere: a missing API key, an empty query, a network error, or
 * no results all return null so the caller can fall back to the default image.
 * No attribution is legally required for Pexels, but we return the photographer
 * so it can be stored on the asset as a courtesy credit.
 */
class PexelsImageSearch
{
    private const ENDPOINT = 'https://api.pexels.com/v1/search';

    /**
     * @return array{url:string, photographer:?string, photographer_url:?string, alt:?string, id:?int}|null
     */
    public function search(string $query, string $orientation = 'landscape'): ?array
    {
        $key = (string) config('services.pexels.key');
        $query = trim($query);
        if ($key === '' || $query === '') {
            return null;
        }

        $orientation = in_array($orientation, ['landscape', 'portrait', 'square'], true)
            ? $orientation
            : 'landscape';

        try {
            $response = Http::withHeaders(['Authorization' => $key])
                ->timeout(8)
                ->retry(1, 200)
                ->get(self::ENDPOINT, [
                    'query' => Str::limit($query, 120, ''),
                    'per_page' => 1,
                    'orientation' => $orientation,
                ]);

            if (!$response->ok()) {
                Log::warning('Pexels search non-OK: ' . $response->status());

                return null;
            }

            $photo = $response->json('photos.0');
            if (!is_array($photo)) {
                return null;
            }

            // Prefer a print-friendly size; fall back down the ladder.
            $src = $photo['src'] ?? [];
            $url = $src['large2x'] ?? $src['large'] ?? $src['original'] ?? null;
            if (!is_string($url) || $url === '') {
                return null;
            }

            return [
                'url' => $url,
                'photographer' => $photo['photographer'] ?? null,
                'photographer_url' => $photo['photographer_url'] ?? null,
                'alt' => $photo['alt'] ?? null,
                'id' => $photo['id'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Pexels search failed: ' . $e->getMessage());

            return null;
        }
    }
}
