<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'page_id', 'post_id', 'blocks_snapshot', 'seo_snapshot',
        'published_by', 'published_at', 'version_number',
    ];

    protected function casts(): array
    {
        return [
            'blocks_snapshot' => 'array',
            'seo_snapshot' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function publishedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
