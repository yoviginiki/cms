<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetFolder extends Model
{
    use HasUuids;

    protected $fillable = ['site_id', 'path'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Normalise a user-supplied folder path: trims, collapses slashes, drops
     * empty / dot segments. Returns null for "root". Throws on illegal chars.
     */
    public static function normalizePath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        $segments = [];
        foreach (preg_split('#[\\\\/]+#', $path) as $seg) {
            $seg = trim($seg);
            if ($seg === '' || $seg === '.' || $seg === '..') {
                continue;
            }
            if (!preg_match('/^[\p{L}\p{N} _\-.()]+$/u', $seg)) {
                throw new \InvalidArgumentException("Folder name \"{$seg}\" contains unsupported characters.");
            }
            $segments[] = $seg;
        }
        if (!$segments) {
            return null;
        }
        $normalized = implode('/', $segments);
        if (mb_strlen($normalized) > 255) {
            throw new \InvalidArgumentException('Folder path is too long.');
        }

        return $normalized;
    }

    public static function parentOf(?string $path): ?string
    {
        if ($path === null || !str_contains($path, '/')) {
            return null;
        }

        return substr($path, 0, strrpos($path, '/'));
    }

    public static function nameOf(string $path): string
    {
        return str_contains($path, '/') ? substr($path, strrpos($path, '/') + 1) : $path;
    }
}
