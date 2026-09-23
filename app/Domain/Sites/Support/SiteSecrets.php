<?php

namespace App\Domain\Sites\Support;

/**
 * Secret handling for the site settings JSON (F08, audit 2026-09-22).
 *
 * Settings carry credentials (AI API keys, analytics tokens, GA service
 * account private key, SSH key path). They are never returned by the API:
 * every read masks them, the SPA round-trips the mask and the write side
 * restores the stored value, and exports/backups strip them recursively.
 * Detection is by key name so nested integrations are covered without a
 * per-feature list.
 */
final class SiteSecrets
{
    public const MASK = '••••••••';

    /** Exact key names (case-insensitive) that hold secrets at any depth. */
    private const SECRET_KEYS = [
        'anthropic_api_key', 'openai_api_key', 'deploy_ssh_key',
        'api_key', 'api_token', 'private_key', 'secret', 'client_secret',
        'password', 'access_token', 'refresh_token', 'token', 'credentials_json',
    ];

    public static function isSecretKey(string|int $key): bool
    {
        return is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true);
    }

    /** Replace every configured secret with the mask (read side). */
    public static function mask(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = self::mask($value);
            } elseif (self::isSecretKey($key) && self::isConfigured($value)) {
                $settings[$key] = self::MASK;
            }
        }

        return $settings;
    }

    /** Remove every secret key (exports, backups, logs). */
    public static function strip(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (self::isSecretKey($key)) {
                unset($settings[$key]);
            } elseif (is_array($value)) {
                $settings[$key] = self::strip($value);
            }
        }

        return $settings;
    }

    /**
     * Write side: wherever the incoming payload carries the mask for a
     * secret key, keep the stored value; anything else (new value, null,
     * empty string) is the caller's explicit intent.
     */
    public static function mergeIncoming(array $existing, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (is_array($value)) {
                $incoming[$key] = self::mergeIncoming(
                    is_array($existing[$key] ?? null) ? $existing[$key] : [],
                    $value,
                );
            } elseif (self::isSecretKey($key) && $value === self::MASK) {
                if (array_key_exists($key, $existing)) {
                    $incoming[$key] = $existing[$key];
                } else {
                    unset($incoming[$key]);
                }
            }
        }

        return $incoming;
    }

    public const ENC_PREFIX = 'enc:v1:';

    /** Encrypt every configured secret value (idempotent: already-encrypted values stay). */
    public static function encryptAll(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = self::encryptAll($value);
            } elseif (self::isSecretKey($key) && self::isConfigured($value) && $value !== self::MASK
                && !str_starts_with((string) $value, self::ENC_PREFIX)) {
                $settings[$key] = self::ENC_PREFIX . \Illuminate\Support\Facades\Crypt::encryptString((string) $value);
            }
        }

        return $settings;
    }

    /** Decrypt every encrypted secret value; plaintext (legacy) values pass through. */
    public static function decryptAll(array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = self::decryptAll($value);
            } elseif (is_string($value) && str_starts_with($value, self::ENC_PREFIX)) {
                try {
                    $settings[$key] = \Illuminate\Support\Facades\Crypt::decryptString(substr($value, strlen(self::ENC_PREFIX)));
                } catch (\Throwable $e) {
                    logger()->warning("Site setting '{$key}' could not be decrypted (APP_KEY changed?) — treated as unset.");
                    $settings[$key] = null;
                }
            }
        }

        return $settings;
    }

    private static function isConfigured(mixed $value): bool
    {
        return is_scalar($value) && (string) $value !== '';
    }
}
