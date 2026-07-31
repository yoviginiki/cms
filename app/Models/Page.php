<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use App\Domain\Concerns\PurgesBlocksOnForceDelete;

class Page extends Model
{
    use HasFactory, HasUuids, SoftDeletes, PurgesBlocksOnForceDelete;

    protected $fillable = [
        'site_id', 'parent_id', 'title', 'slug', 'layout_id',
        'status', 'editor_mode', 'experience_mode', 'seo_meta', 'sort_order', 'grid_id', 'published_at', 'scheduled_at',
        'raw_html', 'needs_republish', 'needs_republish_reason', 'content_modified_at', 'author_id',
    ];

    protected static function booted(): void
    {
        // Stamp the creating user as author so authors can inline-edit their own
        // pages (see PagePolicy::inlineEdit). Guarded on the column existing, so
        // it stays inert until the add-author_id-to-pages migration has run.
        static::creating(function (Page $page): void {
            if ($page->author_id === null && Auth::check() && Schema::hasColumn('pages', 'author_id')) {
                $page->author_id = Auth::id();
            }
        });
    }

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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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
