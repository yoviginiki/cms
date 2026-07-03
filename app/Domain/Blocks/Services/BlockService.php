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

            // Recompute this source's entity-reference edges in the same
            // transaction. Synchronous by design: extraction is in-memory JSON
            // walking plus a couple of indexed slug lookups — negligible next
            // to the full tree rewrite above, and it keeps edges exactly in
            // step with the blocks they were extracted from.
            try {
                app(\App\Domain\References\Services\ReferenceRecorder::class)->recompute($blockable);
            } catch (\Throwable $e) {
                // Never fail a content save over reference bookkeeping
                logger()->warning("entity_references recompute failed for {$blockable->getMorphClass()}:{$blockable->getKey()}: {$e->getMessage()}");
            }

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

            // Merge style/animation/responsive/advanced into data if present
            $data = $blockData['data'] ?? [];
            $style = $blockData['style'] ?? null;
            if ($style) {
                $data['__style'] = $style;
            }
            if (!empty($blockData['animation'])) {
                $data['__animation'] = $blockData['animation'];
            }
            if (!empty($blockData['responsive'])) {
                $data['__responsive'] = $blockData['responsive'];
            }
            if (!empty($blockData['advanced'])) {
                $data['__advanced'] = $blockData['advanced'];
            }

            $block = Block::create([
                'id' => $blockData['id'] ?? Str::uuid()->toString(),
                'blockable_type' => $blockable->getMorphClass(),
                'blockable_id' => $blockable->getKey(),
                'parent_block_id' => $parentId,
                'type' => $blockData['type'],
                'level' => $blockData['level'] ?? 'module',
                'preset_id' => $blockData['preset_id'] ?? null,
                'data' => $data,
                'style' => $style,
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
            $data = $block->data ?? [];

            $node = [
                'id' => $block->id,
                'type' => $block->type,
                'level' => $block->level ?? 'module',
                'preset_id' => $block->preset_id,
                'data' => $data,
                'order' => $block->order,
                'children' => $this->buildTree($blocks, $block->id),
            ];

            // Restore style/animation/responsive/advanced from data or style column
            if ($block->style) {
                $node['style'] = $block->style;
            } elseif (!empty($data['__style'])) {
                $node['style'] = $data['__style'];
            }
            if (!empty($data['__animation'])) {
                $node['animation'] = $data['__animation'];
            }
            if (!empty($data['__responsive'])) {
                $node['responsive'] = $data['__responsive'];
            }
            if (!empty($data['__advanced'])) {
                $node['advanced'] = $data['__advanced'];
            }

            $tree[] = $node;
        }

        return $tree;
    }
}
