<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Block extends Model
{
    use HasUuids;

    protected $fillable = [
        'blockable_id', 'blockable_type', 'parent_block_id',
        'type', 'data', 'order',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Block::class, 'parent_block_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Block::class, 'parent_block_id')->orderBy('order');
    }
}
