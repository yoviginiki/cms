<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockTemplate extends Model
{
    protected $fillable = [
        'site_id', 'name', 'category', 'description', 'blocks_data', 'preview_image', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'blocks_data' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
