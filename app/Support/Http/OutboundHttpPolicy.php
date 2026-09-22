<?php

namespace App\Support\Http;

/**
 * Outbound HTTP policy for server-side requests to user-supplied URLs
 * (webhooks, collection importers) — F25, audit 2026-09-22.
 *
 * The old guard checked the A records of the initial host and then let the
 * HTTP client follow redirects and resolve the host again on connect, so a
 * public→private redirect or a DNS change between check and connect reached
 * internal services. This policy:
 *  - accepts https only, no userinfo, no literal IP / localhost hosts;
 *  - resolves A AND AAAA and refuses the host if ANY record is private,
 *    loopback, link-local, reserved or an IPv4-mapped IPv6 of those;
 *  - pins the connection to the checked address (CURLOPT_RESOLVE) and
 *    never follows redirects.
 */
final class OutboundHttpPolicy
{
    /** @var \Closure(string): array<int,string> */
    private \Closure $resolver;

    /** @param null|\Closure(string): array<int,string> $resolver host → IP list (tests inject one) */
    public function __construct(?\Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = [];
            foreach ((array) @dns_get_record($host, DNS_A) as $r) {
                if (!empty($r['ip'])) {
                    $ips[] = $r['ip'];
                }
            }
            foreach ((array) @dns_get_record($host, DNS_AAAA) as $r) {
                if (!empty($r['ipv6'])) {
                    $ips[] = $r['ipv6'];
                }
            }

            return $ips;
        };
    }

    /**
     * Validate a URL and resolve it to one checked public address.
     *
     * @return array{url:string,host:string,port:int,ip:string}|null null when the destination is not allowed
     */
    public function resolvePublic(string $url): ?array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return null;
        }
        // Literal addresses are never allowed — every destination goes through DNS + the range check.
        if (filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return null;
        }

        $ips = array_values(array_unique(array_filter(($this->resolver)($host), 'is_string')));
        if ($ips === []) {
            return null;
        }
        foreach ($ips as $ip) {
            if (!self::isPublicAddress($ip)) {
                return null; // one private record poisons the host (rebinding / split-horizon)
            }
        }

        return [
            'url' => $url,
            'host' => $host,
            'port' => (int) ($parts['port'] ?? 443),
            'ip' => $ips[0],
        ];
    }

    /** Guzzle options that pin the checked address and forbid redirects. */
    public function guzzleOptions(?array $target): array
    {
        $options = ['allow_redirects' => false];
        if ($target && !empty($target['ip'])) {
            $ip = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
            $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"]];
        }

        return $options;
    }

    public static function isPublicAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        // IPv4-mapped / compatible IPv6 (::ffff:10.0.0.1) — check the embedded v4 too.
        if (str_contains($ip, ':') && preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/', $ip, $m)) {
            return self::isPublicAddress($m[1]);
        }
        // Further reserved v6 space not covered by the filter flags.
        if (str_contains($ip, ':')) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return false;
            }
            $hex = bin2hex($packed);
            if (str_starts_with($hex, '0000000000000000000000000000') // ::/96 incl. ::ffff:… handled above
                || str_starts_with($hex, '2002')      // 6to4 (embeds v4)
                || str_starts_with($hex, '20010000')  // Teredo
                || str_starts_with($hex, '0064ff9b')) { // NAT64 well-known prefix
                return false;
            }
        }

        return true;
    }
}
