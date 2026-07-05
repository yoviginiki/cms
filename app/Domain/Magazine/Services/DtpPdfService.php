<?php

namespace App\Domain\Magazine\Services;

use App\Domain\IssueComposer\Models\MagazineIssue;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * PDF export for DTP magazine issues (checklist M-G, user-prioritized track).
 *
 * Renders the SAME DtpRenderService output through a print-oriented Blade
 * (dtp-print: pages only, @page sized to the document, no viewer chrome) and
 * prints it with headless Chromium. The flow engine's placements are baked
 * into the slices, so the PDF is pixel-consistent with editor + web viewer.
 *
 * Known v1 limitation: spread-spanning frames render their owning page's
 * half only (PDF pages clip at page bounds); duplicating spanning frames
 * onto the facing page is a later polish item.
 */
class DtpPdfService
{
    public function __construct(private DtpRenderService $renderService)
    {
    }

    /** returns the path of a generated PDF temp file (caller deletes) */
    public function export(MagazineIssue $issue, bool $withMarks = false): string
    {
        $data = $this->renderService->render($issue);
        if (empty($data['spreads'])) {
            throw new RuntimeException('No DTP content to export.');
        }

        // flatten pages in reading order
        $pages = [];
        foreach ($data['spreads'] as $spread) {
            foreach ($spread['pages'] as $p) {
                $pages[] = $p;
            }
        }
        usort($pages, fn ($a, $b) => $a['index'] <=> $b['index']);

        $pageW = (int) ($pages[0]['width'] ?? 595);
        $pageH = (int) ($pages[0]['height'] ?? 842);

        $html = view('dtp-print', [
            'issue' => $data['issue'],
            'pages' => $pages,
            'pageW' => $pageW,
            'pageH' => $pageH,
            'fontsUrl' => $data['fontsUrl'] ?? null,
            'withMarks' => $withMarks,
            'bleedSize' => 9,
        ])->render();

        $base = storage_path('app/dtp-pdf');
        if (!is_dir($base)) {
            mkdir($base, 0775, true);
        }
        $stamp = bin2hex(random_bytes(8));
        $htmlPath = "{$base}/issue-{$stamp}.html";
        $pdfPath = "{$base}/issue-{$stamp}.pdf";
        file_put_contents($htmlPath, $html);

        // Chrome needs a writable HOME/XDG + profile dir when run from the
        // queue worker (crashpad aborts otherwise — verified on this host)
        $chromeHome = "{$base}/chrome-home";
        foreach (["{$chromeHome}", "{$chromeHome}/prof", "{$chromeHome}/dumps"] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }

        try {
            $result = Process::timeout(90)
                ->env([
                    'HOME' => $chromeHome,
                    'PATH' => '/usr/local/bin:/usr/bin:/bin',
                    'XDG_CONFIG_HOME' => $chromeHome,
                    'XDG_CACHE_HOME' => $chromeHome,
                ])
                ->run($this->buildCommand($htmlPath, $pdfPath));
            if (!$result->successful() || !is_file($pdfPath) || filesize($pdfPath) < 1000) {
                throw new RuntimeException('PDF render failed: ' . mb_substr($result->errorOutput(), 0, 500));
            }
        } finally {
            @unlink($htmlPath);
        }

        return $pdfPath;
    }

    /** @return list<string> chrome invocation (exposed for unit tests) */
    public function buildCommand(string $htmlPath, string $pdfPath): array
    {
        $chrome = config('services.chrome.binary', '/usr/bin/google-chrome');

        return [
            $chrome,
            '--headless=new',
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--hide-scrollbars',
            '--no-first-run',
            '--user-data-dir=' . storage_path('app/dtp-pdf/chrome-home/prof'),
            '--crash-dumps-dir=' . storage_path('app/dtp-pdf/chrome-home/dumps'),
            '--force-color-profile=srgb',
            '--virtual-time-budget=15000',
            '--no-pdf-header-footer',
            '--print-to-pdf=' . $pdfPath,
            'file://' . $htmlPath,
        ];
    }
}
