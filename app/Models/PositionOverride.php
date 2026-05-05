<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionOverride extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'grid_position_id', 'page_id', 'post_id', 'content_json',
    ];

    protected function casts(): array
    {
        return [
            'content_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(GridPosition::class, 'grid_position_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
