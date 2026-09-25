<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'tenant_id', 'role', 'restricted_to_sites',
        'last_login_at', 'invitation_token', 'invitation_expires_at', 'invited_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'restricted_to_sites' => 'boolean',
        ];
    }

    /** Roles a user can hold, lowest first. */
    public const ROLE_HIERARCHY = ['viewer' => 0, 'author' => 1, 'editor' => 2, 'admin' => 3, 'owner' => 4];

    /**
     * Role on the site the current request addresses (set by
     * Site::resolveRouteBinding for restricted users). Never persisted.
     */
    protected ?string $siteRoleOverride = null;

    /** @var array<string,string>|null site_id => role, memoized per instance */
    protected ?array $siteRolesCache = null;

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Sites a restricted user was granted, with the per-site role on the pivot. */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_user')->withPivot('role')->withTimestamps();
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** The owner always has tenant-wide access, whatever the flag says. */
    public function isRestrictedToSites(): bool
    {
        return (bool) $this->restricted_to_sites && !$this->isOwner();
    }

    /**
     * site_id => role for a restricted user. Read straight from site_user
     * (no join to the RLS-protected `sites` table, so it works before the
     * tenant GUC is set).
     *
     * @return array<string,string>
     */
    public function siteRoles(): array
    {
        return $this->siteRolesCache ??= DB::table('site_user')
            ->where('user_id', $this->id)
            ->pluck('role', 'site_id')
            ->all();
    }

    public function canAccessSite(string $siteId): bool
    {
        return !$this->isRestrictedToSites() || array_key_exists($siteId, $this->siteRoles());
    }

    /** @return list<string>|null null = every site in the tenant */
    public function accessibleSiteIds(): ?array
    {
        return $this->isRestrictedToSites() ? array_keys($this->siteRoles()) : null;
    }

    /** Called when a request is bound to a site: restricted users act with their per-site role. */
    public function actOnSite(string $siteId): void
    {
        if ($this->isRestrictedToSites()) {
            $this->siteRoleOverride = $this->siteRoles()[$siteId] ?? null;
        }
    }

    /**
     * The role authorization checks use: the per-site role while a restricted
     * user works on one of their sites, otherwise `users.role` (which is
     * capped at editor for restricted users, so tenant-wide admin screens
     * stay closed to them).
     */
    public function effectiveRole(): string
    {
        return $this->siteRoleOverride ?? (string) $this->role;
    }

    public function forgetSiteRoles(): void
    {
        $this->siteRolesCache = null;
        $this->siteRoleOverride = null;
    }

    public function isAdmin(): bool
    {
        return in_array($this->effectiveRole(), ['owner', 'admin']);
    }

    public function isEditor(): bool
    {
        return in_array($this->effectiveRole(), ['owner', 'admin', 'editor']);
    }

    public function hasMinimumRole(string $role): bool
    {
        $userLevel = self::ROLE_HIERARCHY[$this->effectiveRole()] ?? 0;
        $requiredLevel = self::ROLE_HIERARCHY[$role] ?? 0;

        return $userLevel >= $requiredLevel;
    }
}
