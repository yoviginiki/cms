<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GridPositionBlock extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'grid_position_id', 'block_id', 'order',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(GridPosition::class, 'grid_position_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
