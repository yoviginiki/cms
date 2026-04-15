<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'site_id', 'category_id', 'title', 'slug', 'excerpt',
        'featured_image', 'status', 'seo_meta', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'seo_meta' => 'array',
            'published_at' => 'datetime',
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

    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }
}
