<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GridAssignment extends Model
{
    use HasUuids;

    protected $fillable = [
        'site_id', 'grid_id', 'assignable_type',
        'assignable_id', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function grid(): BelongsTo
    {
        return $this->belongsTo(Grid::class);
    }
}
