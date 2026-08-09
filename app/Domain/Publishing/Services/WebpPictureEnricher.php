<?php

namespace App\Domain\Publishing\Services;

use App\Models\Asset;

/**
 * Post-render WebP delivery: wraps bare <img> tags that point at a published
 * asset (/assets/files/{checksum}.{png|jpg}) in a <picture> with a WebP
 * <source>, so modern browsers download the lighter derivative while the
 * original stays as the universal fallback.
 *
 * Runs AFTER AssetPublisher::rewriteHtml (srcs are already static
 * /assets/files/{hash}.ext) and BEFORE rewriteBaseForSlugHosting (which then
 * rebases the WebP srcset under /{deploySlug}/ like every other asset URL).
 *
 * The per-block enrich* methods only cover asset-id / serve-URL blocks; VIOIV's
 * migrated images carry content-hash file URLs and slipped through, shipping
 * full-size PNG/JPG (the "Improve image delivery" PageSpeed hit). This pass is
 * block-agnostic: gallery, logostrip, logo, image — anything with such a src.
 *
 * It ALSO injects intrinsic width/height on those <img> tags when they carry
 * neither (e.g. logostrip logos sized only by inline CSS) so the browser can
 * reserve layout box → no CLS ("Image elements do not have explicit width and
 * height"). Display size stays controlled by the element's own CSS.
 */
class WebpPictureEnricher
{
    /** checksum → ['srcset'=>?string, 'w'=>?int, 'h'=>?int] */
    private static array $cache = [];

    public static function enrich(string $html): string
    {
        // Alternate on whole <picture> blocks so images already wrapped (slider
        // backgrounds, collection cards) are left untouched — only bare <img>
        // tags reach the enrichment branch.
        return preg_replace_callback(
            '#<picture\b[\s\S]*?</picture>|<img\b[^>]*>#i',
            function (array $m): string {
                $tag = $m[0];
                if (stripos($tag, '<picture') === 0) {
                    return $tag; // already a picture — leave it
                }
                return self::wrapImg($tag);
            },
            $html
        ) ?? $html;
    }

    private static function wrapImg(string $imgTag): string
    {
        if (!preg_match('#\ssrc="([^"]*/assets/files/([0-9a-f]{16,64})\.(?:png|jpe?g))"#i', $imgTag, $sm)) {
            return $imgTag;
        }
        $res = self::resolve(strtolower($sm[2]));

        // Inject intrinsic dimensions when the tag has NEITHER width nor height
        // (CLS). The element's inline width/height CSS still governs display; the
        // attributes only give the browser the aspect ratio to reserve space.
        if ($res['w'] && $res['h']
            && !preg_match('#\swidth\s*=#i', $imgTag)
            && !preg_match('#\sheight\s*=#i', $imgTag)) {
            $imgTag = preg_replace('#<img\b#i', '<img width="' . $res['w'] . '" height="' . $res['h'] . '"', $imgTag, 1);
        }

        if ($res['srcset'] === null) {
            return $imgTag;
        }

        // Carry the img's own `sizes` onto the source so the browser picks the
        // right width candidate (only meaningful when we have >1 candidate).
        $sizesAttr = '';
        if (preg_match('#\ssizes="([^"]*)"#i', $imgTag, $zm)) {
            $sizesAttr = ' sizes="' . $zm[1] . '"';
        }

        return '<picture><source type="image/webp" srcset="' . $res['srcset'] . '"' . $sizesAttr . '>' . $imgTag . '</picture>';
    }

    /** Resolve an asset checksum to its webp srcset (or null) + intrinsic dims. */
    private static function resolve(string $hash): array
    {
        if (array_key_exists($hash, self::$cache)) {
            return self::$cache[$hash];
        }

        $asset = Asset::where('checksum', $hash)->first();
        $variants = $asset ? (array) ($asset->variants ?? []) : [];
        $dims = $asset ? (array) ($asset->dimensions ?? []) : [];

        // resolveUrl falls back to the original when a variant file is missing —
        // never advertise a non-webp URL as image/webp.
        $webpUrl = function (string $variant) use ($asset): ?string {
            $url = AssetPublisher::resolveUrl($asset->id, null, $variant);
            return ($url && str_ends_with($url, '.webp')) ? $url : null;
        };

        $candidates = [];
        // Sized WebP derivatives = real responsive width candidates.
        foreach (['webp_400' => 400, 'webp_800' => 800] as $variant => $width) {
            if (!empty($variants[$variant]) && ($url = $webpUrl($variant))) {
                $candidates[] = "{$url} {$width}w";
            }
        }
        // Source-resolution WebP: add it as the largest candidate (needs the
        // asset's intrinsic width to sit in a valid multi-source srcset), or as
        // the sole descriptor-less candidate for small logos that have no sized
        // derivatives — so they still ship as WebP.
        if (!empty($variants['webp']) && ($url = $webpUrl('webp'))) {
            $w = (int) ($dims['width'] ?? 0);
            if ($candidates && $w > 0) {
                $candidates[] = "{$url} {$w}w";
            } elseif (!$candidates) {
                $candidates[] = $url;
            }
        }

        return self::$cache[$hash] = [
            'srcset' => $candidates ? implode(', ', $candidates) : null,
            'w' => isset($dims['width']) ? (int) $dims['width'] : null,
            'h' => isset($dims['height']) ? (int) $dims['height'] : null,
        ];
    }

    /** Reset the per-run resolve cache (mirrors AssetPublisher::reset). */
    public static function reset(): void
    {
        self::$cache = [];
    }
}
