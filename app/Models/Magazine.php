<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Magazine extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'site_id', 'title', 'slug', 'description', 'cover_image',
        'status', 'page_width', 'page_height', 'settings', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'published_at' => 'datetime',
            'page_width' => 'integer',
            'page_height' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(MagazinePage::class)->orderBy('sort_order');
    }

    public static function generateUniqueSlug(string $title, string $siteId, ?string $excludeId = null): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $count = 1;

        $query = static::where('site_id', $siteId)->where('slug', $slug);
        if ($excludeId) $query->where('id', '!=', $excludeId);

        while ($query->exists()) {
            $slug = $original . '-' . $count++;
            $query = static::where('site_id', $siteId)->where('slug', $slug);
            if ($excludeId) $query->where('id', '!=', $excludeId);
        }

        return $slug;
    }
}
