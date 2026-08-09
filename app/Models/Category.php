<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'site_id', 'parent_id', 'default_layout_id', 'name', 'slug',
        'description', 'settings', 'sort_order', 'is_public', 'grid_id',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'settings' => 'array',
    ];

    // Surface the settings-backed banner image on every serialization (the
    // category tree endpoint uses toArray(), not CategoryResource).
    protected $appends = ['featured_image', 'featured_image_asset_id'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function grid(): BelongsTo
    {
        return $this->belongsTo(Grid::class);
    }

    /**
     * Public URL path for this category archive (e.g. /news or, with a
     * per-site category base, /category/news). Locale prefix is applied at
     * build time, not here. Mirrors ArchiveBuildService so links and the
     * published archive path always agree.
     */
    public function getUrlPathAttribute(): string
    {
        $base = $this->site
            ? \App\Domain\Publishing\Services\LocalePaths::categoryBase($this->site)
            : '';

        return "/{$base}{$this->slug}";
    }

    /**
     * Category banner/featured image, stored in settings so it needs no schema
     * change and works for every site. Value is a serve URL that the publisher
     * rewrites to a static /assets/files path (and wraps in <picture> for WebP).
     */
    public function getFeaturedImageAttribute(): ?string
    {
        return $this->settings['featured_image'] ?? null;
    }

    public function getFeaturedImageAssetIdAttribute(): ?string
    {
        return $this->settings['featured_image_asset_id'] ?? null;
    }

    /**
     * Set (or clear, with null) the category's featured image from an asset id.
     * Keeps settings['featured_image'] (serve URL) and the asset id in sync.
     */
    public function setFeaturedImageAsset(?string $assetId): void
    {
        $settings = $this->settings ?? [];
        if ($assetId) {
            $settings['featured_image_asset_id'] = $assetId;
            $settings['featured_image'] = "/api/v1/sites/{$this->site_id}/assets/{$assetId}/serve";
        } else {
            unset($settings['featured_image_asset_id'], $settings['featured_image']);
        }
        $this->settings = $settings;
    }
}
