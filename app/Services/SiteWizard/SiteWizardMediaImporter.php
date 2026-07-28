<?php

namespace App\Services\SiteWizard;

use App\Domain\Assets\Services\AssetService;
use App\Models\Asset;
use App\Models\Site;
use App\Support\SsrfGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Imports the images a wizard build references into the site's media library
 * so pages get library assets (WebP variants, dimensions, dedupe) instead of
 * hotlinks. Unlike AssetService::importFromUrl this fetches from ARBITRARY
 * origins — the user pointed the wizard at that site — so every fetch is
 * SSRF-guarded and the bytes must decode as a real image. Returns null on any
 * failure so callers keep the original URL rather than dropping the block.
 */
class SiteWizardMediaImporter
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(private AssetService $assets)
    {
    }

    public function fromUrl(Site $site, string $url, string $alt = ''): ?Asset
    {
        try {
            SsrfGuard::assertPublicHttpUrl($url);

            $response = Http::timeout(20)
                ->connectTimeout(10)
                ->withUserAgent('Stillopress-SiteWizard/1.0')
                ->get($url);
            if (!$response->successful()) {
                return null;
            }
            $body = $response->body();
            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                return null;
            }

            $name = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME) ?: 'imported';

            return $this->fromBytes($site, $body, $name);
        } catch (\Throwable) {
            return null;
        }
    }

    public function fromFile(Site $site, string $absolutePath, string $alt = ''): ?Asset
    {
        try {
            if (!is_file($absolutePath) || filesize($absolutePath) > self::MAX_BYTES) {
                return null;
            }

            return $this->fromBytes($site, (string) file_get_contents($absolutePath), pathinfo($absolutePath, PATHINFO_FILENAME));
        } catch (\Throwable) {
            return null;
        }
    }

    private function fromBytes(Site $site, string $body, string $name): ?Asset
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sitewiz_img_');
        try {
            file_put_contents($tmp, $body);

            // Trust the bytes, not the extension/headers.
            $size = @getimagesize($tmp);
            if ($size && !empty($size['mime']) && str_starts_with($size['mime'], 'image/')) {
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
                $mime = $size['mime'];
            } elseif ($this->looksLikeSvg($body)) {
                // SVG is vector XML — getimagesize can't read it, so raster
                // detection above always failed and icon SVGs were dropped
                // (left as dead /wp-content/… hotlinks). AssetService::upload
                // sanitizes SVGs (scripts/handlers/external refs stripped)
                // before storing, so this is safe to accept.
                $ext = 'svg';
                $mime = 'image/svg+xml';
            } else {
                return null;
            }

            $filename = (Str::slug($name) ?: 'imported') . ".{$ext}";

            return $this->assets->upload($site, new UploadedFile($tmp, $filename, $mime, null, true));
        } catch (\Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    /** Heuristic: does the payload look like an SVG document? (getimagesize
     *  can't detect SVG, so raster sniffing misses it.) The sanitizer that
     *  runs at upload is the real gate; this just routes SVG bytes to it. */
    private function looksLikeSvg(string $body): bool
    {
        $head = substr($body, 0, 1024);
        // Skip a UTF-8 BOM and leading whitespace before checking the root.
        $head = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $head));

        return stripos($head, '<svg') !== false
            && ($head[0] === '<'); // XML/SVG opening, not HTML wrapping an <svg>
    }
}
