<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Concerns\PurgesBlocksOnForceDelete;

class Post extends Model
{
    use HasFactory, HasUuids, SoftDeletes, PurgesBlocksOnForceDelete;

    protected $fillable = [
        'site_id', 'category_id', 'author_id', 'title', 'slug', 'excerpt', 'layout_id',
        'featured_image', 'video_url', 'thumbnail', 'post_format',
        'status', 'editor_mode', 'experience_mode', 'seo_meta', 'grid_id', 'published_at', 'scheduled_at',
        'needs_republish', 'needs_republish_reason', 'content_modified_at',
    ];

    protected $attributes = [
        'experience_mode' => 'standard',
    ];

    protected function casts(): array
    {
        return [
            'seo_meta' => 'array',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'content_modified_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function grid(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Grid::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }

    /**
     * Get the public URL path for this post (e.g. /category-slug/post-slug).
     */
    public function getUrlPathAttribute(): string
    {
        if ($this->category && $this->category->slug) {
            return "/{$this->category->slug}/{$this->slug}";
        }
        return "/{$this->slug}";
    }
}
