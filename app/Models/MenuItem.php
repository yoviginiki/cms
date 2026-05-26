<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'menu_id', 'parent_id', 'label', 'url',
        'page_id', 'post_id', 'category_id',
        'target', 'css_class', 'icon', 'sort_order',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Resolve the URL for this menu item.
     */
    public function resolveUrl(string $baseUrl = ''): string
    {
        if ($this->url) {
            // Block dangerous URI schemes (javascript:, data:, vbscript:)
            $stripped = preg_replace('/[\x00-\x1f\x7f\s]/', '', $this->url);
            if (preg_match('/^(javascript|data|vbscript)\s*:/i', $stripped)) {
                return '#';
            }
            return $this->url;
        }

        if ($this->page_id && $this->page) {
            $slug = $this->page->slug === 'home' ? '' : $this->page->slug;
            return $baseUrl . '/' . $slug;
        }

        if ($this->post_id && $this->post) {
            return $baseUrl . '/' . ($this->post->category ? $this->post->category->slug . '/' : '') . $this->post->slug;
        }

        if ($this->category_id && $this->category) {
            return $baseUrl . '/' . $this->category->slug;
        }

        return '#';
    }
}
