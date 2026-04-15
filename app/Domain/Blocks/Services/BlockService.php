<?php

namespace App\Domain\Blocks\Services;

use App\Models\Block;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlockService
{
    public function syncBlocks(Model $blockable, array $blocksData): array
    {
        return DB::transaction(function () use ($blockable, $blocksData) {
            // Delete all existing blocks for this blockable
            Block::where('blockable_type', $blockable->getMorphClass())
                ->where('blockable_id', $blockable->getKey())
                ->delete();

            // Insert new block tree
            $this->insertBlocks($blockable, $blocksData);

            return $this->getBlockTree($blockable);
        });
    }

    public function getBlockTree(Model $blockable): array
    {
        $blocks = Block::where('blockable_type', $blockable->getMorphClass())
            ->where('blockable_id', $blockable->getKey())
            ->orderBy('order')
            ->get();

        return $this->buildTree($blocks);
    }

    private function insertBlocks(Model $blockable, array $blocksData, ?string $parentId = null): void
    {
        foreach ($blocksData as $blockData) {
            $children = $blockData['children'] ?? [];

            $block = Block::create([
                'id' => $blockData['id'] ?? Str::uuid()->toString(),
                'blockable_type' => $blockable->getMorphClass(),
                'blockable_id' => $blockable->getKey(),
                'parent_block_id' => $parentId,
                'type' => $blockData['type'],
                'data' => $blockData['data'] ?? [],
                'order' => $blockData['order'],
            ]);

            if (!empty($children)) {
                $this->insertBlocks($blockable, $children, $block->id);
            }
        }
    }

    private function buildTree($blocks, ?string $parentId = null): array
    {
        $tree = [];

        foreach ($blocks->where('parent_block_id', $parentId) as $block) {
            $node = [
                'id' => $block->id,
                'type' => $block->type,
                'data' => $block->data,
                'order' => $block->order,
                'children' => $this->buildTree($blocks, $block->id),
            ];
            $tree[] = $node;
        }

        return $tree;
    }
}
