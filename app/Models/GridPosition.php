<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GridPosition extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'grid_id', 'area_name', 'label', 'type',
        'config_json', 'scope', 'is_overridable',
        'mobile_order', 'min_height',
        'align_self', 'justify_self', 'max_width', 'overflow',
        'background_json', 'padding_json', 'border_json',
        'shadow', 'css_class', 'full_bleed',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'background_json' => 'array',
            'padding_json' => 'array',
            'border_json' => 'array',
            'is_overridable' => 'boolean',
            'full_bleed' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function grid(): BelongsTo
    {
        return $this->belongsTo(Grid::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(PositionOverride::class);
    }

    public function positionBlocks(): HasMany
    {
        return $this->hasMany(GridPositionBlock::class)->orderBy('order');
    }

    /**
     * Get the override for a specific page.
     */
    public function getOverrideForPage(string $pageId): ?PositionOverride
    {
        return $this->overrides()->where('page_id', $pageId)->first();
    }

    public function getOverrideForPost(string $postId): ?PositionOverride
    {
        return $this->overrides()->where('post_id', $postId)->first();
    }
}
