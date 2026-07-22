<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Publishing\Services\SiteFilesPublisher;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a site's verbatim design files (exact-copy imports) on the admin
 * origin so previews render before the first static publish. The published
 * site never hits this — SiteFilesPublisher rewrites these URLs to the static
 * /site-files/ copy at build time. Tenancy comes from the Site binding (RLS).
 */
class SiteFilesController extends Controller
{
    private const MIME = [
        'css' => 'text/css', 'js' => 'application/javascript', 'mjs' => 'application/javascript',
        'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'txt' => 'text/plain', 'xml' => 'application/xml',
    ];

    public function serve(Site $site, string $path): BinaryFileResponse
    {
        $root = realpath(SiteFilesPublisher::storageRoot($site));
        abort_if($root === false, 404);

        $file = realpath($root . '/' . rawurldecode($path));
        // realpath collapses any ../ — anything that escapes the root 404s.
        if ($file === false || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';

        return response()->file($file, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
