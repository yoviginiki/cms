<?php

namespace App\Domain\Publishing\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Self-hosts Google Fonts at publish time so pages don't pay a render-blocking
 * cross-origin @import to fonts.googleapis.com (+ fonts.gstatic.com). We fetch
 * Google's own css2 stylesheet, download every referenced woff2, copy them into
 * the current build under /assets/fonts/, and return the SAME CSS with the
 * gstatic URLs rewritten to same-origin paths. Because we only swap URLs inside
 * Google's stylesheet, every @font-face block — with its unicode-range subsets
 * (latin, latin-ext, CYRILLIC, …) — is preserved verbatim, so multilingual
 * (e.g. Bulgarian) text keeps rendering.
 *
 * The emitted url('/assets/fonts/x.woff2') is root-absolute; BuildPageService::
 * rewriteBaseForSlugHosting() rebases it to /{deploySlug}/assets/fonts/x.woff2
 * for subfolder-hosted sites. CSP already allows font-src 'self'.
 */
class GoogleFontPublisher
{
    /** Memoize the resolved CSS per (families+weights, deployTarget) within a request. */
    private static array $memo = [];

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

    /**
     * Localized @font-face CSS for the given families, or null when self-hosting
     * isn't possible (no active build target, or the network fetch failed) — the
     * caller then falls back to the render-blocking @import so text still loads.
     *
     * @param string[] $families primary family names, e.g. ['Inter', 'Plus Jakarta Sans']
     */
    public function localCss(array $families, string $weights = '300;400;500;600;700'): ?string
    {
        $families = array_values(array_unique(array_filter(array_map('trim', $families))));
        if ($families === []) {
            return null;
        }

        // Only self-host during an actual build (deploy target set). In the admin
        // preview there's nowhere to write the files → keep the @import.
        $deployTarget = AssetPublisher::deployTarget();
        if (!$deployTarget) {
            return null;
        }

        $famParam = implode('&family=', array_map(
            fn ($f) => str_replace(' ', '+', $f) . ':wght@' . $weights,
            $families
        ));
        $cssUrl = "https://fonts.googleapis.com/css2?family={$famParam}&display=swap";
        $memoKey = $cssUrl . '|' . $deployTarget;
        if (array_key_exists($memoKey, self::$memo)) {
            return self::$memo[$memoKey];
        }

        try {
            // Google's css2 output is immutable per URL — cache the source text
            // across publishes so we only hit the network once per font set.
            $source = Cache::remember('gfont-css:' . sha1($cssUrl), now()->addDays(30), function () use ($cssUrl) {
                $r = Http::timeout(8)->withHeaders(['User-Agent' => self::UA])->get($cssUrl);
                return $r->successful() ? $r->body() : null;
            });
            if (!$source) {
                return self::$memo[$memoKey] = null;
            }

            $fontsDir = rtrim($deployTarget, '/') . '/assets/fonts';
            if (!is_dir($fontsDir)) {
                @mkdir($fontsDir, 0775, true);
            }

            $ok = true;
            $localCss = preg_replace_callback(
                '#https://fonts\.gstatic\.com/[^)\'"\s]+\.woff2#',
                function ($m) use ($fontsDir, &$ok) {
                    $remote = $m[0];
                    $name = sha1($remote) . '.woff2';
                    $diskKey = 'google-fonts/' . $name;

                    // Persist the bytes on the assets disk so they survive across
                    // atomic builds; only the copy-into-build step runs per publish.
                    if (!Storage::disk('assets')->exists($diskKey)) {
                        $bytes = Http::timeout(8)->withHeaders(['User-Agent' => self::UA])->get($remote);
                        if (!$bytes->successful()) {
                            $ok = false;
                            return $remote; // leave the gstatic URL as a safety net
                        }
                        Storage::disk('assets')->put($diskKey, $bytes->body());
                    }

                    $dest = $fontsDir . '/' . $name;
                    if (!file_exists($dest)) {
                        $data = Storage::disk('assets')->get($diskKey);
                        if ($data === null) {
                            $ok = false;
                            return $remote;
                        }
                        file_put_contents($dest, $data);
                        @chmod($dest, 0664);
                    }

                    return '/assets/fonts/' . $name;
                },
                $source
            );

            // If any woff2 couldn't be localized, don't half-self-host — fall back
            // entirely to the @import to avoid a broken mixed state.
            if (!$ok) {
                return self::$memo[$memoKey] = null;
            }

            return self::$memo[$memoKey] = $localCss;
        } catch (\Throwable $e) {
            return self::$memo[$memoKey] = null;
        }
    }
}
