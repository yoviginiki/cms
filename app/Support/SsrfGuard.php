<?php

namespace App\Support;

use RuntimeException;

/**
 * SSRF gate for user-supplied URLs the server will fetch (Page Wizard content
 * mode). http(s) only, no embedded credentials, and every resolved A/AAAA
 * record must be a public IP — blocks loopback, private ranges, link-local,
 * and cloud metadata endpoints. Mirrors the check baked into the theme
 * wizard's ReferenceCaptureService.
 */
class SsrfGuard
{
    public static function assertPublicHttpUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new RuntimeException('That doesn\'t look like a valid URL.');
        }
        $scheme = mb_strtolower($parts['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('Only http and https URLs are allowed.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URLs with embedded credentials aren\'t allowed.');
        }

        $host = $parts['host'];
        $ips = [];
        foreach (['A', 'AAAA'] as $type) {
            $records = @dns_get_record($host, $type === 'A' ? DNS_A : DNS_AAAA) ?: [];
            foreach ($records as $r) {
                $ips[] = $r['ip'] ?? ($r['ipv6'] ?? null);
            }
        }
        // Literal IP host (no DNS record) — validate it directly.
        if ($ips === [] && filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        }
        $ips = array_filter($ips);
        if ($ips === []) {
            throw new RuntimeException('Could not resolve that host.');
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('That host resolves to a private or reserved address.');
            }
        }
    }
}
