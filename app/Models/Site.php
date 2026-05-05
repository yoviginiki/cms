<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

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
