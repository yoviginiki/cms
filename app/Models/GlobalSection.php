<?php

namespace App\Models;

use App\Domain\Concerns\PurgesBlocksOnForceDelete;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A Global Section (Builder Experience P2): a reusable chunk of blocks shared
 * across pages by REFERENCE (the global_ref block), not copied. Its block tree
 * lives in the polymorphic blocks table (blockable_type 'global_section');
 * editing + republishing flags every embedding page stale via the existing
 * references/staleness engine. Modelled on Slider.
 */
class GlobalSection extends Model
{
    use HasFactory, HasUuids, SoftDeletes, PurgesBlocksOnForceDelete;

    protected $fillable = [
        'site_id', 'name', 'status', 'published_at',
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
}
