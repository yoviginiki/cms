<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Concerns\PurgesBlocksOnForceDelete;

class Page extends Model
{
    use HasFactory, HasUuids, SoftDeletes, PurgesBlocksOnForceDelete;

    protected $fillable = [
        'site_id', 'parent_id', 'title', 'slug', 'layout_id',
        'status', 'editor_mode', 'experience_mode', 'seo_meta', 'sort_order', 'grid_id', 'published_at', 'scheduled_at',
        'raw_html', 'needs_republish', 'needs_republish_reason',
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
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id');
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
