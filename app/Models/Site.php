<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * Scope route model binding to the authenticated user's tenant.
     * Returns 404 instead of 403 for cross-tenant access attempts,
     * preventing resource existence leakage.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $user = Auth::user();

        // Admin URLs address sites by slug (globally unique); API clients and
        // older links may still send the UUID — accept both.
        $field ??= Str::isUuid($value) ? $this->getRouteKeyName() : 'slug';

        $query = $this->resolveRouteBindingQuery($this, $value, $field);

        if ($user && $user->tenant_id) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query->first();
    }

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'custom_domain',
        'seo_defaults', 'status', 'settings', 'active_theme_id',
    ];

    protected function casts(): array
    {
        return [
            'seo_defaults' => 'array',
            'settings' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The public folder this site deploys to under the shared docroot
     * (ensodo.eu/{deploySlug}/). Defaults to the site slug; overridable via
     * settings.deploy_slug so the live URL can differ from the internal slug.
     * Malformed overrides fall back to the slug — the deploy path must never
     * contain separators or traversal.
     */
    public function deploySlug(): string
    {
        $custom = trim((string) ($this->settings['deploy_slug'] ?? ''));

        return $custom !== '' && preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $custom)
            ? $custom
            : $this->slug;
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function magazines(): HasMany
    {
        return $this->hasMany(Magazine::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(\App\Domain\IssueComposer\Models\MagazineIssue::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function grids(): HasMany
    {
        return $this->hasMany(Grid::class);
    }

    public function gridAssignments(): HasMany
    {
        return $this->hasMany(GridAssignment::class)->orderBy('priority');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'active_theme_id');
    }
}
