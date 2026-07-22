<?php

namespace App\Services\SiteWizard;

use App\Support\SsrfGuard;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * One-page extraction for the Site Wizard (NO AI). Runs
 * scripts/import-site-page.mjs in a headless browser and returns the page
 * manifest PLUS the signals the whole-site build needs: nav anchors, the
 * same-origin link frontier, and computed-style theme signals. Works on live
 * URLs and on HTML files inside an extracted design ZIP (served over a
 * throwaway loopback server so real CSS/geometry applies).
 *
 * Needs proc_open → queue worker only (same constraint as PageDomImporter,
 * which this mirrors). Tests replace this class in the container.
 */
class SitePageExtractor
{
    public function __construct(private \App\Services\PageWizard\PageManifestValidator $validator)
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

    /** @return array{manifest:array, nav:array, links:array, style:array} */
    public function fromUrl(string $url): array
    {
        SsrfGuard::assertPublicHttpUrl($url);

        return $this->run([$url], $url);
    }

    /** @return array{manifest:array, nav:array, links:array, style:array} */
    public function fromLocalFile(string $rootDir, string $relativePath): array
    {
        return $this->run(['--dir', $rootDir, '--path', $relativePath], $relativePath);
    }

    private function run(array $args, string $label): array
    {
        if (!$this->available()) {
            throw new RuntimeException('Site import is not enabled on this server.');
        }

        $node = trim((string) (config('cms.theme_wizard.node_bin') ?? 'node'));
        $script = base_path('scripts/import-site-page.mjs');

        $proc = new Process([$node, $script, ...$args], base_path());
        $proc->setTimeout(90);
        $proc->run();

        if (!$proc->isSuccessful()) {
            $err = trim($proc->getErrorOutput()) ?: 'import failed';
            Log::warning('SiteWizard: page extraction failed', ['ref' => $label, 'err' => mb_substr($err, 0, 200)]);
            $friendly = str_starts_with($err, 'ERROR: ') ? substr($err, 7) : 'Could not read that page.';
            throw new RuntimeException($friendly);
        }

        $result = json_decode(trim($proc->getOutput()), true);
        if (!is_array($result) || !is_array($result['manifest'] ?? null)) {
            throw new RuntimeException('The import produced no usable content.');
        }

        return [
            'manifest' => $this->validator->sanitize($result['manifest']),
            'nav' => array_values(array_filter((array) ($result['nav'] ?? []), 'is_array')),
            'links' => array_values(array_filter((array) ($result['links'] ?? []), 'is_string')),
            'style' => is_array($result['style'] ?? null) ? $result['style'] : [],
        ];
    }
}
