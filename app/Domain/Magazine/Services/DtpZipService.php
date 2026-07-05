<?php

namespace App\Domain\Magazine\Services;

use App\Domain\IssueComposer\Models\MagazineIssue;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

/**
 * Standalone magazine export (Viewer 2.0): a ZIP containing index.html
 * (the self-contained stillo-viewer runtime with the issue baked in) and an
 * assets/ folder with every CMS-hosted media file — extract anywhere and it
 * reads with NO CMS involvement. Remote embeds (YouTube/Vimeo, webfonts,
 * external banner/ad images) stay remote by design.
 */
class DtpZipService
{
    public function __construct(private DtpRenderService $renderService)
    {
    }

    /** returns path of the generated zip (caller moves/deletes) */
    public function export(MagazineIssue $issue): string
    {
        $data = $this->renderService->render($issue);
        if (empty($data['spreads'])) {
            throw new RuntimeException('No DTP content to export.');
        }

        $adminUrl = rtrim(config('app.url', 'https://sys.ensodo.eu'), '/');
        $spreads = json_decode(json_encode($data['spreads']), true);
        array_walk_recursive($spreads, function (&$value) use ($adminUrl) {
            if (is_string($value) && preg_match('#/api/v1/sites/([^/]+)/assets/([^/]+)/serve#', $value, $m)) {
                $value = str_replace($m[0], "{$adminUrl}/media/{$m[1]}/{$m[2]}", $value);
            }
        });

        $html = view('stillo-viewer', [
            'issue' => $data['issue'],
            'spreads' => $spreads,
            'pageCount' => $data['pageCount'],
            'viewerSettings' => $issue->layout_final['viewerSettings'] ?? [],
            'coverMode' => $issue->layout_final['issueSettings']['coverMode'] ?? 'standalone',
            'fontsUrl' => $data['fontsUrl'] ?? null,
        ])->render();

        // Localize CMS-hosted media into assets/ (schema-agnostic: self-fetch)
        $mediaUrls = [];
        preg_match_all('#(?:' . preg_quote($adminUrl, '#') . ')?/media/[0-9a-f\-]{36}/[0-9a-f\-]{36}[^"\'\s)]*#', $html, $mm);
        foreach (array_unique($mm[0]) as $u) {
            $mediaUrls[] = str_starts_with($u, '/') ? $adminUrl . $u : $u;
        }

        $base = storage_path('app/dtp-pdf');
        if (!is_dir($base)) {
            mkdir($base, 0775, true);
        }
        $zipPath = "{$base}/zip-" . bin2hex(random_bytes(8)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create zip.');
        }

        $n = 0;
        foreach ($mediaUrls as $url) {
            try {
                $resp = Http::withoutVerifying()->timeout(20)->get($url);
                if (!$resp->successful()) {
                    continue;
                }
                $ext = match (true) {
                    str_contains($resp->header('Content-Type'), 'webp') => 'webp',
                    str_contains($resp->header('Content-Type'), 'png') => 'png',
                    str_contains($resp->header('Content-Type'), 'gif') => 'gif',
                    str_contains($resp->header('Content-Type'), 'svg') => 'svg',
                    str_contains($resp->header('Content-Type'), 'mp3') || str_contains($resp->header('Content-Type'), 'mpeg') => 'mp3',
                    str_contains($resp->header('Content-Type'), 'mp4') => 'mp4',
                    default => 'jpg',
                };
                $n++;
                $local = "assets/media-{$n}.{$ext}";
                $zip->addFromString($local, $resp->body());
                // replace BOTH absolute and root-relative occurrences
                $rel = substr($url, strlen($adminUrl));
                $html = str_replace([$url, $rel], $local, $html);
            } catch (\Throwable) {
                // leave the remote URL in place — still works online
            }
        }

        $zip->addFromString('index.html', $html);
        $zip->addFromString('README.txt',
            "STILLOPRESS MAGAZINE — standalone export\n\n"
            . "Upload this folder anywhere (any static host or website directory)\n"
            . "and open index.html. Everything needed is inside: the reader,\n"
            . "your pages and your media ({$n} files in assets/). No CMS required.\n\n"
            . "Remote pieces that stay online by design: webfonts (Google Fonts),\n"
            . "YouTube/Vimeo embeds, and externally-hosted banner images.\n");
        $zip->close();

        if (!is_file($zipPath) || filesize($zipPath) < 500) {
            throw new RuntimeException('Zip export failed.');
        }

        return $zipPath;
    }
}
