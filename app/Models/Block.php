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

    /**
     * Children in render order. Uses the tree preloaded by preloadTree()
     * when present (H03: the renderer used to issue one query per block —
     * 575 queries for a 500-block page), else queries as before.
     */
    public function childrenOrdered(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->relationLoaded('childrenTree')) {
            return $this->getRelation('childrenTree');
        }

        return $this->children()->orderBy('order')->get();
    }

    /**
     * Load every block of the roots' owners in ONE query per owner and attach
     * each node's ordered children as the 'childrenTree' relation.
     *
     * @param  iterable<Block>  $roots
     */
    public static function preloadTree(iterable $roots): void
    {
        $owners = [];
        foreach ($roots as $root) {
            $owners[$root->blockable_type . '|' . $root->blockable_id][] = $root;
        }
        foreach ($owners as $key => $ownerRoots) {
            [$type, $id] = explode('|', $key, 2);
            $all = static::where('blockable_type', $type)->where('blockable_id', $id)->orderBy('order')->get();
            $byParent = [];
            foreach ($all as $b) {
                if ($b->parent_block_id !== null) {
                    $byParent[(string) $b->parent_block_id][] = $b;
                }
            }
            $attach = function (Block $node) use (&$attach, $byParent): void {
                $kids = new \Illuminate\Database\Eloquent\Collection($byParent[(string) $node->id] ?? []);
                $node->setRelation('childrenTree', $kids);
                foreach ($kids as $kid) {
                    $attach($kid);
                }
            };
            foreach ($ownerRoots as $root) {
                $attach($root);
            }
        }
    }
}
