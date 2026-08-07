<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Bearer token for external module services. Only the sha256 hash is stored;
 * the plaintext is returned exactly once from `issue()`. Not tenant-RLS'd —
 * an auth-credential table (see docs decision RLS-TOKENS).
 */
class ModuleToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'module_id',
        'tenant_id',
        'name',
        'token_hash',
        'abilities',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    /** Hash a plaintext token for storage / lookup (sha256 hex, 64 chars). */
    public static function hashToken(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    /**
     * Create a token, returning [ModuleToken $model, string $plaintext].
     * The plaintext is shown to the operator exactly once and never stored.
     */
    public static function issue(array $attributes): array
    {
        $plaintext = 'mod_' . Str::random(48);

        $model = static::create($attributes + [
            'token_hash' => static::hashToken($plaintext),
        ]);

        return [$model, $plaintext];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
