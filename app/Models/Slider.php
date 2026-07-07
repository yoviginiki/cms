<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Concerns\PurgesBlocksOnForceDelete;

/**
 * Standalone slider library entity. The block tree (slider → slides → layers)
 * lives in the polymorphic blocks table (blockable_type 'slider'); pages embed
 * a slider via the slider_ref block, tracked as an entity_references edge.
 */
class Slider extends Model
{
    use HasFactory, HasUuids, SoftDeletes, PurgesBlocksOnForceDelete;

    protected $fillable = [
        'site_id', 'name', 'status', 'root_block_id', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable');
    }

    public function rootBlock(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'root_block_id');
    }
}
