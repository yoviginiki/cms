<?php

namespace App\Services\ThemeWizard;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Turns a reference (a URL to screenshot, or an uploaded image) into a
 * base64 PNG/JPEG for the vision analyzer.
 *
 * URL capture shells out to scripts/capture-url.mjs (Playwright/chromium).
 * Because that fetches an arbitrary URL server-side, this class is the SSRF
 * gate: http(s) only, no credentials, and the host must not resolve to a
 * private / reserved / loopback / link-local address (blocks cloud metadata
 * endpoints and internal services).
 */
class ReferenceCaptureService
{
    /** Whether server-side URL screenshots can run in this PHP context. */
    public function urlCaptureAvailable(): bool
    {
        if (!function_exists('proc_open')) return false;
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return !in_array('proc_open', $disabled, true);
    }

    /** @return array{data:string, media_type:string} base64 image + media type */
    /**
     * @param bool $fullPage capture the WHOLE page (Page Wizard layout mode),
     *                       not just the top viewport (Theme Wizard default).
     */
    public function fromUrl(string $url, bool $fullPage = false): array
    {
        // php-fpm often disables proc_open; the node screenshot then can't run
        // in the web request. Fail cleanly (→ 422) so the UI steers to upload.
        if (!$this->urlCaptureAvailable()) {
            throw new RuntimeException('Automatic site capture is not enabled on this server — upload a screenshot of the site instead.');
        }

        $url = $this->assertPublicHttpUrl($url);

        $node = trim((string) (config('cms.theme_wizard.node_bin') ?? 'node'));
        $script = base_path('scripts/capture-url.mjs');

        $args = [$node, $script, $url, '1280x900'];
        if ($fullPage) {
            $args[] = 'full';
        }
        $proc = new Process($args, base_path());
        $proc->setTimeout($fullPage ? 90 : 45); // full-page scroll + tall shot needs longer
        $proc->run();

        if (!$proc->isSuccessful()) {
            $err = trim($proc->getErrorOutput()) ?: 'capture failed';
            Log::warning('ThemeWizard: URL capture failed', ['url' => $url, 'err' => mb_substr($err, 0, 200)]);
            throw new RuntimeException('Could not capture that site — check the URL is reachable and public.');
        }

        $b64 = trim($proc->getOutput());
        if ($b64 === '' || base64_decode($b64, true) === false) {
            throw new RuntimeException('The capture produced no image — try a screenshot upload instead.');
        }

        return ['data' => $b64, 'media_type' => 'image/png'];
    }

    /** @return array{data:string, media_type:string} */
    public function fromUpload(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new RuntimeException('The upload was not received correctly.');
        }
        $mime = $file->getMimeType();
        $allowed = ['image/png' => 'image/png', 'image/jpeg' => 'image/jpeg', 'image/webp' => 'image/webp'];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Upload a PNG, JPEG, or WebP screenshot.');
        }
        if ($file->getSize() > 8 * 1024 * 1024) {
            throw new RuntimeException('Screenshot is too large (max 8 MB).');
        }
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || @getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('That file is not a readable image.');
        }
        return ['data' => base64_encode($bytes), 'media_type' => $allowed[$mime]];
    }

    /**
     * SSRF guard. Returns the normalized URL or throws.
     */
    public function assertPublicHttpUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Enter a full http(s) URL.');
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https URLs can be captured.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URLs with embedded credentials are not allowed.');
        }

        $host = $parts['host'];
        // resolve every A/AAAA record; ALL must be public
        $ips = $this->resolveHost($host);
        if ($ips === []) {
            throw new RuntimeException('That host could not be resolved.');
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp($ip)) {
                throw new RuntimeException('That address is not publicly reachable.');
            }
        }
        return $url;
    }

    /** @return array<int,string> */
    private function resolveHost(string $host): array
    {
        // a literal IP is its own resolution
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }
        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        foreach ($records as $r) {
            if (!empty($r['ip'])) $ips[] = $r['ip'];
            if (!empty($r['ipv6'])) $ips[] = $r['ipv6'];
        }
        if ($ips === []) {
            $v4 = @gethostbyname($host);
            if ($v4 && $v4 !== $host) $ips[] = $v4;
        }
        return array_values(array_unique($ips));
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
