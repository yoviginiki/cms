<?php

namespace App\Domain\Publishing\Services\Deploy;

/**
 * Validated SSH deploy target (H01, audit 2026-09-22).
 *
 * Every field that ends up on the rsync/ssh command line is checked here,
 * on write (site create/update) AND right before the deploy, because site
 * settings also arrive through create, clone, import and restore paths:
 *  - host: a DNS name or IP literal, never starting with '-';
 *  - user: a POSIX-style login name (no leading '-', no spaces/specials);
 *  - port: integer 1–65535;
 *  - path: absolute, normalized, no '..', not '/' and not a system directory
 *    (rsync --delete runs against it);
 *  - key: only a file inside the operator-managed keys directory
 *    (publishing.ssh_keys_path) — never an arbitrary server path such as
 *    the platform's own ~/.ssh identity.
 */
final class SshTarget
{
    private const FORBIDDEN_PATHS = ['/', '/bin', '/boot', '/dev', '/etc', '/home', '/lib', '/lib64', '/opt', '/proc',
        '/root', '/run', '/sbin', '/srv', '/sys', '/tmp', '/usr', '/var', '/var/www'];

    private function __construct(
        public readonly string $host,
        public readonly string $user,
        public readonly int $port,
        public readonly string $path,
        public readonly ?string $keyPath,
    ) {
    }

    /** @throws \InvalidArgumentException with a field-specific message */
    public static function fromSettings(array $settings): self
    {
        $errors = self::errors($settings);
        if ($errors !== []) {
            throw new \InvalidArgumentException('SSH deploy settings are invalid: ' . implode(' ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($errors), $errors)));
        }

        return new self(
            strtolower(trim((string) $settings['deploy_ssh_host'])),
            (string) $settings['deploy_ssh_user'],
            (int) ($settings['deploy_ssh_port'] ?? 22),
            self::normalizePath((string) $settings['deploy_ssh_path']),
            self::resolveKey($settings['deploy_ssh_key'] ?? null),
        );
    }

    /**
     * Field errors keyed by the settings key ([] = valid). Only meaningful
     * when deploy_method is ssh; callers decide when to apply it.
     *
     * @return array<string,string>
     */
    public static function errors(array $settings): array
    {
        $e = [];
        $host = trim((string) ($settings['deploy_ssh_host'] ?? ''));
        if ($host === '' || !self::validHost($host)) {
            $e['deploy_ssh_host'] = 'must be a host name or IP address.';
        }
        $user = (string) ($settings['deploy_ssh_user'] ?? '');
        if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/i', $user)) {
            $e['deploy_ssh_user'] = 'must be a plain login name (letters, digits, _ . -; not starting with -).';
        }
        $port = $settings['deploy_ssh_port'] ?? 22;
        if (!(is_int($port) || (is_string($port) && ctype_digit($port))) || (int) $port < 1 || (int) $port > 65535) {
            $e['deploy_ssh_port'] = 'must be an integer between 1 and 65535.';
        }
        $path = (string) ($settings['deploy_ssh_path'] ?? '');
        $norm = self::normalizePath($path);
        if ($norm === '' || in_array(rtrim($norm, '/') ?: '/', self::FORBIDDEN_PATHS, true)) {
            $e['deploy_ssh_path'] = 'must be an absolute, dedicated directory (not /, a system directory, or containing ..).';
        }
        $key = $settings['deploy_ssh_key'] ?? null;
        if ($key !== null && $key !== '' && self::resolveKey($key) === null) {
            $e['deploy_ssh_key'] = 'must name a key file inside the server\'s managed SSH keys directory.';
        }

        return $e;
    }

    /** argv for rsync — no shell involved. */
    public function rsyncCommand(string $source): array
    {
        $ssh = ['ssh', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'ConnectTimeout=10', '-o', 'BatchMode=yes', '-p', (string) $this->port];
        if ($this->keyPath) {
            $ssh[] = '-i';
            $ssh[] = $this->keyPath;
        }

        return [
            'rsync', '-az', '--delete', '--chmod=D2775,F664',
            // rsync splits -e on whitespace; every component is validated to contain none.
            '-e', implode(' ', $ssh),
            '--',
            rtrim($source, '/') . '/',
            "{$this->user}@{$this->host}:{$this->path}/",
        ];
    }

    private static function validHost(string $host): bool
    {
        if (str_starts_with($host, '-')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return (bool) preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/i', $host);
    }

    /** '/var/www/site/' → '/var/www/site'; '' when not absolute, has '..'/'.', or odd characters. */
    private static function normalizePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/' || !preg_match('#^[A-Za-z0-9._/-]+$#', $path)) {
            return '';
        }
        $parts = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '') {
                continue;
            }
            if ($seg === '.' || $seg === '..' || str_starts_with($seg, '-')) {
                return '';
            }
            $parts[] = $seg;
        }

        return '/' . implode('/', $parts);
    }

    /** Key file inside publishing.ssh_keys_path, or null. Accepts a bare file name or a path inside that dir. */
    private static function resolveKey(mixed $key): ?string
    {
        if (!is_string($key) || $key === '') {
            return null;
        }
        $dir = realpath((string) config('publishing.ssh_keys_path', storage_path('app/ssh-keys')));
        if ($dir === false) {
            return null;
        }
        $candidate = str_starts_with($key, '/') ? $key : $dir . '/' . $key;
        $real = realpath($candidate);
        if ($real === false || !is_file($real) || !str_starts_with($real, $dir . '/')) {
            return null;
        }
        if (!preg_match('#^[A-Za-z0-9._/-]+$#', $real)) {
            return null;
        }

        return $real;
    }
}
