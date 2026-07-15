<?php

namespace App\Services\PageWizard;

use App\Support\SsrfGuard;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Deterministic URL importer (NO AI). Runs scripts/import-url.mjs in a headless
 * browser to read the page's real DOM + geometry and returns a page manifest
 * built from the actual headings, paragraphs, images, and CTAs. Free, instant,
 * reproducible, and faithful (real content + real image URLs) — the sensible
 * default for "build a page from this URL". Needs proc_open, so it runs on the
 * queue worker (same constraint as the screenshot capture).
 */
class PageDomImporter
{
    public function __construct(private PageManifestValidator $validator)
    {
    }

    public function available(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('proc_open', $disabled, true);
    }

    /** @return array{page_title:string, design_read:string, blocks:array} */
    public function import(string $url): array
    {
        if (!$this->available()) {
            throw new RuntimeException('URL import is not enabled on this server — upload a screenshot or describe the page instead.');
        }

        SsrfGuard::assertPublicHttpUrl($url);

        $node = trim((string) (config('cms.theme_wizard.node_bin') ?? 'node'));
        $script = base_path('scripts/import-url.mjs');

        $proc = new Process([$node, $script, $url], base_path());
        $proc->setTimeout(90);
        $proc->run();

        if (!$proc->isSuccessful()) {
            $err = trim($proc->getErrorOutput()) ?: 'import failed';
            Log::warning('PageWizard: URL import failed', ['url' => $url, 'err' => mb_substr($err, 0, 200)]);
            $friendly = str_starts_with($err, 'ERROR: ') ? substr($err, 7) : 'Could not read that page — check the URL is reachable and public.';
            throw new RuntimeException($friendly);
        }

        $manifest = json_decode(trim($proc->getOutput()), true);
        if (!is_array($manifest)) {
            throw new RuntimeException('The import produced no usable content — try a different URL.');
        }

        $manifest = $this->validator->sanitize($manifest);
        if ($manifest['blocks'] === []) {
            throw new RuntimeException('Nothing importable was found on that page.');
        }

        return $manifest;
    }
}
