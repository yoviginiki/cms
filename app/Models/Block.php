<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Block extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        // 'id' is fillable so the editor can preserve block ids across saves
        // (FIX-C11a). Without it Block::create(['id'=>…]) dropped the id and
        // HasUuids minted a new one every save — breaking theme_overrides,
        // grid-position links, and page_version snapshots on every edit.
        'id',
        'blockable_id', 'blockable_type', 'parent_block_id',
        'type', 'level', 'preset_id', 'data', 'style', 'order',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'style' => 'array',
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
