<?php

namespace App\Domain\Magazine\Models;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MagElement extends Model
{
    use HasUuids;

    protected $table = 'mag_elements';

    protected $fillable = [
        'page_id', 'parent_id', 'type', 'name', 'data',
        'x', 'y', 'width', 'height', 'rotation', 'scale_x', 'scale_y',
        'z_index', 'locked', 'visible', 'layer_name',
        'style', 'typography', 'text_wrap',
        'thread_id', 'thread_order',
        'page_number', 'on_master',
        'responsive_overrides', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'style' => 'array',
            'typography' => 'array',
            'text_wrap' => 'array',
            'responsive_overrides' => 'array',
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'rotation' => 'float',
            'scale_x' => 'float',
            'scale_y' => 'float',
            'z_index' => 'integer',
            'locked' => 'boolean',
            'visible' => 'boolean',
            'on_master' => 'boolean',
            'thread_order' => 'integer',
            'page_number' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('z_index');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function threadedFrames(): HasMany
    {
        return $this->hasMany(self::class, 'thread_id', 'thread_id')
            ->where('id', '!=', $this->id)
            ->orderBy('thread_order');
    }

    // Scopes
    public function scopeOnPage($query, int $pageNumber)
    {
        return $query->where('page_number', $pageNumber);
    }

    public function scopeOnMaster($query)
    {
        return $query->where('on_master', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('visible', true);
    }

    public function scopeInThread($query, string $threadId)
    {
        return $query->where('thread_id', $threadId)->orderBy('thread_order');
    }

    // Helpers
    public function isTextFrame(): bool
    {
        return in_array($this->type, ['text_frame', 'headline_frame', 'pullquote_frame', 'caption_frame', 'footnote_frame', 'marginalia_frame']);
    }

    public function isImageFrame(): bool
    {
        return in_array($this->type, ['image_frame', 'circular_image', 'polygon_image', 'fullbleed_image', 'gallery_frame', 'background_image']);
    }

    public function isShape(): bool
    {
        return in_array($this->type, ['rectangle', 'ellipse', 'line', 'polygon', 'freeform_path', 'decorative_rule', 'gradient_overlay']);
    }

    public function isGroup(): bool
    {
        return in_array($this->type, ['group', 'component_instance', 'clipping_group']);
    }

    public function getThreadText(): string
    {
        if (!$this->thread_id) {
            return $this->data['content'] ?? '';
        }

        $frames = self::where('thread_id', $this->thread_id)
            ->orderBy('thread_order')
            ->pluck('data');

        return $frames->map(fn($d) => $d['content'] ?? '')->implode('');
    }
}
