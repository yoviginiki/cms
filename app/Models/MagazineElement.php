<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MagazineElement extends Model
{
    use HasUuids;

    protected $fillable = [
        'magazine_page_id', 'type', 'content',
        'x', 'y', 'width', 'height', 'rotation', 'z_index', 'style',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'style' => 'array',
            'x' => 'decimal:2',
            'y' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'rotation' => 'decimal:2',
            'z_index' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MagazinePage::class, 'magazine_page_id');
    }
}
