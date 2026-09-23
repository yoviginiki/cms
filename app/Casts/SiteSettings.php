<?php

namespace App\Casts;

use App\Domain\Sites\Support\SiteSecrets;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * sites.settings with secrets encrypted at rest (F08, audit 2026-09-22).
 *
 * Behaves like the old 'array' cast for every caller; values under secret
 * keys (SiteSecrets::isSecretKey, any depth) are stored as
 * "enc:v1:<Crypt payload>" and decrypted transparently on read. Plaintext
 * values written before this cast still read fine and are encrypted on the
 * next save. A value that cannot be decrypted (APP_KEY changed) reads as null
 * and is logged — the site keeps working, the credential must be re-entered.
 */
class SiteSettings implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }
        $data = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($data) ? SiteSecrets::decryptAll($data) : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = is_array($value) ? $value : (json_decode((string) $value, true) ?? []);

        return json_encode(SiteSecrets::encryptAll($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
