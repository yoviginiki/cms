<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Concerns\PurgesBlocksOnForceDelete;

class ThemeTemplate extends Model
{
    use HasUuids, SoftDeletes, PurgesBlocksOnForceDelete;

    protected $fillable = [
        'site_id', 'name', 'slug', 'type',
        'category_id', 'post_format', 'collection_id', 'priority',
        'is_default', 'settings', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_default' => 'boolean',
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

    /**
     * Resolve the best template for a given post.
     * Priority: post_format+category > category > post_format > default
     */
    public static function resolveForPost(Post $post): ?self
    {
        $siteId = $post->site_id;

        // 1. Exact match: category + post_format
        if ($post->category_id && $post->post_format && $post->post_format !== 'standard') {
            $match = static::where('site_id', $siteId)
                ->where('type', 'post')
                ->where('category_id', $post->category_id)
                ->where('post_format', $post->post_format)
                ->orderByDesc('priority')->first();
            if ($match) return $match;
        }

        // 2. Category-specific
        if ($post->category_id) {
            $match = static::where('site_id', $siteId)
                ->where('type', 'post')
                ->where('category_id', $post->category_id)
                ->whereNull('post_format')
                ->orderByDesc('priority')->first();
            if ($match) return $match;
        }

        // 3. Post format-specific
        if ($post->post_format && $post->post_format !== 'standard') {
            $match = static::where('site_id', $siteId)
                ->where('type', 'post')
                ->whereNull('category_id')
                ->where('post_format', $post->post_format)
                ->orderByDesc('priority')->first();
            if ($match) return $match;
        }

        // 4. Default post template
        return static::where('site_id', $siteId)
            ->where('type', 'post')
            ->where('is_default', true)
            ->orderByDesc('priority')->first();
    }

    /**
     * Resolve the best archive template for a category.
     */
    public static function resolveForArchive(string $siteId, ?string $categoryId = null): ?self
    {
        // 1. Category-specific archive
        if ($categoryId) {
            $match = static::where('site_id', $siteId)
                ->where('type', 'archive')
                ->where('category_id', $categoryId)
                ->first();
            if ($match) return $match;
        }

        // 2. Default archive template
        return static::where('site_id', $siteId)
            ->where('type', 'archive')
            ->where('is_default', true)
            ->first();
    }

    /**
     * Track G2: the record-single template for a record's collection.
     * Collection-scoped only — no cross-collection default (schemas differ
     * too much for one template to render any record sensibly).
     */
    public static function resolveForRecord(Record $record): ?self
    {
        return static::where('site_id', $record->site_id)
            ->where('type', 'record-single')
            ->where('collection_id', $record->collection_id)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->first();
    }

    /** Track G2: the record-archive template for a collection. */
    public static function resolveForRecordArchive(string $siteId, string $collectionId): ?self
    {
        return static::where('site_id', $siteId)
            ->where('type', 'record-archive')
            ->where('collection_id', $collectionId)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->first();
    }

    /**
     * Resolve global header/footer template.
     */
    public static function resolveGlobal(string $siteId, string $type): ?self
    {
        return static::where('site_id', $siteId)
            ->where('type', $type)
            ->where('is_default', true)
            ->first();
    }
}
