<?php

namespace App\Domain\Blocks\Services;

use App\Domain\Blocks\Support\BlockTreeValidator;
use App\Models\Block;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BlockService
{
    /** Tables carrying an explicit content_revision (F13). */
    private const REVISIONED_TABLES = ['pages', 'posts', 'theme_templates'];

    public function __construct(private BlockTreeValidator $validator = new BlockTreeValidator(new BlockRegistry()))
    {
        // Container-resolved callers get the app registry; the default keeps
        // `new BlockService()` working for existing programmatic callers.
        if (app()->bound(BlockRegistry::class)) {
            $this->validator = new BlockTreeValidator(app(BlockRegistry::class));
        }
    }

    /**
     * Replace a blockable's whole block tree.
     *
     * @param  string|null  $expectedRevision  F13: the content revision the caller
     *         loaded. When given, the write only happens if it still matches —
     *         compared-and-incremented in this transaction (row lock), so two
     *         concurrent saves from the same revision yield one success and one
     *         StaleContentRevisionException. Null = programmatic/legacy writer
     *         (still increments the revision, so interactive clients notice).
     * @param  bool  $trusted  F14: trusted importers/seeders skip the per-field
     *         shape rules; unknown types are refused for everyone.
     *
     * @throws \App\Domain\Blocks\Exceptions\StaleContentRevisionException
     * @throws \App\Domain\Blocks\Exceptions\InvalidBlockTreeException
     */
    public function syncBlocks(Model $blockable, array $blocksData, ?string $expectedRevision = null, bool $trusted = false): array
    {
        // Validate BEFORE the transaction: nothing is touched on invalid input.
        $this->validator->assertValid($blocksData, rules: !$trusted);

        return DB::transaction(function () use ($blockable, $blocksData, $expectedRevision) {
            $this->bumpRevision($blockable, $expectedRevision);

            $existingBlockIds = Block::where('blockable_type', $blockable->getMorphClass())
                ->where('blockable_id', $blockable->getKey())
                ->pluck('id')->all();

            // FIX-C11a: this is a destructive delete-all-then-reinsert. Block
            // ids are preserved (the client resends them), but the delete
            // CASCADEs away rows that FK block_id (block-scoped theme overrides,
            // grid-position block links). Snapshot them and restore the ones
            // whose block id survives the round trip.
            $themeOverrides = \App\Models\ThemeOverride::whereIn('block_id', $existingBlockIds)->get();
            $gridPositionBlocks = \App\Models\GridPositionBlock::whereIn('block_id', $existingBlockIds)->get();

            // Delete all existing blocks for this blockable
            Block::where('blockable_type', $blockable->getMorphClass())
                ->where('blockable_id', $blockable->getKey())
                ->delete();

            // Insert new block tree
            $this->insertBlocks($blockable, $blocksData);

            // Restore block-scoped rows whose block still exists post-reinsert.
            $survivingIds = Block::where('blockable_type', $blockable->getMorphClass())
                ->where('blockable_id', $blockable->getKey())
                ->pluck('id')->flip();
            foreach ($themeOverrides as $ov) {
                if ($survivingIds->has($ov->block_id) && !\App\Models\ThemeOverride::whereKey($ov->id)->exists()) {
                    \App\Models\ThemeOverride::create($ov->getAttributes());
                }
            }
            foreach ($gridPositionBlocks as $gpb) {
                if ($survivingIds->has($gpb->block_id) && !\App\Models\GridPositionBlock::whereKey($gpb->id)->exists()) {
                    \App\Models\GridPositionBlock::create($gpb->getAttributes());
                }
            }

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

    /**
     * Trusted programmatic write (seeders, importers, wizards, clones): the
     * tree comes from code or from content that already lived in this CMS,
     * so per-field shape rules are skipped. Unknown types are still refused
     * and the content revision still advances.
     */
    public function syncTrusted(Model $blockable, array $blocksData): array
    {
        return $this->syncBlocks($blockable, $blocksData, null, true);
    }

    /**
     * The content version token a client must send back as expected_version.
     * Revisioned tables (F13) expose their content_revision; other blockables
     * keep the legacy count:max(updated_at) token.
     */
    public function blocksVersion(Model $blockable): ?string
    {
        if ($this->isRevisioned($blockable)) {
            $rev = DB::table($blockable->getTable())->where('id', $blockable->getKey())->value('content_revision');

            return (string) ((int) $rev);
        }

        $q = Block::where('blockable_type', $blockable->getMorphClass())
            ->where('blockable_id', $blockable->getKey());
        $latest = (clone $q)->max('updated_at');

        return $latest ? $q->count() . ':' . $latest : '0:';
    }

    /**
     * Compare-and-increment the owning record's content revision (F13). Must
     * run inside the caller's transaction: the UPDATE takes the row lock, a
     * concurrent writer waits, re-evaluates the WHERE against the committed
     * value and fails the compare. Returns the new revision token.
     *
     * @throws \App\Domain\Blocks\Exceptions\StaleContentRevisionException
     */
    public function bumpRevision(Model $blockable, ?string $expectedRevision = null): ?string
    {
        if (!$this->isRevisioned($blockable)) {
            return null;
        }
        $q = DB::table($blockable->getTable())->where('id', $blockable->getKey());
        if ($expectedRevision !== null) {
            if (!preg_match('/^\d{1,18}$/', $expectedRevision)) {
                throw new \App\Domain\Blocks\Exceptions\StaleContentRevisionException($expectedRevision);
            }
            $q->where('content_revision', (int) $expectedRevision);
        }
        $affected = $q->update(['content_revision' => DB::raw('content_revision + 1')]);
        if ($affected === 0) {
            throw new \App\Domain\Blocks\Exceptions\StaleContentRevisionException(
                (string) $expectedRevision,
                $this->blocksVersion($blockable),
            );
        }

        return $this->blocksVersion($blockable);
    }

    public function isRevisioned(Model $blockable): bool
    {
        return in_array($blockable->getTable(), self::REVISIONED_TABLES, true);
    }

    public function getBlockTree(Model $blockable): array
    {
        $blocks = Block::where('blockable_type', $blockable->getMorphClass())
            ->where('blockable_id', $blockable->getKey())
            ->orderBy('order')
            ->get();

        return $this->buildTree($blocks);
    }

    /**
     * Insert a block tree with a bounded number of queries (H03, audit
     * 2026-09-22): one lookup for id collisions, then chunked multi-row
     * INSERTs — the old per-node exists()+create() cost ~1 query per block
     * (7.6 s for a 500-block save). Semantics unchanged: client ids are kept
     * unless they still exist elsewhere or repeat inside the payload; sibling
     * order is the array position; __style/__animation/__responsive/
     * __advanced are merged into data.
     */
    private function insertBlocks(Model $blockable, array $blocksData, ?string $parentId = null): void
    {
        // Ids already used by OTHER rows (this blockable's rows were deleted).
        $payloadIds = [];
        $collect = function (array $nodes) use (&$collect, &$payloadIds) {
            foreach ($nodes as $n) {
                if (!empty($n['id']) && is_string($n['id']) && Str::isUuid($n['id'])) {
                    $payloadIds[] = $n['id'];
                }
                if (!empty($n['children']) && is_array($n['children'])) {
                    $collect($n['children']);
                }
            }
        };
        $collect($blocksData);
        $taken = [];
        foreach (array_chunk(array_unique($payloadIds), 1000) as $chunk) {
            foreach (Block::whereIn('id', $chunk)->pluck('id') as $id) {
                $taken[(string) $id] = true;
            }
        }

        $rows = [];
        $now = now();
        $type = $blockable->getMorphClass();
        $ownerId = $blockable->getKey();
        $flatten = function (array $nodes, ?string $parent) use (&$flatten, &$rows, &$taken, $now, $type, $ownerId) {
            foreach (array_values($nodes) as $index => $blockData) {
                $data = $blockData['data'] ?? [];
                $style = $blockData['style'] ?? null;
                if ($style) {
                    $data['__style'] = $style;
                }
                foreach (['animation' => '__animation', 'responsive' => '__responsive', 'advanced' => '__advanced'] as $k => $dk) {
                    if (!empty($blockData[$k])) {
                        $data[$dk] = $blockData[$k];
                    }
                }

                $blockId = $blockData['id'] ?? null;
                if (!$blockId || !is_string($blockId) || !Str::isUuid($blockId) || isset($taken[$blockId])) {
                    $blockId = Str::uuid()->toString();
                }
                $taken[$blockId] = true; // a repeat inside the payload gets a fresh id

                $rows[] = [
                    'id' => $blockId,
                    'blockable_type' => $type,
                    'blockable_id' => $ownerId,
                    'parent_block_id' => $parent,
                    'type' => $blockData['type'],
                    'level' => $blockData['level'] ?? 'module',
                    'preset_id' => $blockData['preset_id'] ?? null,
                    'data' => json_encode($data),
                    'style' => $style === null ? null : json_encode($style),
                    'order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (!empty($blockData['children'])) {
                    $flatten($blockData['children'], $blockId);
                }
            }
        };
        $flatten($blocksData, $parentId);

        // Parents precede children in $rows (depth-first), so FK order holds per chunk.
        foreach (array_chunk($rows, 200) as $chunk) {
            Block::insert($chunk);
        }
    }

    /**
     * Nest a flat, order-sorted block collection. Groups by parent ONCE
     * (H03: the old per-node where() made this quadratic — 2.7 s for 575
     * blocks, on every load and after every save). Output is unchanged.
     */
    private function buildTree($blocks, ?string $parentId = null): array
    {
        $byParent = [];
        foreach ($blocks as $block) {
            $byParent[(string) ($block->parent_block_id ?? '')][] = $block;
        }

        return $this->nest($byParent, (string) ($parentId ?? ''));
    }

    private function nest(array $byParent, string $parentKey): array
    {
        $tree = [];
        foreach ($byParent[$parentKey] ?? [] as $block) {
            $data = $block->data ?? [];
            $style = $block->style;

            $node = [
                'id' => $block->id,
                'type' => $block->type,
                'level' => $block->level ?? 'module',
                'preset_id' => $block->preset_id,
                'data' => $data,
                'order' => $block->order,
                'children' => $this->nest($byParent, (string) $block->id),
            ];

            // Restore style/animation/responsive/advanced from data or style column
            if ($style) {
                $node['style'] = $style;
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
