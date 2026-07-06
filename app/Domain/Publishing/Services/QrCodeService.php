<?php

namespace App\Domain\Publishing\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Inline SVG QR codes for published output (e.g. video frames in print:
 * the reader scans the code to watch the video the paper can't play).
 */
class QrCodeService
{
    /** Scalable inline SVG (no XML prolog, 100% width/height). */
    public static function svg(string $text): string
    {
        $renderer = new ImageRenderer(new RendererStyle(240, 0), new SvgImageBackEnd());
        $svg = (new Writer($renderer))->writeString($text);

        $svg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);

        return preg_replace(
            '/<svg([^>]*?)width="[^"]*" height="[^"]*"/',
            '<svg$1width="100%" height="100%"',
            $svg,
            1
        );
    }
}
